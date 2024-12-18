<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Hydration\Scalar;

use Danilovl\DoctrineEntityDtoBundle\Exception\LogicException;
use Danilovl\DoctrineEntityDtoBundle\Service\ConfigurationService;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator as BaseAbstractHydration;
use Symfony\Component\PropertyAccess\{
    PropertyAccess,
    PropertyAccessorInterface
};
use ReflectionClass;

class AbstractScalarHydration extends BaseAbstractHydration
{
    protected readonly PropertyAccessorInterface $propertyAccessor;

    public static ?string $dtoClass = null;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);

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

        self::$dtoClass = null;

        return array_values($result);
    }

    protected function hydrateRowData(array $row, array &$result): void
    {
        if (self::$dtoClass === null) {
            throw new LogicException('DTO class is not set.');
        }

        if (!in_array(self::$dtoClass, ConfigurationService::getScalarDTO(), true)) {
            throw new LogicException('DTO class is not in scalar DTOs.');
        }

        $rowData = $this->prepareRowData($row);

        $reflectionClass = new ReflectionClass(self::$dtoClass);
        $reflectionConstructor = $reflectionClass->getConstructor();

        $data = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            if (!array_key_exists($propertyName, $rowData)) {
                continue;
            }

            $data[$propertyName] = $rowData[$propertyName];
        }

        if ($reflectionConstructor) {
            $dto = $reflectionClass->newInstance(...$data);
        } else {
            $dto = $reflectionClass->newInstanceWithoutConstructor();

            foreach ($data as $fieldName => $value) {
                $this->propertyAccessor->setValue($dto, $fieldName, $value);
            }
        }

        $result[] = $dto;
    }

    protected function prepareRowData(array $row): array
    {
        $result = [];

        foreach ($row as $key => $value) {
            $cacheKeyInfo = $this->hydrateColumnInfo($key);
            if ($cacheKeyInfo === null) {
                continue;
            }

            /** @var string $fieldName */
            $fieldName = $cacheKeyInfo['fieldName'];
            /** @var Type $type */
            $type = $cacheKeyInfo['type'];

            if (array_key_exists($fieldName, $result)) {
                $message = sprintf('Already exists field "%s" in result. Field name must be unique. Use AS operator', $fieldName);

                throw new LogicException($message);
            }

            $result[$fieldName] = $type->convertToPHPValue($value, $this->platform);
        }

        return $result;
    }
}
