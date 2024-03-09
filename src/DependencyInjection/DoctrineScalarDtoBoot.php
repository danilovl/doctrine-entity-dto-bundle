<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\DependencyInjection;

use Danilovl\DoctrineEntityDtoBundle\Hydration\Scalar\ScalarHydration;
use Danilovl\DoctrineEntityDtoBundle\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DoctrineScalarDtoBoot
{
    public function boot(ContainerInterface $container): void
    {
        /** @var ConfigurationService $configurationService */
        $configurationService = $container->get(ConfigurationService::class);
        if (!$configurationService->isEnableScalarDTO) {
            return;
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.default_entity_manager');

        foreach ($configurationService->scalarDTO as $dto) {
            $entityManager->getConfiguration()->addCustomHydrationMode(
                $dto,
                ScalarHydration::class
            );
        }
    }
}
