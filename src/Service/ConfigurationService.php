<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Service;

class ConfigurationService
{
    private static array $staticParameters = [];

    public function __construct(
        public readonly bool $isEnableEntityDTO = false,
        public readonly bool $isEnableEntityRuntimeNameDTO = false,
        public readonly bool $isAsEntityDTO = false,
        public readonly array $entityDTO = [],
        public readonly bool $isEnableScalarDTO = false,
        public readonly bool $isAsScalarDTO = false,
        public readonly array $scalarDTO = []
    ) {
        self::$staticParameters = [
            'isEnableEntityRuntimeNameDTO' => $isEnableEntityRuntimeNameDTO,
            'scalarDTO' => $isEnableEntityDTO
        ];
    }

    public static function getScalarDTO(): array
    {
        return self::$staticParameters['scalarDTO'];
    }

    public static function getIsEnableEntityRuntimeNameDTO(): bool
    {
        return self::$staticParameters['isEnableEntityRuntimeNameDTO'];
    }

    public static function setScalarDTO(array $scalarDTO): void
    {
        self::$staticParameters['scalarDTO'] = $scalarDTO;
    }
}
