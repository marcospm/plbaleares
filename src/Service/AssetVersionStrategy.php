<?php

namespace App\Service;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Estrategia de versionado de assets que cambia en cada despliegue
 */
class AssetVersionStrategy implements VersionStrategyInterface
{
    private AssetVersionService $versionService;

    public function __construct(AssetVersionService $versionService)
    {
        $this->versionService = $versionService;
    }

    public function getVersion(string $path): string
    {
        return $this->versionService->getVersion();
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'v=' . $version;
    }
}

