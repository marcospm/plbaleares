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
     */
    public function findNoLeidasByAlumno(User $alumno): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.alumno = :alumno')
            ->andWhere('n.leida = :leida')
            ->setParameter('alumno', $alumno)
            ->setParameter('leida', false)
            ->orderBy('n.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta notificaciones no leídas de un alumno
     */
    public function countNoLeidasByAlumno(User $alumno): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.alumno = :alumno')
            ->andWhere('n.leida = :leida')
            ->setParameter('alumno', $alumno)
            ->setParameter('leida', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtiene todas las notificaciones de un alumno (leídas y no leídas)
     */
    public function findAllByAlumno(User $alumno): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.alumno = :alumno')
            ->setParameter('alumno', $alumno)
            ->orderBy('n.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}



