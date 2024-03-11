<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Hydration\Entity;

use Danilovl\DoctrineEntityDtoBundle\Exception\LogicException;
use Doctrine\Common\Collections\{
    Collection,
    ArrayCollection
};
use Danilovl\DoctrineEntityDtoBundle\Service\ConfigurationService;
use Doctrine\DBAL\Types\Type;
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

    protected array $collectionMapping = [];

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
            $dto = $this->createClassInstance($this->dtoClass);
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

                $finalValue = $this->getValue($rowKey, $value);
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

            /** @var string $parentObjectClassName */
            $parentObjectClassName = get_class($parentObject);
            if (str_contains($parentObjectClassName, $this->getRuntimeClassSuffix())) {
                $parentObjectClassName = get_parent_class($parentObject);
            }

            $mapping = $this->_metadataCache[$parentObjectClassName];
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

                $childEntity = $this->createClassInstance($targetEntity);
                $isSetValue = $this->setRowValuesToEntity($rowKeys, $row, $childEntity);

                $identifier = $this->getPrimaryId($row, $data, $parentFieldName);
                $isInCollection = $this->collectionMapping[$parentObjectClassName][$targetEntity][$identifier] ?? null;

                if ($isInCollection) {
                    continue;
                } else {
                    $this->collectionMapping[$parentObjectClassName][$targetEntity][$identifier] = true;
                }

                if ($isSetValue) {
                    $collection->add($childEntity);
                }

                if (is_array($children)) {
                    $this->createChildDTO($children, $data, $row, $childEntity);
                }

                continue;
            }

            if (!$isReadable) {
                $childEntity = $this->createClassInstance($targetEntity);
            } else {
                /** @var object $childEntity */
                $childEntity = $this->propertyAccessor->getValue($parentObject, $relationFieldName);

                if ($childEntity === null) {
                    $childEntity = $this->createClassInstance($targetEntity);
                }
            }

            $isSetValue = $this->setRowValuesToEntity($rowKeys, $row, $childEntity);
            if ($isSetValue) {
                $this->propertyAccessor->setValue($parentObject, $relationFieldName, $childEntity);
            }

            if (is_array($children)) {
                $this->createChildDTO($children, $data, $row, $childEntity);
            }
        }
    }

    protected function setRowValuesToEntity(array $rowKeys, array $row, object $object): bool
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->_rsm;
        $isSetValue = false;

        foreach ($rowKeys as $rowKey) {
            $value = $row[$rowKey];
            if ($value === null) {
                continue;
            }

            $fieldName = $resultSetMapping->fieldMappings[$rowKey];
            $finalValue = $this->getValue($rowKey, $value);
            $this->propertyAccessor->setValue($object, $fieldName, $finalValue);

            $isSetValue = true;
        }

        return $isSetValue;
    }

    protected function getValue(string $rowKey, mixed $value): mixed
    {
        /** @var Type|null $type */
        $type = $this->hydrateColumnInfo($rowKey)['type'] ?? null;

        return $type !== null ? $type->convertToPHPValue($value, $this->_platform) : $value;
    }

    protected function getPrimaryId(array $row, array $data, string $entityMappingsKey): string|int
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->_rsm;
        $mappingsKeyData = $data[$entityMappingsKey];
        $primaryId = null;

        foreach ($mappingsKeyData as $key) {
            $value = $row[$key];

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
        $entityClass = $resultSetMapping->aliasMap[$entityMappingsKey];
        $this->dtoClass = $entityClass;

        if (!ConfigurationService::getIsEnableEntityRuntimeNameDTO()) {
            return;
        }

        $shortName = (new ReflectionClass($entityClass))->getShortName();
        $this->dtoClass = sprintf('%s%s', $shortName, $this->getRuntimeClassSuffix());

        if (class_exists($this->dtoClass)) {
            return;
        }

        $classDefinition = sprintf('class %s extends %s {};', $this->dtoClass, $entityClass);
        eval($classDefinition);
    }

    protected function getRuntimeClassSuffix(): string
    {
        return 'RuntimeDTO';
    }

    protected function createClassInstance(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
