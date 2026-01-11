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
     * Encuentra todas las leyes activas que tienen el formato "número/número, de día de mes"
     * Ejemplo: "20/2006, de 15 de diciembre" o "Ley 20/2006, de 15 de diciembre"
     */
    public function findLeyesConFormatoFecha(): array
    {
        // Excluir ley "Accidentes de Tráfico"
        $qb = $this->createQueryBuilder('l')
            ->where('l.activo = :activo')
            ->andWhere('l.nombre != :nombreLeyExcluida')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
            ->orderBy('l.nombre', 'ASC');

        $leyes = $qb->getQuery()->getResult();
        
        // Filtrar leyes que coincidan con el patrón: número/número, de día de mes
        // Patrón más flexible: puede empezar con "Ley" o no, y permite espacios variables
        // Ejemplos: "20/2006, de 15 de diciembre", "Ley 20/2006, de 15 de diciembre"
        $patron = '/\d+\/\d+,\s*de\s+\d+\s+de\s+\w+/i';
        
        return array_filter($leyes, function(Ley $ley) use ($patron) {
            $nombre = $ley->getNombre() ?? '';
            return preg_match($patron, $nombre) === 1;
        });
    }
}

