<?php

namespace App\Repository;

use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<TemaMunicipal>
 */
class TemaMunicipalRepository extends ServiceEntityRepository
{
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache = null)
    {
        parent::__construct($registry, TemaMunicipal::class);
        $this->cache = $cache;
    }

    /**
     * Obtiene temas municipales activos de un municipio (con cache por municipio)
     * @return TemaMunicipal[] Returns an array of TemaMunicipal objects for a municipio
     */
    public function findByMunicipio(Municipio $municipio): array
    {
        $cacheKey = 'temas_municipales_municipio_' . $municipio->getId();
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $temas = $this->createQueryBuilder('t')
            ->andWhere('t.municipio = :municipio')
            ->setParameter('municipio', $municipio)
            ->andWhere('t.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('t.nombre', 'ASC')
            ->getQuery()
            ->getResult();

        if ($this->cache) {
            $cacheItem->set($temas);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $temas;
    }

    /**
     * Limpia el cache de temas municipales para un municipio
     */
    public function clearCacheForMunicipio(Municipio $municipio): void
    {
        if ($this->cache) {
            $this->cache->deleteItem('temas_municipales_municipio_' . $municipio->getId());
        }
    }
}












