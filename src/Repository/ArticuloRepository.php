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
            ->setParameter('activo', true)
            ->orderBy('a.numero', 'ASC');

        if ($leyId !== null && $leyId > 0) {
            $qb->andWhere('l.id = :leyId')
               ->setParameter('leyId', $leyId);
        }

        if ($search !== null && $search !== '') {
            // Para búsqueda en número, convertir a CAST para buscar como texto
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('CAST(a.numero AS CHAR)', ':search'),
                    $qb->expr()->like('a.nombre', ':search'),
                    $qb->expr()->like('a.explicacion', ':search'),
                    $qb->expr()->like('l.nombre', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Busca artículos con filtros avanzados
     * 
     * @param int|null $leyId ID de la ley para filtrar (opcional)
     * @param string|null $search Término de búsqueda general (opcional)
     * @param string|null $numero Número específico del artículo para filtrar (opcional)
     * @param bool|null $activo Filtrar por estado activo/inactivo (opcional, null = todos)
     * @return Articulo[]
     */
    public function buscarConFiltros(?int $leyId = null, ?string $search = null, ?string $numero = null, ?bool $activo = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->orderBy('a.numero', 'ASC');

        // Filtro por ley
        if ($leyId !== null && $leyId > 0) {
            $qb->andWhere('l.id = :leyId')
               ->setParameter('leyId', $leyId);
        }

        // Filtro por número específico (ahora integer)
        if ($numero !== null && $numero !== '') {
            // Convertir a integer para búsqueda numérica
            $numeroInt = is_numeric($numero) ? (int)$numero : null;
            if ($numeroInt !== null) {
                $qb->andWhere('a.numero = :numero')
                   ->setParameter('numero', $numeroInt);
            }
        }

        // Filtro por estado activo/inactivo
        if ($activo !== null) {
            $qb->andWhere('a.activo = :activo')
               ->setParameter('activo', $activo);
        }

        // Filtro de búsqueda general (busca en número, nombre, explicación y nombre de ley)
        if ($search !== null && $search !== '') {
            // Para búsqueda en número, convertir a CAST para buscar como texto
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('CAST(a.numero AS CHAR)', ':search'),
                    $qb->expr()->like('a.nombre', ':search'),
                    $qb->expr()->like('a.explicacion', ':search'),
                    $qb->expr()->like('l.nombre', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }

        // Ordenar por número (ahora es numérico, el ordenBy ya está en la línea 80)
        return $qb->getQuery()->getResult();
    }
}

