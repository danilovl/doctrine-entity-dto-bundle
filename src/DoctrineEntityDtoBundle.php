<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle;

use Danilovl\DoctrineEntityDtoBundle\DependencyInjection\{
    DoctrineEntityDtoBoot,
    DoctrineScalarDtoBoot
};
use Danilovl\DoctrineEntityDtoBundle\DependencyInjection\Extension\DoctrineEntityDtoExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DoctrineEntityDtoBundle extends Bundle
{
    public function getContainerExtension(): DoctrineEntityDtoExtension
    {
        return new DoctrineEntityDtoExtension;
    }

    public function boot(): void
    {
        /** @var ContainerInterface $container */
        $container = $this->container;

        (new DoctrineEntityDtoBoot)->boot($container);
        (new DoctrineScalarDtoBoot)->boot($container);
    }
}
