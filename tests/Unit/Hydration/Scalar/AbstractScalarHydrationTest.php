<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Unit\Hydration\Scalar;

use ArgumentCountError;
use Danilovl\DoctrineEntityDtoBundle\Exception\LogicException;
use Danilovl\DoctrineEntityDtoBundle\Service\ConfigurationService;
use Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Scalar\{
    ScalarDTOMock,
    AbstractScalarHydrationMock
};
use DateTime;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM\Query\ResultSetMapping;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class AbstractScalarHydrationTest extends TestCase
{
    private ResultSetMapping $resultSetMapping;
    private AbstractScalarHydrationMock $abstractScalarHydrationMock;

    protected function setUp(): void
    {
        ConfigurationService::setScalarDTO([ScalarDTOMock::class]);

        $this->resultSetMapping = new ResultSetMapping;
        $this->resultSetMapping->aliasMap = [
            'task' => ScalarDTOMock::class
        ];

        $this->resultSetMapping->fieldMappings = [
            'complete_0' => 'complete',
            'notify_complete_1' => 'notifyComplete',
            'deadline_2' => 'deadline',
            'id_3' => 'id',
            'name_4' => 'name',
            'description_5' => 'description',
            'active_6' => 'active',
            'created_at_7' => 'createdAt',
            'updated_at_8' => 'updatedAt'
        ];
        $this->resultSetMapping->entityMappings = [
            'task' => null
        ];

        $this->resultSetMapping->columnOwnerMap = [
            'complete_0' => 'task',
            'notify_complete_1' => 'task',
            'deadline_2' => 'task',
            'id_3' => 'task',
            'name_4' => 'task',
            'description_5' => 'task',
            'active_6' => 'task',
            'created_at_7' => 'task',
            'updated_at_8' => 'task'
        ];

        $this->resultSetMapping->declaringClasses = [
            'complete_0' => ScalarDTOMock::class,
            'notify_complete_1' => ScalarDTOMock::class,
            'deadline_2' => ScalarDTOMock::class,
            'id_3' => ScalarDTOMock::class,
            'name_4' => ScalarDTOMock::class,
            'description_5' => ScalarDTOMock::class,
            'active_6' => ScalarDTOMock::class,
            'created_at_7' => ScalarDTOMock::class,
            'updated_at_8' => ScalarDTOMock::class
        ];

        $platform = new MySQL80Platform;
        $this->abstractScalarHydrationMock = new AbstractScalarHydrationMock($this->resultSetMapping, $platform);
    }

    public function testExceptionDTOIsNotSet(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DTO class is not set.');

        $result = [];
        $this->abstractScalarHydrationMock->publicHydrateRowData([], $result);
    }

    public function testIsNotInScalarDTOs(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DTO class is not in scalar DTOs.');

        $this->abstractScalarHydrationMock::$dtoClass = stdClass::class;

        $result = [];
        $this->abstractScalarHydrationMock->publicHydrateRowData([], $result);
    }

    #[DataProvider('dataExceptionAlreadyExistsField')]
    public function testExceptionAlreadyExistsField(array $row, string $fieldName): void
    {
        $this->expectException(LogicException::class);

        $exceptionMessage = sprintf('Already exists field "%s" in result. Field name must be unique. Use AS operator', $fieldName);
        $this->expectExceptionMessage($exceptionMessage);

        $this->abstractScalarHydrationMock::$dtoClass = ScalarDTOMock::class;

        $result = [];
        $this->abstractScalarHydrationMock->publicHydrateRowData($row, $result);
    }

    #[DataProvider('dataHydrateRowDataSucceed')]
    public function testHydrateRowDataSucceed(array $row, ScalarDTOMock $expectedDTO): void
    {
        $result = [];

        $this->abstractScalarHydrationMock::$dtoClass = ScalarDTOMock::class;
        $this->abstractScalarHydrationMock->publicHydrateRowData($row, $result);

        $expectedResult = [$expectedDTO];

        $this->assertEquals($expectedResult, $result);
    }

    #[DataProvider('dataHydrateRowDataFailed')]
    public function testHydrateRowDataFailed(array $row, ScalarDTOMock $expectedDTO): void
    {
        $this->expectException(ArgumentCountError::class);

        $result = [];

        $this->abstractScalarHydrationMock::$dtoClass = ScalarDTOMock::class;
        $this->abstractScalarHydrationMock->publicHydrateRowData($row, $result);

        $expectedResult = [$expectedDTO];

        $this->assertEquals($expectedResult, $result);
    }

    public static function dataHydrateRowDataSucceed(): Generator
    {
        $scalarDTOMock = new ScalarDTOMock(59, true, 'Simple name', 'Simple description', false, false, new DateTime('2018-04-28'));

        yield [[
            'complete_0' => 0,
            'notify_complete_1' => 0,
            'deadline_2' => '2018-04-28',
            'id_3' => 59,
            'name_4' => 'Simple name',
            'description_5' => 'Simple description',
            'active_6' => 1,
            'created_at_7' => '2018-04-07 19:09:17',
            'updated_at_8' => '2019-03-06 20:12:48'
        ], $scalarDTOMock];

        $scalarDTOMock = new ScalarDTOMock(59, true, 'name', 'description', true, true, new DateTime('2018-04-28'));

        yield [[
            'complete_0' => 1,
            'notify_complete_1' => 1,
            'deadline_2' => '2018-04-28',
            'id_3' => 59,
            'name_4' => 'name',
            'description_5' => 'description',
            'active_6' => 1,
            'created_at_7' => '2018-04-07 19:09:17',
            'updated_at_8' => '2019-03-06 20:12:48'
        ], $scalarDTOMock];
    }

    public static function dataHydrateRowDataFailed(): Generator
    {
        $scalarDTOMock = new ScalarDTOMock(1, true, 'Simple name', 'Simple description', true, true, new DateTime('2018-04-28'));

        yield [[
            'complete_0' => 0,
            'notify_complete_1' => 0,
            'deadline_2' => '2018-04-28',
            'id_3' => 59,
        ], $scalarDTOMock];

        $scalarDTOMock = new ScalarDTOMock(2, true, 'name', 'description', true, true, new DateTime('2018-04-28'));

        yield [[
            'description_5' => 'description',
            'active_6' => 1,
            'created_at_7' => '2018-04-07 19:09:17',
            'updated_at_8' => '2019-03-06 20:12:48'
        ], $scalarDTOMock];

        yield [[
            'complete_0' => 1,
            'notify_complete_1' => 1,
            'deadline_2' => '2018-04-28',
            'name_4' => 'name',
            'description_5' => 'description',
            'active_6' => 1,
            'created_at_7' => '2018-04-07 19:09:17',
            'updated_at_8' => '2019-03-06 20:12:48'
        ], $scalarDTOMock];
    }

    public static function dataExceptionAlreadyExistsField(): Generator
    {
        yield [[
            'complete_0' => 0,
            'notify_complete_1' => 0,
            'deadline_2' => '2018-04-28',
            'id_3' => 59,
            'id_4' => 59,
        ], 'id'];

        yield [[
            'description_5' => 'description',
            'active_6' => 1,
            'active_7' => 1,
            'created_at_7' => '2018-04-07 19:09:17',
            'updated_at_8' => '2019-03-06 20:12:48'
        ], 'active'];

        yield [[
            'complete_0' => 1,
            'notify_complete_1' => 1,
            'deadline_2' => '2018-04-28',
            'name_4' => 'name',
            'description_5' => 'description',
            'active_6' => 1,
            'created_at_7' => '2018-04-07 19:09:17',
            'updated_at_8' => '2019-03-06 20:12:48',
            'updated_at_9' => '2019-03-06 20:12:48'
        ], 'updatedAt'];
    }
}
