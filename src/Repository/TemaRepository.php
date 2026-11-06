<?php

namespace App\Repository;

use App\Entity\Tema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<Tema>
 */
class TemaRepository extends ServiceEntityRepository
{
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache = null)
    {
        parent::__construct($registry, Tema::class);
        $this->cache = $cache;
    }

    /**
     * Temas activos en orden natural: Tema 1, Tema 2, Tema 10.
     * @return Tema[]
     */
    public function findActivosOrderedByNombre(): array
    {
        $cacheKey = 'temas_activos_ordered_nombre_natural';
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $temas = $this->findBy(['activo' => true]);
        usort($temas, static fn (Tema $a, Tema $b): int => strnatcasecmp((string) $a->getNombre(), (string) $b->getNombre()));

        if ($this->cache) {
            $cacheItem->set($temas);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $temas;
    }

    /**
     * Obtiene todos los temas activos ordenados por ID (con cache)
     * @return Tema[]
     */
    public function findActivosOrderedById(): array
    {
        $cacheKey = 'temas_activos_ordered_id';
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $temas = $this->findBy(['activo' => true], ['id' => 'ASC']);

        if ($this->cache) {
            $cacheItem->set($temas);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $temas;
    }

    /**
     * Limpia el cache de temas (llamar después de crear/editar/eliminar temas)
     */
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->deleteItems(['temas_activos_ordered_nombre', 'temas_activos_ordered_id']);
        }
    }
}

