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
            // Para búsqueda en número, usar CONCAT para convertir a texto
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('CONCAT(a.numero, \'\')', ':search'),
                    $qb->expr()->like('COALESCE(a.sufijo, \'\')', ':search'),
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

        // Filtro de búsqueda general (busca en número, sufijo, nombre, explicación y nombre de ley)
        if ($search !== null && $search !== '') {
            // Para búsqueda en número, usar CONCAT para convertir a texto
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('CONCAT(a.numero, \'\')', ':search'),
                    $qb->expr()->like('COALESCE(a.sufijo, \'\')', ':search'),
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

    /**
     * Obtiene 20 artículos activos aleatorios con nombre y ley cargada
     * Mezcla artículos de diferentes leyes
     * @return Articulo[]
     */
    public function findAleatoriosConNombre(int $limit = 20): array
    {
        // Obtener todos los artículos activos con nombre y ley cargada
        $articulos = $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->addSelect('l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->andWhere('a.nombre IS NOT NULL')
            ->andWhere('a.nombre != :vacio')
            ->setParameter('activo', true)
            ->setParameter('vacio', '')
            ->getQuery()
            ->getResult();

        if (empty($articulos)) {
            return [];
        }

        // Mezclar los artículos para asegurar variedad de leyes
        shuffle($articulos);

        // Si hay menos artículos que el límite, usar todos
        $limit = min($limit, count($articulos));

        // Retornar solo el número solicitado
        return array_slice($articulos, 0, $limit);
    }

    /**
     * Obtiene 20 artículos activos aleatorios con textoLegal y ley cargada
     * @return Articulo[]
     */
    public function findAleatoriosConTextoLegal(int $limit = 20): array
    {
        // Primero obtener todos los IDs de artículos activos con textoLegal
        $ids = $this->createQueryBuilder('a')
            ->select('a.id')
            ->innerJoin('a.ley', 'l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->andWhere('a.textoLegal IS NOT NULL')
            ->andWhere('a.textoLegal != :vacio')
            ->setParameter('activo', true)
            ->setParameter('vacio', '')
            ->getQuery()
            ->getScalarResult();

        if (empty($ids)) {
            return [];
        }

        // Convertir a array simple de IDs
        $idsArray = array_column($ids, 'id');

        // Mezclar aleatoriamente
        shuffle($idsArray);

        // Limitar la cantidad
        $limit = min($limit, count($idsArray));
        $idsSeleccionados = array_slice($idsArray, 0, $limit);

        // Si no hay IDs seleccionados, retornar array vacío
        if (empty($idsSeleccionados)) {
            return [];
        }

        // Obtener los artículos completos con ley cargada
        return $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->addSelect('l')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $idsSeleccionados)
            ->getQuery()
            ->getResult();
    }
}

