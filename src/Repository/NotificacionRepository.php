<?php

namespace App\Repository;

use App\Entity\Notificacion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notificacion>
 */
class NotificacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notificacion::class);
    }

    /**
     * Obtiene notificaciones no leídas de un profesor
     */
    public function findNoLeidasByProfesor(User $profesor): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.profesor = :profesor')
            ->andWhere('n.leida = :leida')
            ->setParameter('profesor', $profesor)
            ->setParameter('leida', false)
            ->orderBy('n.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta notificaciones no leídas de un profesor
     */
    public function countNoLeidasByProfesor(User $profesor): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.profesor = :profesor')
            ->andWhere('n.leida = :leida')
            ->setParameter('profesor', $profesor)
            ->setParameter('leida', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtiene todas las notificaciones de un profesor (leídas y no leídas)
     */
    public function findAllByProfesor(User $profesor): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.profesor = :profesor')
            ->setParameter('profesor', $profesor)
            ->orderBy('n.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene notificaciones no leídas de un alumno
     * Solo incluye notificaciones de acciones de profesores, no de las propias acciones del alumno
     */
    public function findNoLeidasByAlumno(User $alumno): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.alumno = :alumno')
            ->andWhere('n.leida = :leida')
            ->andWhere('n.tipo IN (:tiposPermitidos)')
            ->setParameter('alumno', $alumno)
            ->setParameter('leida', false)
            ->setParameter('tiposPermitidos', [
                Notificacion::TIPO_PLANIFICACION_CREADA,
                Notificacion::TIPO_PLANIFICACION_EDITADA,
                Notificacion::TIPO_PLANIFICACION_ELIMINADA,
                Notificacion::TIPO_TAREA_CREADA,
                Notificacion::TIPO_TAREA_EDITADA,
                Notificacion::TIPO_TAREA_ELIMINADA,
                Notificacion::TIPO_EXAMEN_SEMANAL,
            ])
            ->orderBy('n.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta notificaciones no leídas de un alumno
     * Solo incluye notificaciones de acciones de profesores, no de las propias acciones del alumno
     */
    public function countNoLeidasByAlumno(User $alumno): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.alumno = :alumno')
            ->andWhere('n.leida = :leida')
            ->andWhere('n.tipo IN (:tiposPermitidos)')
            ->setParameter('alumno', $alumno)
            ->setParameter('leida', false)
            ->setParameter('tiposPermitidos', [
                Notificacion::TIPO_PLANIFICACION_CREADA,
                Notificacion::TIPO_PLANIFICACION_EDITADA,
                Notificacion::TIPO_PLANIFICACION_ELIMINADA,
                Notificacion::TIPO_TAREA_CREADA,
                Notificacion::TIPO_TAREA_EDITADA,
                Notificacion::TIPO_TAREA_ELIMINADA,
                Notificacion::TIPO_EXAMEN_SEMANAL,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtiene todas las notificaciones de un alumno (leídas y no leídas)
     * Solo incluye notificaciones de acciones de profesores, no de las propias acciones del alumno
     */
    public function findAllByAlumno(User $alumno): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.alumno = :alumno')
            ->andWhere('n.tipo IN (:tiposPermitidos)')
            ->setParameter('alumno', $alumno)
            ->setParameter('tiposPermitidos', [
                Notificacion::TIPO_PLANIFICACION_CREADA,
                Notificacion::TIPO_PLANIFICACION_EDITADA,
                Notificacion::TIPO_PLANIFICACION_ELIMINADA,
                Notificacion::TIPO_TAREA_CREADA,
                Notificacion::TIPO_TAREA_EDITADA,
                Notificacion::TIPO_TAREA_ELIMINADA,
                Notificacion::TIPO_EXAMEN_SEMANAL,
            ])
            ->orderBy('n.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}



