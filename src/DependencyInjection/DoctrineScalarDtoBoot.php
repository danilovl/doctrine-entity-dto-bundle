<?php declare(strict_types=1);

namespace Danilovl\DoctrineEntityDtoBundle\DependencyInjection;

use Danilovl\DoctrineEntityDtoBundle\Attribute\AsScalarDTO;
use Danilovl\DoctrineEntityDtoBundle\Hydration\Scalar\ScalarHydration;
use Danilovl\DoctrineEntityDtoBundle\Service\{
    CacheService,
    ConfigurationService
};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

class DoctrineScalarDtoBoot
{
    public function boot(ContainerInterface $container): void
    {
        /** @var ConfigurationService $configurationService */
        $configurationService = $container->get(ConfigurationService::class);
        if (!$configurationService->isEnableScalarDTO) {
            return;
        }

        $scalarDTO = [];
        if ($configurationService->isAsScalarDTO) {
            $scalarDTO = $this->getIsAsScalarDTO($container);
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.default_entity_manager');

        $scalarDTO = array_merge($scalarDTO, $configurationService->scalarDTO);

        foreach ($scalarDTO as $dto) {
            $entityManager->getConfiguration()->addCustomHydrationMode($dto, ScalarHydration::class);
        }

        ConfigurationService::setScalarDTOs($scalarDTO);
    }

    private function processDirectory(ContainerInterface $container): array
    {
        /** @var string $kernelProjectDir */
        $kernelProjectDir = $container->getParameter('kernel.project_dir');
        $kernelProjectDir = sprintf('%s/src', $kernelProjectDir);

        $result = [];

        $finder = new Finder;
        $finder->files()
            ->in($kernelProjectDir)
            ->name('*.php');

        foreach ($finder as $file) {
            $pathname = $file->getPathname();
            $fileContent = file_get_contents($pathname);

            if (!$fileContent || !str_contains($fileContent, AsScalarDTO::class)) {
                continue;
            }

            $namespace = $this->getNamespaceFromFile($fileContent);
            if (!$namespace) {
                continue;
            }

            $namespace = sprintf('%s\\%s', $namespace, $file->getBasename('.php'));
            $result[] = $namespace;
        }

        return $result;
    }

    private function getNamespaceFromFile(string $content): ?string
    {
        if (preg_match('~namespace\s+(.*?);~i', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getIsAsScalarDTO(ContainerInterface $container): array
    {
        $environment = $container->getParameter('kernel.environment');

        if ($environment !== 'prod') {
            return $this->processDirectory($container);
        }

        /** @var CacheService $cacheService */
        $cacheService = $container->get(CacheService::class);
        $cache = $cacheService->cache;
        $scalarDTOItem = $cache->getItem($this->getCacheKey());

        if ($scalarDTOItem->isHit()) {
            /** @var array $cacheItem */
            $cacheItem = $scalarDTOItem->get();

            return $cacheItem;
        }

        $scalarDTO = $this->processDirectory($container);

        $scalarDTOItem->set($scalarDTO);
        $cache->save($scalarDTOItem);

        return $scalarDTO;
    }

    private function getCacheKey(): string
    {
        return 'DoctrineEntityDtoBundle:scalarDTO';
    }
}
