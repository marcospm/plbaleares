<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\VideoConvocatoria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoConvocatoria>
 */
class VideoConvocatoriaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoConvocatoria::class);
    }

    /**
     * @return array{videos: VideoConvocatoria[], total: int}
     */
    public function findPaginated(
        ?string $search,
        ?int $convocatoriaId,
        ?User $alumno,
        int $page,
        int $itemsPerPage
    ): array {
        $qb = $this->createQueryBuilder('v')
            ->innerJoin('v.convocatoria', 'c');

        if ($alumno !== null) {
            $qb->innerJoin('c.usuarios', 'u')
                ->andWhere('u.id = :usuarioId')
                ->andWhere('c.activo = :activo')
                ->setParameter('usuarioId', $alumno->getId())
                ->setParameter('activo', true);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('v.titulo LIKE :search OR v.descripcion LIKE :search OR c.nombre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($convocatoriaId !== null) {
            $qb->andWhere('c.id = :convocatoriaId')
                ->setParameter('convocatoriaId', $convocatoriaId);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT v.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $ids = array_column(
            (clone $qb)
                ->select('v.id AS id')
                ->addSelect('c.nombre AS nombreConvocatoria')
                ->addSelect('v.orden AS orden')
                ->addSelect('v.fechaCreacion AS fechaCreacion')
                ->groupBy('v.id')
                ->addGroupBy('c.nombre')
                ->addGroupBy('v.orden')
                ->addGroupBy('v.fechaCreacion')
                ->orderBy('c.nombre', 'ASC')
                ->addOrderBy('v.orden', 'ASC')
                ->addOrderBy('v.fechaCreacion', 'DESC')
                ->setFirstResult(($page - 1) * $itemsPerPage)
                ->setMaxResults($itemsPerPage)
                ->getQuery()
                ->getScalarResult(),
            'id'
        );

        if ($ids === []) {
            return [
                'videos' => [],
                'total' => $total,
            ];
        }

        $videos = $this->createQueryBuilder('v')
            ->innerJoin('v.convocatoria', 'c')
            ->addSelect('c')
            ->leftJoin('v.creadoPor', 'autor')
            ->addSelect('autor')
            ->where('v.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('c.nombre', 'ASC')
            ->addOrderBy('v.orden', 'ASC')
            ->addOrderBy('v.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'videos' => $videos,
            'total' => $total,
        ];
    }
}
