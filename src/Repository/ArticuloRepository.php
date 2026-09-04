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
     * @return Articulo[]
     */
    public function findActivosByLey(int $leyId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.ley = :leyId')
            ->andWhere('a.activo = :activo')
            ->setParameter('leyId', $leyId)
            ->setParameter('activo', true)
            ->orderBy('a.numero', 'ASC')
            ->addOrderBy('a.sufijo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPreguntasAsociadas(Articulo $articulo): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(\App\Entity\Pregunta::class, 'p')
            ->where('p.articulo = :articulo')
            ->setParameter('articulo', $articulo)
            ->getQuery()
            ->getSingleScalarResult();
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
     * Obtiene artículos activos ordenados por número con paginación SQL
     * 
     * @param int|null $leyId ID de la ley para filtrar (opcional)
     * @param string|null $search Término de búsqueda (opcional)
     * @param int $offset Offset para paginación
     * @param int $limit Límite de resultados
     * @return Articulo[]
     */
    public function findActivosOrdenadosPorNumeroPaginated(?int $leyId = null, ?string $search = null, int $offset = 0, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('a.numero', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

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
     * Cuenta artículos activos sin cargar las entidades
     * 
     * @param int|null $leyId ID de la ley para filtrar (opcional)
     * @param string|null $search Término de búsqueda (opcional)
     * @return int
     */
    public function countActivosOrdenadosPorNumero(?int $leyId = null, ?string $search = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.ley', 'l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->setParameter('activo', true);

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

        return (int) $qb->getQuery()->getSingleScalarResult();
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
     * Busca artículos con filtros avanzados con paginación SQL
     * 
     * @param int|null $leyId ID de la ley para filtrar (opcional)
     * @param string|null $search Término de búsqueda general (opcional)
     * @param string|null $numero Número específico del artículo para filtrar (opcional)
     * @param bool|null $activo Filtrar por estado activo/inactivo (opcional, null = todos)
     * @param int $offset Offset para paginación
     * @param int $limit Límite de resultados
     * @return Articulo[]
     */
    public function buscarConFiltrosPaginated(?int $leyId = null, ?string $search = null, ?string $numero = null, ?bool $activo = null, int $offset = 0, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->orderBy('a.numero', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

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

        return $qb->getQuery()->getResult();
    }

    /**
     * Cuenta artículos con filtros avanzados sin cargar las entidades
     * 
     * @param int|null $leyId ID de la ley para filtrar (opcional)
     * @param string|null $search Término de búsqueda general (opcional)
     * @param string|null $numero Número específico del artículo para filtrar (opcional)
     * @param bool|null $activo Filtrar por estado activo/inactivo (opcional, null = todos)
     * @return int
     */
    public function countConFiltros(?int $leyId = null, ?string $search = null, ?string $numero = null, ?bool $activo = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.ley', 'l');

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

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Filtra artículos cuya ley pertenece a alguno de los temas indicados.
     * @param int[] $temaIds
     */
    private function applyTemaFilter(\Doctrine\ORM\QueryBuilder $qb, ?array $temaIds, string $leyAlias = 'l'): void
    {
        if ($temaIds === null || $temaIds === []) {
            return;
        }

        $qb->innerJoin($leyAlias . '.temas', 'temaFiltro')
            ->andWhere('temaFiltro.id IN (:temaIds)')
            ->setParameter('temaIds', $temaIds);
    }

    /**
     * Distribuye artículos de forma round-robin entre leyes.
     * @param Articulo[] $articulos
     * @return Articulo[]
     */
    private function distribuirAleatoriosPorLey(array $articulos, int $limit): array
    {
        if ($articulos === []) {
            return [];
        }

        $articulosPorLey = [];
        foreach ($articulos as $articulo) {
            $leyId = $articulo->getLey()->getId();
            $articulosPorLey[$leyId][] = $articulo;
        }

        foreach ($articulosPorLey as $leyId => $articulosLey) {
            shuffle($articulosPorLey[$leyId]);
        }

        $resultado = [];
        $indicesPorLey = array_fill_keys(array_keys($articulosPorLey), 0);
        $leyesIds = array_keys($articulosPorLey);
        shuffle($leyesIds);

        while (count($resultado) < $limit) {
            $algunoAgregado = false;
            foreach ($leyesIds as $leyId) {
                if (count($resultado) >= $limit) {
                    break;
                }

                if ($indicesPorLey[$leyId] < count($articulosPorLey[$leyId])) {
                    $resultado[] = $articulosPorLey[$leyId][$indicesPorLey[$leyId]];
                    $indicesPorLey[$leyId]++;
                    $algunoAgregado = true;
                }
            }

            if (!$algunoAgregado) {
                break;
            }
        }

        shuffle($resultado);

        return $resultado;
    }

    /**
     * Obtiene artículos activos aleatorios con nombre y ley cargada.
     * @param int[]|null $temaIds Si se indica, solo leyes de esos temas
     * @return Articulo[]
     */
    public function findAleatoriosConNombre(int $limit = 20, ?array $temaIds = null): array
    {
        $subquery = $this->getEntityManager()->createQueryBuilder()
            ->select('l2.id')
            ->from('App\Entity\Ley', 'l2')
            ->where('l2.nombre = :nombreLeyExcluida')
            ->setMaxResults(1);

        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->addSelect('l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->andWhere('l.id != (' . $subquery->getDQL() . ')')
            ->andWhere('a.numero != :numeroExcluido')
            ->andWhere('a.nombre IS NOT NULL')
            ->andWhere('a.nombre != :vacio')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
            ->setParameter('numeroExcluido', 0)
            ->setParameter('vacio', '');

        $this->applyTemaFilter($qb, $temaIds);

        return $this->distribuirAleatoriosPorLey($qb->getQuery()->getResult(), $limit);
    }

    /**
     * Obtiene artículos activos aleatorios con textoLegal y ley cargada.
     * @param int[]|null $temaIds Si se indica, solo leyes de esos temas
     * @return Articulo[]
     */
    public function findAleatoriosConTextoLegal(int $limit = 20, ?array $temaIds = null): array
    {
        $subquery = $this->getEntityManager()->createQueryBuilder()
            ->select('l2.id')
            ->from('App\Entity\Ley', 'l2')
            ->where('l2.nombre = :nombreLeyExcluida')
            ->setMaxResults(1);

        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.ley', 'l')
            ->addSelect('l')
            ->where('a.activo = :activo')
            ->andWhere('l.activo = :activo')
            ->andWhere('l.id != (' . $subquery->getDQL() . ')')
            ->andWhere('a.numero != :numeroExcluido')
            ->andWhere('a.textoLegal IS NOT NULL')
            ->andWhere('a.textoLegal != :vacio')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
            ->setParameter('numeroExcluido', 0)
            ->setParameter('vacio', '');

        $this->applyTemaFilter($qb, $temaIds);

        return $this->distribuirAleatoriosPorLey($qb->getQuery()->getResult(), $limit);
    }

    /**
     * Obtiene artículos activos aleatorios con textoLegal para el juego "El artículo correcto".
     * @param int[]|null $temaIds Si se indica, solo leyes de esos temas
     * @return Articulo[]
     */
    public function findAleatoriosConTextoLegalParaJuego(int $limit = 20, ?array $temaIds = null): array
    {
        return $this->findAleatoriosConTextoLegal($limit, $temaIds);
    }
}

