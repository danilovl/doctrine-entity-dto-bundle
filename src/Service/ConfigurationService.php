<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Service;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ConfigurationService extends Bundle
{
    private static array $scalarDTOs = [];

    public function __construct(
        public readonly bool $isEnableEntityDTO = false,
        public readonly bool $isAsEntityDTO = false,
        public readonly array $entityDTO = [],
        public readonly bool $isEnableScalarDTO = false,
        public readonly array $scalarDTO = []
    ) {
        self::$scalarDTOs = $scalarDTO;
    }

    public static function getscalarDTO(): array
    {
        return self::$scalarDTOs;
    }
}
