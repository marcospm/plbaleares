<?php

namespace App\Repository;

use App\Entity\Convocatoria;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<Convocatoria>
 */
class ConvocatoriaRepository extends ServiceEntityRepository
{
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache = null)
    {
        parent::__construct($registry, Convocatoria::class);
        $this->cache = $cache;
    }

    /**
     * Obtiene todas las convocatorias activas (con cache)
     * @return Convocatoria[]
     */
    public function findActivas(): array
    {
        $cacheKey = 'convocatorias_activas_ordered_creacion_desc';
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $convocatorias = $this->createQueryBuilder('c')
            ->where('c.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('c.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();

        if ($this->cache) {
            $cacheItem->set($convocatorias);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $convocatorias;
    }

    /**
     * Obtiene todas las convocatorias activas ordenadas por nombre (con cache)
     * @return Convocatoria[]
     */
    public function findActivasOrderedByNombre(): array
    {
        $cacheKey = 'convocatorias_activas_ordered_nombre_asc';
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $convocatorias = $this->findBy(['activo' => true], ['nombre' => 'ASC']);

        if ($this->cache) {
            $cacheItem->set($convocatorias);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $convocatorias;
    }

    /**
     * Limpia el cache de convocatorias (llamar después de crear/editar/eliminar convocatorias)
     */
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->deleteItems([
                'convocatorias_activas_ordered_creacion_desc',
                'convocatorias_activas_ordered_nombre_asc'
            ]);
        }
    }

    /**
     * Obtiene las convocatorias de un usuario específico
     * @return Convocatoria[]
     */
    public function findByUsuario(User $usuario): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.usuarios', 'u')
            ->where('u.id = :usuarioId')
            ->andWhere('c.activo = :activo')
            ->setParameter('usuarioId', $usuario->getId())
            ->setParameter('activo', true)
            ->orderBy('c.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}












