<?php

namespace App\Repository;

use App\Entity\PlanificacionPersonalizada;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanificacionPersonalizada>
 */
class PlanificacionPersonalizadaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanificacionPersonalizada::class);
    }

    public function findByUsuario(User $usuario): ?PlanificacionPersonalizada
    {
        return $this->createQueryBuilder('p')
            ->where('p.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('p.fechaCreacion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PlanificacionPersonalizada[]
     */
    public function findAllByUsuario(User $usuario): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('p.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene planificaciones activas de un usuario en un rango de fechas
     * @return PlanificacionPersonalizada[]
     */
    public function findActivasPorRango(User $usuario, \DateTime $fechaInicio, \DateTime $fechaFin): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.usuario = :usuario')
            ->andWhere('p.fechaInicio <= :fechaFin')
            ->andWhere('p.fechaFin >= :fechaInicio')
            ->setParameter('usuario', $usuario)
            ->setParameter('fechaInicio', $fechaInicio)
            ->setParameter('fechaFin', $fechaFin)
            ->orderBy('p.fechaInicio', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

