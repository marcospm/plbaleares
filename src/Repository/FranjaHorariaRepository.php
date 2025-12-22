<?php

namespace App\Repository;

use App\Entity\FranjaHoraria;
use App\Entity\PlanificacionSemanal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FranjaHoraria>
 */
class FranjaHorariaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FranjaHoraria::class);
    }

    /**
     * @return FranjaHoraria[]
     */
    public function findByPlanificacionYdia(PlanificacionSemanal $plan, int $dia): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.planificacion = :plan')
            ->andWhere('f.diaSemana = :dia')
            ->setParameter('plan', $plan)
            ->setParameter('dia', $dia)
            ->orderBy('f.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FranjaHoraria[]
     */
    public function findByPlanificacionOrdenadas(PlanificacionSemanal $plan): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.planificacion = :plan')
            ->setParameter('plan', $plan)
            ->orderBy('f.diaSemana', 'ASC')
            ->addOrderBy('f.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

