<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Tests\Mock\Hydration\Scalar;

use DateTime;

class ScalarDTOMock
{
    public function __construct(
        public readonly int $id,
        public readonly bool $active,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $complete,
        public readonly bool $notifyComplete,
        public readonly ?DateTime $deadline
    ) {}
}
