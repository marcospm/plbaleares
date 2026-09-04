<?php

namespace App\Repository;

use App\Entity\Ley;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<Ley>
 */
class LeyRepository extends ServiceEntityRepository
{
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache = null)
    {
        parent::__construct($registry, Ley::class);
        $this->cache = $cache;
    }

    /**
     * Obtiene todas las leyes ordenadas por nombre (con cache)
     * @return Ley[]
     */
    public function findAllOrderedByNombre(): array
    {
        $cacheKey = 'leyes_all_ordered_nombre';
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $leyes = $this->findBy([], ['nombre' => 'ASC']);

        if ($this->cache) {
            $cacheItem->set($leyes);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $leyes;
    }

    /**
     * Obtiene todas las leyes activas ordenadas por nombre (con cache)
     * @return Ley[]
     */
    public function findActivasOrderedByNombre(): array
    {
        $cacheKey = 'leyes_activas_ordered_nombre';
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $leyes = $this->findBy(['activo' => true], ['nombre' => 'ASC']);

        if ($this->cache) {
            $cacheItem->set($leyes);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $leyes;
    }

    /**
     * Limpia el cache de leyes (llamar después de crear/editar/eliminar leyes)
     */
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->deleteItems(['leyes_all_ordered_nombre', 'leyes_activas_ordered_nombre']);
        }
    }

    /**
     * Encuentra leyes activas con formato de fecha en el nombre.
     * @param int[]|null $temaIds Si se indica, solo leyes de esos temas
     * @return Ley[]
     */
    public function findLeyesConFormatoFecha(?array $temaIds = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.activo = :activo')
            ->andWhere('l.nombre != :nombreLeyExcluida')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
            ->orderBy('l.nombre', 'ASC');

        if ($temaIds !== null && $temaIds !== []) {
            $qb->innerJoin('l.temas', 't')
                ->andWhere('t.id IN (:temaIds)')
                ->setParameter('temaIds', $temaIds);
        }

        $leyes = $qb->getQuery()->getResult();

        $patron = '/\d+\/\d+,\s*de\s+\d+\s+de\s+\w+/i';

        return array_filter($leyes, function (Ley $ley) use ($patron) {
            $nombre = $ley->getNombre() ?? '';
            return preg_match($patron, $nombre) === 1;
        });
    }
}

