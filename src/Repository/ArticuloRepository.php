<?php

namespace App\Repository;

use App\Entity\Articulo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Articulo>
 */
class ArticuloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Articulo::class);
    }

    /**
     * Obtiene artículos activos ordenados por número
     * 
     * @param int|null $leyId ID de la ley para filtrar (opcional)
     * @param string|null $search Término de búsqueda (opcional)
     * @return Articulo[]
     */
    public function findActivosOrdenadosPorNumero(?int $leyId = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->setParameter('activo', true);

        if ($leyId !== null && $leyId > 0) {
            $qb->andWhere('l.id = :leyId')
               ->setParameter('leyId', $leyId);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('a.numero', ':search'),
                    $qb->expr()->like('a.nombre', ':search'),
                    $qb->expr()->like('a.explicacion', ':search'),
                    $qb->expr()->like('l.nombre', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }

        // Obtener resultados
        $articulos = $qb->getQuery()->getResult();
        
        // Ordenar por número usando comparación natural (maneja "1", "2", "10" correctamente)
        // También maneja casos como "1.1", "1.2", "10.1", etc.
        usort($articulos, function($a, $b) {
            $numA = $a->getNumero() ?? '';
            $numB = $b->getNumero() ?? '';
            
            // Usar strnatcmp para ordenamiento natural (numérico cuando sea posible)
            return strnatcmp($numA, $numB);
        });

        return $articulos;
    }
}

