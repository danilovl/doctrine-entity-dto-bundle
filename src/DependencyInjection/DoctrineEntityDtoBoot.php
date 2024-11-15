<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\DependencyInjection;

use Danilovl\DoctrineEntityDtoBundle\Attribute\AsEntityDTO;
use Danilovl\DoctrineEntityDtoBundle\Hydration\Entity\EntityHydration;
use Danilovl\DoctrineEntityDtoBundle\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DoctrineEntityDtoBoot
{
    public function boot(ContainerInterface $container): void
    {
        /** @var ConfigurationService $configurationService */
        $configurationService = $container->get(ConfigurationService::class);
        if (!$configurationService->isEnableEntityDTO) {
            return;
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.default_entity_manager');

        $entityClasses = $this->getEntityClasses($configurationService, $entityManager);

        foreach ($entityClasses as $className) {
            if ($configurationService->isAsEntityDTO) {
                $attributes = (new ReflectionClass($className))->getAttributes(AsEntityDTO::class);
                $attribute = $attributes[0] ?? null;

                if ($attribute === null) {
                    continue;
                }
            }

            $entityManager->getConfiguration()->addCustomHydrationMode(
                $className,
                EntityHydration::class
            );
        }
    }

    private function getEntityClasses(
        ConfigurationService $configurationService,
        EntityManagerInterface $entityManager
    ): array {
        if (!empty($configurationService->entityDTO)) {
            return $configurationService->entityDTO;
        }

        $metadataFactory = $entityManager->getMetadataFactory();
        /** @var object[] $metadata */
        $metadata = $metadataFactory->getAllMetadata();

        $result = [];

        foreach ($metadata as $classMetadata) {
            if (!$classMetadata instanceof ClassMetadata) {
                continue;
            }
            $result[] = $classMetadata->getName();
        }

        return $result;
    }
}
