<?php

namespace App\Repository;

use App\Entity\Municipio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<Municipio>
 */
class MunicipioRepository extends ServiceEntityRepository
{
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache = null)
    {
        parent::__construct($registry, Municipio::class);
        $this->cache = $cache;
    }

    /**
     * Obtiene todos los municipios activos ordenados por nombre (con cache)
     * @return Municipio[] Returns an array of active Municipio objects
     */
    public function findActivos(): array
    {
        $cacheKey = 'municipios_activos_ordered_nombre';
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $municipios = $this->createQueryBuilder('m')
            ->andWhere('m.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('m.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        if ($this->cache) {
            $cacheItem->set($municipios);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $municipios;
    }

    /**
     * Limpia el cache de municipios (llamar después de crear/editar/eliminar municipios)
     */
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->deleteItem('municipios_activos_ordered_nombre');
        }
    }
}












