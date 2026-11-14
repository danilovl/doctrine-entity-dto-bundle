<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Unit\Hydration\Entity;

use Danilovl\DoctrineEntityDtoBundle\Service\ConfigurationService;
use Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Entity\{
    Shop,
    City,
    Cheque,
    ChequeItem,
    AbstractEntityHydrationMock
};
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\{
    DriverManager,
    Result
};
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\{
    EntityManager,
    EntityManagerInterface,
    ORMSetup
};
use Doctrine\ORM\Query\ResultSetMapping;
use PHPUnit\Framework\TestCase;
use stdClass;

class AbstractEntityHydrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../../../Mock/Hydration/Entity'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $this->entityManager = new EntityManager($connection, $config);
        $this->platform = $this->entityManager->getConnection()->getDatabasePlatform();

        new ConfigurationService(isEnableEntityRuntimeNameDTO: false);
    }

    protected function tearDown(): void
    {
        new ConfigurationService(isEnableEntityRuntimeNameDTO: false);
    }

    public function testCreateClassInstanceInitializesToManyCollectionsOnEntity(): void
    {
        $rsm = new ResultSetMapping;
        $hydrator = new AbstractEntityHydrationMock($this->entityManager, $rsm, $this->platform);

        /** @var Shop $shop */
        $shop = $hydrator->publicCreateClassInstance(Shop::class);

        $this->assertInstanceOf(Shop::class, $shop);
        $this->assertInstanceOf(ArrayCollection::class, $shop->getCheques());
        $this->assertTrue($shop->getCheques()->isEmpty());
    }

    public function testCreateClassInstanceInitializesToManyCollectionsOnRuntimeDTO(): void
    {
        new ConfigurationService(isEnableEntityRuntimeNameDTO: true);

        $rsm = new ResultSetMapping;
        $rsm->addEntityResult(Shop::class, 's');
        $rsm->addFieldResult('s', 's_id', 'id');
        $rsm->addFieldResult('s', 's_name', 'name');

        $hydrator = new AbstractEntityHydrationMock($this->entityManager, $rsm, $this->platform);
        $hydrator->columnInfo = [
            's_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            's_name' => ['isIdentifier' => false, 'type' => Type::getType('string')],
        ];

        $row = [
            's_id' => 1,
            's_name' => 'Shop 1',
        ];

        $result = [];
        $hydrator->publicHydrateRowData($row, $result);

        $shop = reset($result);
        $this->assertInstanceOf(Shop::class, $shop);
        $this->assertSame('ShopRuntimeDTO', $shop::class);
        $this->assertInstanceOf(ArrayCollection::class, $shop->getCheques());
        $this->assertTrue($shop->getCheques()->isEmpty());
    }

    public function testCreateClassInstanceWithNonEntityClass(): void
    {
        $rsm = new ResultSetMapping;
        $hydrator = new AbstractEntityHydrationMock($this->entityManager, $rsm, $this->platform);

        $instance = $hydrator->publicCreateClassInstance(stdClass::class);

        $this->assertInstanceOf(stdClass::class, $instance);
    }

    public function testHydrateRowDataWithoutToManyJoin(): void
    {
        new ConfigurationService(isEnableEntityRuntimeNameDTO: true);

        $rsm = new ResultSetMapping;
        $rsm->addEntityResult(Shop::class, 's');
        $rsm->addFieldResult('s', 's_id', 'id');
        $rsm->addFieldResult('s', 's_name', 'name');

        $rsm->addJoinedEntityResult(City::class, 'c', 's', 'city');
        $rsm->addFieldResult('c', 'c_id', 'id');
        $rsm->addFieldResult('c', 'c_name', 'name');

        $hydrator = new AbstractEntityHydrationMock($this->entityManager, $rsm, $this->platform);
        $hydrator->columnInfo = [
            's_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            's_name' => ['isIdentifier' => false, 'type' => Type::getType('string')],
            'c_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            'c_name' => ['isIdentifier' => false, 'type' => Type::getType('string')],
        ];

        $row = [
            's_id' => 1,
            's_name' => 'Supermarket',
            'c_id' => 10,
            'c_name' => 'Moscow',
        ];

        $result = [];
        $hydrator->publicHydrateRowData($row, $result);

        $this->assertCount(1, $result);

        /** @var Shop $shop */
        $shop = reset($result);

        $this->assertSame(1, $shop->getId());
        $this->assertSame('Supermarket', $shop->getName());
        $this->assertNotNull($shop->getCity());
        $this->assertSame(10, $shop->getCity()->getId());
        $this->assertSame('Moscow', $shop->getCity()->getName());

        $this->assertInstanceOf(ArrayCollection::class, $shop->getCheques());
        $this->assertTrue($shop->getCheques()->isEmpty());
    }

    public function testHydrateRowDataWithToManyJoin(): void
    {
        new ConfigurationService(isEnableEntityRuntimeNameDTO: true);

        $rsm = new ResultSetMapping;
        $rsm->addEntityResult(Shop::class, 's');
        $rsm->addFieldResult('s', 's_id', 'id');
        $rsm->addFieldResult('s', 's_name', 'name');

        $rsm->addJoinedEntityResult(Cheque::class, 'ch', 's', 'cheques');
        $rsm->addFieldResult('ch', 'ch_id', 'id');
        $rsm->addFieldResult('ch', 'ch_number', 'number');

        $hydrator = new AbstractEntityHydrationMock($this->entityManager, $rsm, $this->platform);
        $hydrator->columnInfo = [
            's_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            's_name' => ['isIdentifier' => false, 'type' => Type::getType('string')],
            'ch_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            'ch_number' => ['isIdentifier' => false, 'type' => Type::getType('string')],
        ];

        $rows = [
            [
                's_id' => 1,
                's_name' => 'Supermarket',
                'ch_id' => 100,
                'ch_number' => 'CH-001',
            ],
            [
                's_id' => 1,
                's_name' => 'Supermarket',
                'ch_id' => 101,
                'ch_number' => 'CH-002',
            ],
        ];

        $result = [];
        foreach ($rows as $row) {
            $hydrator->publicHydrateRowData($row, $result);
        }

        $this->assertCount(1, $result);

        /** @var Shop $shop */
        $shop = reset($result);

        $this->assertSame(1, $shop->getId());
        $this->assertSame('Supermarket', $shop->getName());
        $this->assertCount(2, $shop->getCheques());

        $cheques = $shop->getCheques()->toArray();
        $this->assertSame(100, $cheques[0]->getId());
        $this->assertSame('CH-001', $cheques[0]->getNumber());
        $this->assertInstanceOf(ArrayCollection::class, $cheques[0]->getItems());
        $this->assertTrue($cheques[0]->getItems()->isEmpty());

        $this->assertSame(101, $cheques[1]->getId());
        $this->assertSame('CH-002', $cheques[1]->getNumber());
        $this->assertInstanceOf(ArrayCollection::class, $cheques[1]->getItems());
        $this->assertTrue($cheques[1]->getItems()->isEmpty());
    }

    public function testHydrateRowDataWithNestedToManyJoin(): void
    {
        new ConfigurationService(isEnableEntityRuntimeNameDTO: false);

        $rsm = new ResultSetMapping;
        $rsm->addEntityResult(Shop::class, 's');
        $rsm->addFieldResult('s', 's_id', 'id');
        $rsm->addFieldResult('s', 's_name', 'name');

        $rsm->addJoinedEntityResult(Cheque::class, 'ch', 's', 'cheques');
        $rsm->addFieldResult('ch', 'ch_id', 'id');
        $rsm->addFieldResult('ch', 'ch_number', 'number');

        $rsm->addJoinedEntityResult(ChequeItem::class, 'ci', 'ch', 'items');
        $rsm->addFieldResult('ci', 'ci_id', 'id');
        $rsm->addFieldResult('ci', 'ci_title', 'title');

        $hydrator = new AbstractEntityHydrationMock($this->entityManager, $rsm, $this->platform);
        $hydrator->columnInfo = [
            's_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            's_name' => ['isIdentifier' => false, 'type' => Type::getType('string')],
            'ch_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            'ch_number' => ['isIdentifier' => false, 'type' => Type::getType('string')],
            'ci_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            'ci_title' => ['isIdentifier' => false, 'type' => Type::getType('string')],
        ];

        $row = [
            's_id' => 1,
            's_name' => 'Supermarket',
            'ch_id' => 100,
            'ch_number' => 'CH-001',
            'ci_id' => 1_000,
            'ci_title' => 'Milk',
        ];

        $result = [];
        $hydrator->publicHydrateRowData($row, $result);

        $this->assertCount(1, $result);

        /** @var Shop $shop */
        $shop = reset($result);

        $this->assertCount(1, $shop->getCheques());

        /** @var Cheque $cheque */
        $cheque = $shop->getCheques()->first();
        $this->assertSame(100, $cheque->getId());
        $this->assertSame('CH-001', $cheque->getNumber());

        $this->assertCount(1, $cheque->getItems());
        /** @var ChequeItem $item */
        $item = $cheque->getItems()->first();
        $this->assertSame(1_000, $item->getId());
        $this->assertSame('Milk', $item->getTitle());
    }

    public function testSharedChildIsAddedToCollectionOfEveryParent(): void
    {
        $rsm = $this->createShopChequeItemResultSetMapping();
        $hydrator = $this->createShopChequeItemHydrator($rsm);

        $rows = [
            ['s_id' => 1, 's_name' => 'Shop 1', 'ch_id' => 100, 'ch_number' => 'CH-100', 'ci_id' => null, 'ci_title' => null],
            ['s_id' => 2, 's_name' => 'Shop 2', 'ch_id' => 100, 'ch_number' => 'CH-100', 'ci_id' => null, 'ci_title' => null],
        ];

        $result = [];
        foreach ($rows as $row) {
            $hydrator->publicHydrateRowData($row, $result);
        }

        $this->assertCount(2, $result);

        /** @var Shop[] $shops */
        $shops = array_values($result);
        $this->assertCount(1, $shops[0]->getCheques());
        $this->assertCount(1, $shops[1]->getCheques());

        foreach ($shops as $shop) {
            $cheque = $shop->getCheques()->first();
            $this->assertInstanceOf(Cheque::class, $cheque);
            $this->assertSame(100, $cheque->getId());
        }
    }

    public function testNestedChildrenOfAlreadySeenChildAreHydrated(): void
    {
        $rsm = $this->createShopChequeItemResultSetMapping();
        $hydrator = $this->createShopChequeItemHydrator($rsm);

        $rows = [
            ['s_id' => 1, 's_name' => 'Shop 1', 'ch_id' => 100, 'ch_number' => 'CH-100', 'ci_id' => 1_000, 'ci_title' => 'Milk'],
            ['s_id' => 1, 's_name' => 'Shop 1', 'ch_id' => 100, 'ch_number' => 'CH-100', 'ci_id' => 1_001, 'ci_title' => 'Bread'],
        ];

        $result = [];
        foreach ($rows as $row) {
            $hydrator->publicHydrateRowData($row, $result);
        }

        /** @var Shop $shop */
        $shop = reset($result);
        $this->assertCount(1, $shop->getCheques());

        /** @var Cheque $cheque */
        $cheque = $shop->getCheques()->first();
        $this->assertCount(2, $cheque->getItems());
        $this->assertSame(['Milk', 'Bread'], $cheque->getItems()->map(static fn (ChequeItem $item): string => $item->getTitle())->getValues());
    }

    public function testToIterableAddsSharedChildToEveryParent(): void
    {
        $rsm = $this->createShopChequeItemResultSetMapping();
        $hydrator = $this->createShopChequeItemHydrator($rsm);

        $columns = ['s_id', 's_name', 'ch_id', 'ch_number', 'ci_id', 'ci_title'];
        $rows = [
            [1, 'Shop 1', 100, 'CH-100', null, null],
            [2, 'Shop 2', 100, 'CH-100', null, null],
            [3, 'Shop 3', 100, 'CH-100', null, null],
        ];

        $statement = new Result(new ArrayResult($columns, $rows), $this->entityManager->getConnection());

        $chequeCounts = [];
        /** @var Shop $shop */
        foreach ($hydrator->toIterable($statement, $rsm) as $shop) {
            $chequeCounts[$shop->getId()] = $shop->getCheques()->count();
        }

        $this->assertSame([1 => 1, 2 => 1, 3 => 1], $chequeCounts);
    }

    private function createShopChequeItemResultSetMapping(): ResultSetMapping
    {
        $rsm = new ResultSetMapping;
        $rsm->addEntityResult(Shop::class, 's');
        $rsm->addFieldResult('s', 's_id', 'id');
        $rsm->addFieldResult('s', 's_name', 'name');

        $rsm->addJoinedEntityResult(Cheque::class, 'ch', 's', 'cheques');
        $rsm->addFieldResult('ch', 'ch_id', 'id');
        $rsm->addFieldResult('ch', 'ch_number', 'number');

        $rsm->addJoinedEntityResult(ChequeItem::class, 'ci', 'ch', 'items');
        $rsm->addFieldResult('ci', 'ci_id', 'id');
        $rsm->addFieldResult('ci', 'ci_title', 'title');

        return $rsm;
    }

    private function createShopChequeItemHydrator(ResultSetMapping $rsm): AbstractEntityHydrationMock
    {
        $hydrator = new AbstractEntityHydrationMock($this->entityManager, $rsm, $this->platform);
        $hydrator->columnInfo = [
            's_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            's_name' => ['isIdentifier' => false, 'type' => Type::getType('string')],
            'ch_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            'ch_number' => ['isIdentifier' => false, 'type' => Type::getType('string')],
            'ci_id' => ['isIdentifier' => true, 'type' => Type::getType('integer')],
            'ci_title' => ['isIdentifier' => false, 'type' => Type::getType('string')],
        ];

        return $hydrator;
    }
}
