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
    public function findByPlanificacionYFecha(PlanificacionPersonalizada $plan, \DateTime $fecha): array
    {
        $fechaNormalizada = clone $fecha;
        $fechaNormalizada->setTime(0, 0, 0);
        
        return $this->createQueryBuilder('f')
            ->where('f.planificacion = :plan')
            ->andWhere('f.fechaEspecifica = :fecha')
            ->setParameter('plan', $plan)
            ->setParameter('fecha', $fechaNormalizada)
            ->orderBy('f.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function findByUsuarioYFecha(User $usuario, \DateTime $fecha): array
    {
        $fechaNormalizada = clone $fecha;
        $fechaNormalizada->setTime(0, 0, 0);
        
        $qb = $this->createQueryBuilder('f')
            ->join('f.planificacion', 'p')
            ->leftJoin('f.tareasAsignadas', 't')
            ->leftJoin('t.tarea', 'ta')
            ->addSelect('t')
            ->addSelect('ta')
            ->where('p.usuario = :usuario')
            ->andWhere('f.fechaEspecifica = :fecha')
            ->andWhere('p.fechaInicio <= :fecha')
            ->andWhere('p.fechaFin >= :fecha')
            ->setParameter('usuario', $usuario)
            ->setParameter('fecha', $fechaNormalizada);

        return $qb->orderBy('f.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FranjaHorariaPersonalizada[]
     */
    public function findByUsuarioYRangoFechas(User $usuario, \DateTime $fechaInicio, \DateTime $fechaFin): array
    {
        $fechaInicioNormalizada = clone $fechaInicio;
        $fechaInicioNormalizada->setTime(0, 0, 0);
        $fechaFinNormalizada = clone $fechaFin;
        $fechaFinNormalizada->setTime(23, 59, 59);
        
        return $this->createQueryBuilder('f')
            ->join('f.planificacion', 'p')
            ->leftJoin('f.tareasAsignadas', 't')
            ->leftJoin('t.tarea', 'ta')
            ->addSelect('t')
            ->addSelect('ta')
            ->where('p.usuario = :usuario')
            ->andWhere('f.fechaEspecifica >= :fechaInicio')
            ->andWhere('f.fechaEspecifica <= :fechaFin')
            ->andWhere('p.fechaInicio <= :fechaFin')
            ->andWhere('p.fechaFin >= :fechaInicio')
            ->setParameter('usuario', $usuario)
            ->setParameter('fechaInicio', $fechaInicioNormalizada)
            ->setParameter('fechaFin', $fechaFinNormalizada)
            ->orderBy('f.fechaEspecifica', 'ASC')
            ->addOrderBy('f.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Verifica si hay solapamiento de horarios en una fecha específica
     */
    public function tieneSolapamiento(User $usuario, \DateTime $fecha, \DateTime $horaInicio, \DateTime $horaFin, ?int $excluirFranjaId = null): bool
    {
        $fechaNormalizada = clone $fecha;
        $fechaNormalizada->setTime(0, 0, 0);
        
        $qb = $this->createQueryBuilder('f')
            ->join('f.planificacion', 'p')
            ->where('p.usuario = :usuario')
            ->andWhere('f.fechaEspecifica = :fecha')
            ->andWhere('p.fechaInicio <= :fecha')
            ->andWhere('p.fechaFin >= :fecha')
            ->andWhere('(f.horaInicio < :horaFin AND f.horaFin > :horaInicio)')
            ->setParameter('usuario', $usuario)
            ->setParameter('fecha', $fechaNormalizada)
            ->setParameter('horaInicio', $horaInicio)
            ->setParameter('horaFin', $horaFin);
        
        if ($excluirFranjaId !== null) {
            $qb->andWhere('f.id != :excluirId')
               ->setParameter('excluirId', $excluirFranjaId);
        }
        
        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}

