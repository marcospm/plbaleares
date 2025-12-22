<?php

namespace App\Repository;

use App\Entity\PlanificacionSemanal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanificacionSemanal>
 */
class PlanificacionSemanalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanificacionSemanal::class);
    }

    /**
     * @return PlanificacionSemanal[]
     */
    public function findActivas(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.activa = :activa')
            ->setParameter('activa', true)
            ->orderBy('p.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PlanificacionSemanal[]
     */
    public function findByCreador(User $profesor): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.creadoPor = :profesor')
            ->setParameter('profesor', $profesor)
            ->orderBy('p.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PlanificacionSemanal[]
     */
    public function findConFiltros(?string $nombre = null, ?string $estado = null, ?int $usuarioId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.planificacionesPersonalizadas', 'pp')
            ->leftJoin('pp.usuario', 'u')
            ->groupBy('p.id');

        if ($nombre && $nombre !== '') {
            $qb->andWhere('p.nombre LIKE :nombre')
               ->setParameter('nombre', '%' . $nombre . '%');
        }

        if ($estado && $estado !== '') {
            if ($estado === 'activa') {
                $qb->andWhere('p.activa = :activa')
                   ->setParameter('activa', true);
            } elseif ($estado === 'inactiva') {
                $qb->andWhere('p.activa = :activa')
                   ->setParameter('activa', false);
            }
        }

        if ($usuarioId && $usuarioId !== '') {
            $qb->andWhere('u.id = :usuarioId')
               ->setParameter('usuarioId', $usuarioId);
        }

        return $qb->orderBy('p.fechaCreacion', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}

