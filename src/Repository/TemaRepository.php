<?php

namespace App\Repository;

use App\Entity\CategoriaOposicion;
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

        $temas = $this->findBy(['activo' => true, 'categoria' => CategoriaOposicion::POLICIA]);
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

        $temas = $this->findActivosDePolicia(['id' => 'ASC']);

        if ($this->cache) {
            $cacheItem->set($temas);
            $cacheItem->expiresAfter(3600); // 1 hora
            $this->cache->save($cacheItem);
        }

        return $temas;
    }

    /**
     * Temas activos de Policía. Es el listado que usan exámenes, preguntas y juegos.
     *
     * @param array<string, string> $orderBy
     * @return Tema[]
     */
    public function findActivosDePolicia(array $orderBy = ['id' => 'ASC']): array
    {
        return $this->findBy(
            ['activo' => true, 'categoria' => CategoriaOposicion::POLICIA],
            $orderBy
        );
    }

    /**
     * Temas activos de una categoría, en orden natural por nombre.
     *
     * @return Tema[]
     */
    public function findActivosByCategoria(CategoriaOposicion $categoria): array
    {
        $cacheKey = 'temas_activos_categoria_' . $categoria->value;

        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        $temas = $this->findBy(['activo' => true, 'categoria' => $categoria]);
        usort($temas, static fn (Tema $a, Tema $b): int => strnatcasecmp((string) $a->getNombre(), (string) $b->getNombre()));

        if ($this->cache) {
            $cacheItem->set($temas);
            $cacheItem->expiresAfter(3600);
            $this->cache->save($cacheItem);
        }

        return $temas;
    }

    public function clearCache(): void
    {
        if (!$this->cache) {
            return;
        }

        $keys = [
            'temas_activos_ordered_nombre',
            'temas_activos_ordered_nombre_natural',
            'temas_activos_ordered_id',
        ];
        foreach (CategoriaOposicion::cases() as $categoria) {
            $keys[] = 'temas_activos_categoria_' . $categoria->value;
        }
        $this->cache->deleteItems($keys);
    }
}

