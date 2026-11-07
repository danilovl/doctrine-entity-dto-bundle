<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Entity;

use Danilovl\DoctrineEntityDtoBundle\Hydration\Entity\AbstractEntityHydration;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class AbstractEntityHydrationMock extends AbstractEntityHydration
{
    /** @var array<string, array{isIdentifier?: bool, fieldName?: string, type?: Type, dqlAlias?: string, enumType?: null}> */
    public array $columnInfo = [];

    public function __construct(
        EntityManagerInterface $em,
        ?ResultSetMapping $rsm = null,
        ?AbstractPlatform $platform = null
    ) {
        parent::__construct($em);

        if ($rsm !== null) {
            $this->rsm = $rsm;
        }

        if ($platform !== null) {
            $this->platform = $platform;
        }
    }

    public function publicHydrateRowData(array $row, array &$result): void
    {
        $this->hydrateRowData($row, $result);
    }

    public function publicCreateClassInstance(string $class): object
    {
        return $this->createClassInstance($class);
    }

    protected function hydrateColumnInfo(string $key): ?array
    {
        return $this->columnInfo[$key] ?? parent::hydrateColumnInfo($key);
    }
}
