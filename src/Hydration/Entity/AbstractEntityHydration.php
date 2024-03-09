<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Hydration\Entity;

use Danilovl\DoctrineEntityDtoBundle\Exception\LogicException;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\{
    Collection,
    ArrayCollection
};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator as BaseAbstractHydration;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\ResultSetMapping;
use ReflectionClass;
use Symfony\Component\PropertyAccess\{
    PropertyAccess,
    PropertyAccessorInterface
};

class AbstractEntityHydration extends BaseAbstractHydration
{
    protected readonly PropertyAccessorInterface $propertyAccessor;

    protected string $dtoClass;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager);

        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableMagicMethods()
            ->getPropertyAccessor();
    }

    protected function hydrateAllData(): array
    {
        $result = [];

        while ($row = $this->statement()->fetchAssociative()) {
            $this->hydrateRowData($row, $result);
        }

        return array_values($result);
    }

    protected function hydrateRowData(array $row, array &$result): void
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->_rsm;

        if (!empty($resultSetMapping->scalarMappings)) {
            throw new LogicException('Hydration of scalar values is not supported.');
        }

        $this->warmUpCache($row);
        $this->initDtoClass();

        /** @var string $entityMappingsKey */
        $entityMappingsKey = array_key_first($resultSetMapping->entityMappings);

        $entityMappingsKeyData = [];
        foreach ($resultSetMapping->columnOwnerMap as $column => $value) {
            $entityMappingsKeyData[$value][] = $column;
        }

        $primaryId = $this->getPrimaryId($row, $entityMappingsKeyData, $entityMappingsKey);

        $entityTreeMap = [
            $entityMappingsKey => $this->buildParentChildrenTree($resultSetMapping->parentAliasMap, $entityMappingsKey)
        ];

        $resultKey = $this->dtoClass . ':' . $primaryId;
        $dto = $result[$resultKey] ?? null;

        if ($dto === null) {
            $reflectionClass = new ReflectionClass($this->dtoClass);
            $dto = $reflectionClass->newInstanceWithoutConstructor();
            $result[$resultKey] = $dto;
        }

        foreach ($entityTreeMap as $fieldName => $children) {
            $rowKeys = $entityMappingsKeyData[$fieldName];
            foreach ($rowKeys as $rowKey) {
                $value = $row[$rowKey];
                if ($value === null) {
                    continue;
                }

                $fieldName = $resultSetMapping->fieldMappings[$rowKey];

                $finalValue = $this->getValue($fieldName, $value, $this->dtoClass);
                $this->propertyAccessor->setValue($dto, $fieldName, $finalValue);
            }

            if (is_array($children)) {
                $this->createChildDTO($children, $entityMappingsKeyData, $row, $dto);
            }
        }

        $result[$resultKey] = $dto;
    }

    protected function buildParentChildrenTree(array $parentAliasMap, string $root): array
    {
        $tree = [];
        foreach ($parentAliasMap as $child => $parent) {
            if ($parent === $root) {
                unset($parentAliasMap[$child]);
                $tree[$child] = $this->buildParentChildrenTree($parentAliasMap, $child);
            }
        }

        return $tree;
    }

    protected function createChildDTO(array $tree, array $data, array $row, object $parentObject): void
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->_rsm;

        foreach ($tree as $parentFieldName => $children) {
            /** @var array $rowKeys */
            $rowKeys = $data[$parentFieldName];
            $targetEntity = $resultSetMapping->aliasMap[$parentFieldName];
            $relationFieldName = $resultSetMapping->relationMap[$parentFieldName];

            $mapping = $this->_metadataCache[get_class($parentObject)];
            $associationMapping = $mapping->associationMappings[$relationFieldName] ?? null;

            $isReadable = $this->propertyAccessor->isReadable($parentObject, $relationFieldName);

            if ($associationMapping && !($associationMapping['type'] & ClassMetadata::TO_ONE)) {
                $collection = new ArrayCollection;

                if ($isReadable) {
                    /** @var Collection $collection */
                    $collection = $this->propertyAccessor->getValue($parentObject, $relationFieldName);
                } else {
                    $this->propertyAccessor->setValue($parentObject, $relationFieldName, $collection);
                }

                $reflectionClass = new ReflectionClass($targetEntity);
                $childEntity = $reflectionClass->newInstanceWithoutConstructor();

                $this->setRowValuesToEntity($rowKeys, $row, $childEntity, $targetEntity);
                $collection->add($childEntity);

                if (is_array($children)) {
                    $this->createChildDTO($children, $data, $row, $childEntity);
                }

                continue;
            }

            if (!$isReadable) {
                $reflectionClass = new ReflectionClass($targetEntity);
                $childEntity = $reflectionClass->newInstanceWithoutConstructor();
            } else {
                /** @var object $childEntity */
                $childEntity = $this->propertyAccessor->getValue($parentObject, $relationFieldName);
            }

            $this->setRowValuesToEntity($rowKeys, $row, $childEntity, $targetEntity);
            $this->propertyAccessor->setValue($parentObject, $relationFieldName, $childEntity);

            if (is_array($children)) {
                $this->createChildDTO($children, $data, $row, $childEntity);
            }
        }
    }

    protected function setRowValuesToEntity(
        array $rowKeys,
        array $row,
        object $object,
        string $targetEntity
    ): void {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->_rsm;

        foreach ($rowKeys as $rowKey) {
            $value = $row[$rowKey];
            if ($value === null) {
                continue;
            }

            $fieldName = $resultSetMapping->fieldMappings[$rowKey];
            $finalValue = $this->getValue($fieldName, $value, $targetEntity);
            $this->propertyAccessor->setValue($object, $fieldName, $finalValue);
        }
    }

    protected function getValue(string $key, mixed $value, string $dtoClass): mixed
    {
        $types = $this->_metadataCache[$dtoClass]->fieldMappings[$key];

        /** @var string $type */
        $type = $types['type'] ?? null;

        if ($type === null) {
            return $value;
        }

        if ($type === Types::JSON) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        if ($type === Types::BOOLEAN) {
            return (bool) $value;
        }

        if (in_array($type, [Types::DECIMAL, Types::FLOAT], true)) {
            return (float) $value;
        }

        if (in_array($type, [Types::INTEGER, Types::SMALLINT, Types::BIGINT], true)) {
            return (int) $value;
        }

        if (in_array($type, [Types::STRING, Types::TEXT, Types::ASCII_STRING], true)) {
            return (string) $value;
        }

        if (in_array($type, [Types::DATE_MUTABLE, Types::DATETIME_MUTABLE, Types::DATETIMETZ_MUTABLE], true)) {
            return new DateTime($value);
        }

        if (in_array($type, [Types::DATETIME_IMMUTABLE, Types::DATE_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE], true)) {
            return new DateTimeImmutable($value);
        }

        if ($type === Types::DATEINTERVAL) {
            return new DateInterval($value);
        }

        if ($type === Types::BINARY || $type === Types::BLOB) {
            return (binary) $value;
        }

        if ($type === Types::GUID) {
            return (string) $value;
        }

        if ($type === Types::ARRAY || $type === Types::SIMPLE_ARRAY) {
            return (array) $value;
        }

        if ($type === Types::OBJECT) {
            return unserialize($value);
        }

        return $value;
    }

    protected function getPrimaryId(array $row, array $data, string $entityMappingsKey): string|int
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->_rsm;

        $primaryId = null;
        foreach ($row as $key => $value) {
            $columnInfo = $this->hydrateColumnInfo($key);
            $declaringClass = $resultSetMapping->getDeclaringClass($key);
            if ($this->dtoClass !== $declaringClass && $primaryId) {
                continue;
            }

            $isIdentifier = $columnInfo['isIdentifier'] ?? null;
            if ($isIdentifier) {
                $primaryId = $value;
            }
        }

        if ($primaryId) {
            return $primaryId;
        }

        $values = [];

        $rowKeys = $data[$entityMappingsKey];
        foreach ($rowKeys as $rowKey) {
            $value = $row[$rowKey];
            if ($value === null) {
                continue;
            }

            $values[] = $value;
        }

        return md5(json_encode($values));
    }

    protected function warmUpCache(array $row): void
    {
        foreach ($row as $key => $value) {
            $this->hydrateColumnInfo($key);
        }
    }

    protected function initDtoClass(): void
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->_rsm;

        $entityMappingsKey = array_key_first($resultSetMapping->entityMappings);
        $this->dtoClass = $resultSetMapping->aliasMap[$entityMappingsKey];
    }
}
