<?php

namespace App\Repository;

use App\Entity\FranjaHorariaPersonalizada;
use App\Entity\PlanificacionPersonalizada;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FranjaHorariaPersonalizada>
 */
class FranjaHorariaPersonalizadaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FranjaHorariaPersonalizada::class);
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function findByPlanificacionYdia(PlanificacionPersonalizada $plan, int $dia): array
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
     * @return FranjaHorariaPersonalizada[]
     */
    public function findByUsuarioYdia(User $usuario, int $diaSemana, ?\DateTime $fechaReferencia = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->join('f.planificacion', 'p')
            ->join('p.planificacionBase', 'pb')
            ->leftJoin('f.tareasAsignadas', 't')
            ->leftJoin('t.tarea', 'ta')
            ->addSelect('t')
            ->addSelect('ta')
            ->where('p.usuario = :usuario')
            ->andWhere('f.diaSemana = :dia')
            ->setParameter('usuario', $usuario)
            ->setParameter('dia', $diaSemana);

        // Filtrar por fechaFin si se proporciona una fecha de referencia
        if ($fechaReferencia !== null) {
            // Si la planificación tiene fechaFin, solo mostrar si la fecha de referencia es anterior o igual
            // Normalizar la fecha de referencia al inicio del día para comparación
            $fechaReferenciaInicio = clone $fechaReferencia;
            $fechaReferenciaInicio->setTime(0, 0, 0);
            // Comparar solo la parte de fecha (sin hora)
            $qb->andWhere('(pb.fechaFin IS NULL OR pb.fechaFin >= :fechaReferencia)')
               ->setParameter('fechaReferencia', $fechaReferenciaInicio);
        }

        return $qb->orderBy('f.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function findConTareas(User $usuario, int $diaSemana, \DateTime $fechaSemana): array
    {
        // Obtener el lunes de la semana
        $lunesSemana = clone $fechaSemana;
        $lunesSemana->modify('monday this week');
        
        return $this->createQueryBuilder('f')
            ->join('f.planificacion', 'p')
            ->leftJoin('f.tareasAsignadas', 't')
            ->leftJoin('t.tarea', 'ta')
            ->where('p.usuario = :usuario')
            ->andWhere('f.diaSemana = :dia')
            ->andWhere('(ta.semanaAsignacion = :semana OR ta.semanaAsignacion IS NULL)')
            ->setParameter('usuario', $usuario)
            ->setParameter('dia', $diaSemana)
            ->setParameter('semana', $lunesSemana)
            ->orderBy('f.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

