<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\Service;

use Psr\Cache\CacheItemPoolInterface;

class CacheService
{
    public function __construct(public readonly CacheItemPoolInterface $cache) {}
}
