<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Scalar;

use Danilovl\DoctrineEntityDtoBundle\Hydration\Scalar\AbstractScalarHydration;
use Doctrine\DBAL\Types\{
    DateType,
    TextType,
    StringType,
    BooleanType,
    DateTimeType,
    IntegerType
};
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\ORM\Query\ResultSetMapping;

class AbstractScalarHydrationMock extends AbstractScalarHydration
{
    public function __construct(ResultSetMapping $rsm, MySQL84Platform $platform)
    {
        $this->rsm = $rsm;
        $this->platform = $platform;
    }

    public function publicHydrateRowData(array $row, array &$result): void
    {
        $this->hydrateRowData($row, $result);
    }

    protected function hydrateColumnInfo($key): ?array
    {
        $columnInfo = [
            'complete_0' => [
                'isIdentifier' => false,
                'fieldName' => 'complete',
                'type' => new BooleanType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'notify_complete_1' => [
                'isIdentifier' => false,
                'fieldName' => 'notifyComplete',
                'type' => new BooleanType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'deadline_2' => [
                'isIdentifier' => false,
                'fieldName' => 'deadline',
                'type' => new DateType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'id_3' => [
                'isIdentifier' => true,
                'fieldName' => 'id',
                'type' => new IntegerType,
                'dqlAlias' => 'task',
                'enumType' => null,
            ],
            'id_4' => [
                'isIdentifier' => true,
                'fieldName' => 'id',
                'type' => new IntegerType,
                'dqlAlias' => 'task',
                'enumType' => null,
            ],
            'name_4' => [
                'isIdentifier' => false,
                'fieldName' => 'name',
                'type' => new StringType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'description_5' => [
                'isIdentifier' => false,
                'fieldName' => 'description',
                'type' => new TextType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'active_6' => [
                'isIdentifier' => false,
                'fieldName' => 'active',
                'type' => new BooleanType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'active_7' => [
                'isIdentifier' => false,
                'fieldName' => 'active',
                'type' => new BooleanType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'created_at_7' => [
                'isIdentifier' => false,
                'fieldName' => 'createdAt',
                'type' => new DateTimeType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'updated_at_8' => [
                'isIdentifier' => false,
                'fieldName' => 'updatedAt',
                'type' => new DateTimeType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
            'updated_at_9' => [
                'isIdentifier' => false,
                'fieldName' => 'updatedAt',
                'type' => new DateTimeType,
                'dqlAlias' => 'task',
                'enumType' => null
            ],
        ];

        return $columnInfo[$key] ?? null;
    }
}
