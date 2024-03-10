<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Service;

class ConfigurationService
{
    private static array $scalarDTOs = [];

    public function __construct(
        public readonly bool $isEnableEntityDTO = false,
        public readonly bool $isAsEntityDTO = false,
        public readonly array $entityDTO = [],
        public readonly bool $isEnableScalarDTO = false,
        public readonly bool $isAsScalarDTO = false,
        public readonly array $scalarDTO = []
    ) {
        self::$scalarDTOs = $scalarDTO;
    }

    public static function getScalarDTO(): array
    {
        return self::$scalarDTOs;
    }

    public static function setScalarDTOs(array $scalarDTOs): void
    {
        self::$scalarDTOs = $scalarDTOs;
    }
}
