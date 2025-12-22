<?php

namespace App\Repository;

use App\Entity\PlanificacionPersonalizada;
use App\Entity\PlanificacionSemanal;
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
     * @return PlanificacionPersonalizada[]
     */
    public function findByPlanificacionBase(PlanificacionSemanal $base): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.planificacionBase = :base')
            ->setParameter('base', $base)
            ->getQuery()
            ->getResult();
    }

    public function findByUsuarioAndPlanificacionBase(User $usuario, PlanificacionSemanal $planificacionBase): ?PlanificacionPersonalizada
    {
        return $this->createQueryBuilder('p')
            ->where('p.usuario = :usuario')
            ->andWhere('p.planificacionBase = :planificacionBase')
            ->setParameter('usuario', $usuario)
            ->setParameter('planificacionBase', $planificacionBase)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

