<?php

namespace App\Repository;

use App\Entity\RecursoEspecifico;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecursoEspecifico>
 */
class RecursoEspecificoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecursoEspecifico::class);
    }

    /**
     * Obtiene recursos específicos asignados a un alumno
     */
    public function findByAlumno(User $alumno): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.alumnos', 'a')
            ->where('a.id = :alumnoId')
            ->setParameter('alumnoId', $alumno->getId())
            ->orderBy('r.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene recursos específicos creados por un profesor
     */
    public function findByProfesor(User $profesor): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.profesor = :profesor')
            ->setParameter('profesor', $profesor)
            ->orderBy('r.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}







