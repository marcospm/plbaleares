<?php

namespace App\Service;

/**
 * Servicio para generar versiones de assets que cambian en cada despliegue
 * Esto fuerza la actualización de caché en CDN/proxies como Upsun
 */
class AssetVersionService
{
    private ?string $version = null;

    public function getVersion(): string
    {
        if ($this->version !== null) {
            return $this->version;
        }

        // 1. Intentar usar variable de entorno APP_VERSION (puede ser configurada en Upsun)
        $envVersion = $_ENV['APP_VERSION'] ?? $_SERVER['APP_VERSION'] ?? null;
        if ($envVersion) {
            $this->version = $envVersion;
            return $this->version;
        }

        // 2. Intentar usar commit hash de Git (si está disponible)
        $gitHash = $this->getGitHash();
        if ($gitHash) {
            $this->version = substr($gitHash, 0, 8); // Primeros 8 caracteres
            return $this->version;
        }

        // 3. Usar timestamp del archivo de versión o del proyecto
        $this->version = $this->getTimestampVersion();
        return $this->version;
    }

    private function getGitHash(): ?string
    {
        // Intentar leer desde .git/HEAD o variable de entorno
        $gitHash = $_ENV['GIT_COMMIT'] ?? $_SERVER['GIT_COMMIT'] ?? null;
        if ($gitHash) {
            return $gitHash;
        }

        // Intentar leer desde archivo .git/HEAD (si existe)
        $gitHeadPath = __DIR__ . '/../../.git/HEAD';
        if (file_exists($gitHeadPath)) {
            $head = trim(file_get_contents($gitHeadPath));
            if (str_starts_with($head, 'ref: ')) {
                $refPath = __DIR__ . '/../../.git/' . substr($head, 5);
                if (file_exists($refPath)) {
                    return trim(file_get_contents($refPath));
                }
            } else {
                return $head;
            }
        }

        return null;
    }

    private function getTimestampVersion(): string
    {
        // Usar timestamp del último cambio en el directorio de assets
        $assetsPath = __DIR__ . '/../../assets';
        if (is_dir($assetsPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($assetsPath),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            $latestTime = 0;
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $mtime = $file->getMTime();
                    if ($mtime > $latestTime) {
                        $latestTime = $mtime;
                    }
                }
            }
            
            if ($latestTime > 0) {
                return (string) $latestTime;
            }
        }

        // Fallback: usar timestamp actual
        return (string) time();
    }
}

