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
        $qb = $this->createQueryBuilder('p');

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

        // Nota: El filtro por usuario ya no está disponible ya que PlanificacionSemanal
        // ya no tiene relación directa con usuarios (se usa PlanificacionPersonalizada ahora)

        return $qb->orderBy('p.fechaCreacion', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}

