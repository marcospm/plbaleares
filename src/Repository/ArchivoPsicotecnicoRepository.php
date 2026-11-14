<?php

namespace App\Repository;

use App\Entity\ArchivoPsicotecnico;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArchivoPsicotecnico>
 */
class ArchivoPsicotecnicoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArchivoPsicotecnico::class);
    }

    /**
     * @return list<ArchivoPsicotecnico>
     */
    public function findParaAdmin(?int $categoriaId = null, ?string $search = null): array
    {
        $ids = $this->buscarIds($categoriaId, $search, false);
        if ($ids === []) {
            return [];
        }

        return $this->cargarPorIds($ids);
    }

    /**
     * Archivos que tienen al menos una categoría activa.
     *
     * @return list<ArchivoPsicotecnico>
     */
    public function findVisibles(?int $categoriaId = null, ?string $search = null): array
    {
        $ids = $this->buscarIds($categoriaId, $search, true);
        if ($ids === []) {
            return [];
        }

        return $this->cargarPorIds($ids);
    }

    /**
     * @return list<int>
     */
    private function buscarIds(?int $categoriaId, ?string $search, bool $soloCategoriasActivas): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.id AS id')
            ->distinct()
            ->innerJoin('a.categorias', 'c');

        if ($soloCategoriasActivas) {
            $qb->andWhere('c.activo = true');
        }

        if ($categoriaId !== null) {
            $qb->andWhere('c.id = :categoriaId')
                ->setParameter('categoriaId', $categoriaId);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('a.nombre LIKE :search OR a.descripcion LIKE :search OR a.nombreArchivoOriginal LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $filas = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $fila): int => (int) $fila['id'], $filas);
    }

    /**
     * @param list<int> $ids
     * @return list<ArchivoPsicotecnico>
     */
    private function cargarPorIds(array $ids): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.categorias', 'c')
            ->addSelect('c')
            ->leftJoin('a.creadoPor', 'u')
            ->addSelect('u')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('a.fechaCreacion', 'DESC')
            ->addOrderBy('c.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
