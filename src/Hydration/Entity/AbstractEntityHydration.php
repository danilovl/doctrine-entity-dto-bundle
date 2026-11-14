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
use WeakMap;
use Symfony\Component\PropertyAccess\{
    PropertyAccess,
    PropertyAccessorInterface
};

class AbstractEntityHydration extends BaseAbstractHydration
{
    protected readonly PropertyAccessorInterface $propertyAccessor;

    protected string $dtoClass;

    /** @var WeakMap<object, array<string, array<string|int, object>>> */
    protected WeakMap $collectionMapping;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);

        $this->collectionMapping = new WeakMap;
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

    protected function cleanup(): void
    {
        parent::cleanup();

        $this->collectionMapping = new WeakMap;
    }

    protected function hydrateRowData(array $row, array &$result): void
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->rsm;

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
        /** @var object|null $dto */
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

            $this->createChildDTO($children, $entityMappingsKeyData, $row, $dto);
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
        $resultSetMapping = $this->rsm;

        foreach ($tree as $parentFieldName => $children) {
            /** @var array $rowKeys */
            $rowKeys = $data[$parentFieldName];
            $targetEntity = $resultSetMapping->aliasMap[$parentFieldName];
            $relationFieldName = $resultSetMapping->relationMap[$parentFieldName];

            $parentObjectClassName = get_class($parentObject);
            if (str_contains($parentObjectClassName, $this->getRuntimeClassSuffix())) {
                $parentClass = get_parent_class($parentObject);
                if ($parentClass !== false) {
                    $parentObjectClassName = $parentClass;
                }
            }

            $mapping = $this->metadataCache[$parentObjectClassName] ?? null;
            if ($mapping === null && ($this->em->getMetadataFactory()->hasMetadataFor($parentObjectClassName) || !$this->em->getMetadataFactory()->isTransient($parentObjectClassName))) {
                $mapping = $this->getClassMetadata($parentObjectClassName);
            }

            /** @var array{type: int}|null $associationMapping */
            $associationMapping = $mapping?->associationMappings[$relationFieldName] ?? null;

            if ($mapping !== null && $associationMapping && !($associationMapping['type'] & ClassMetadata::TO_ONE)) {
                /** @var Collection|null $collection */
                $collection = $mapping->getFieldValue($parentObject, $relationFieldName);
                if (!$collection instanceof Collection) {
                    $collection = new ArrayCollection;
                    $mapping->setFieldValue($parentObject, $relationFieldName, $collection);
                }

                $childEntity = $this->createClassInstance($targetEntity);
                $isSetValue = $this->setRowValuesToEntity($rowKeys, $row, $childEntity);

                $identifier = $this->getPrimaryId($row, $data, $parentFieldName);
                $this->collectionMapping[$parentObject] ??= [];
                /** @var object|null $existingChildEntity */
                $existingChildEntity = $this->collectionMapping[$parentObject][$relationFieldName][$identifier] ?? null;

                if ($existingChildEntity !== null) {
                    $childEntity = $existingChildEntity;
                } else {
                    $this->collectionMapping[$parentObject][$relationFieldName][$identifier] = $childEntity;

                    if ($isSetValue) {
                        $collection->add($childEntity);
                    }
                }

                if (is_array($children)) {
                    $this->createChildDTO($children, $data, $row, $childEntity);
                }

                continue;
            }

            $isReadable = $this->propertyAccessor->isReadable($parentObject, $relationFieldName);

            if (!$isReadable) {
                $childEntity = $this->createClassInstance($targetEntity);
            } else {
                /** @var object|null $childEntity */
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
        $resultSetMapping = $this->rsm;
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

        return $type !== null ? $type->convertToPHPValue($value, $this->platform) : $value;
    }

    protected function getPrimaryId(array $row, array $data, string $entityMappingsKey): string|int
    {
        /** @var ResultSetMapping $resultSetMapping */
        $resultSetMapping = $this->rsm;
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
        $resultSetMapping = $this->rsm;

        /** @var string $entityMappingsKey */
        $entityMappingsKey = array_key_first($resultSetMapping->entityMappings);
        $entityClass = $resultSetMapping->aliasMap[$entityMappingsKey];
        $this->dtoClass = $entityClass;

        if (!ConfigurationService::getIsEnableEntityRuntimeNameDTO()) {
            return;
        }

        $reflectionClass = new ReflectionClass($entityClass);
        if ($reflectionClass->isFinal()) {
            return;
        }

        $shortName = $reflectionClass->getShortName();
        $dtoClass = sprintf('%s%s', $shortName, $this->getRuntimeClassSuffix());

        $isDtoClassOccupied = class_exists($dtoClass, false) && get_parent_class($dtoClass) !== $entityClass;
        if ($isDtoClassOccupied) {
            $entityClassHash = mb_substr(md5($entityClass), 0, 8);
            $dtoClass = sprintf('%s%s%s', $shortName, $entityClassHash, $this->getRuntimeClassSuffix());
        }

        $this->dtoClass = $dtoClass;

        if (class_exists($dtoClass, false)) {
            return;
        }

        $classModifier = $reflectionClass->isReadOnly() ? 'readonly ' : '';
        $classDefinition = sprintf('%sclass %s extends \\%s {};', $classModifier, $dtoClass, $entityClass);
        eval($classDefinition);
    }

    protected function getRuntimeClassSuffix(): string
    {
        return 'RuntimeDTO';
    }

    protected function createClassInstance(string $class): object
    {
        $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $this->initCollections($instance, $class);

        return $instance;
    }

    protected function initCollections(object $instance, string $class): void
    {
        $entityClass = $class;
        if (str_contains($class, $this->getRuntimeClassSuffix())) {
            $parentClass = get_parent_class($class);
            if ($parentClass === false) {
                return;
            }

            $entityClass = $parentClass;
        }

        if (!class_exists($entityClass)) {
            return;
        }

        $mapping = $this->metadataCache[$entityClass] ?? null;
        if ($mapping === null && ($this->em->getMetadataFactory()->hasMetadataFor($entityClass) || !$this->em->getMetadataFactory()->isTransient($entityClass))) {
            $mapping = $this->getClassMetadata($entityClass);
        }

        if ($mapping === null) {
            return;
        }

        foreach ($mapping->associationMappings as $fieldName => $associationMapping) {
            /** @var array{type: int} $associationMapping */
            if (!($associationMapping['type'] & ClassMetadata::TO_ONE)) {
                $mapping->setFieldValue($instance, $fieldName, new ArrayCollection);
            }
        }
    }
}
