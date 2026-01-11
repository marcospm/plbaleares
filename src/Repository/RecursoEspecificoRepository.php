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
     * OPTIMIZADO: Usa una sola consulta SQL con OR conditions en lugar de dos consultas separadas
     * Incluye recursos asignados directamente al alumno o a través de un grupo
     */
    public function findByAlumno(User $alumno): array
    {
        // Una sola consulta que obtiene recursos tanto directos como por grupo
        $recursos = $this->createQueryBuilder('r')
            ->leftJoin('r.alumnos', 'a')
            ->leftJoin('r.grupo', 'g')
            ->leftJoin('g.alumnos', 'ga')
            ->where('(a.id = :alumnoId AND r.grupo IS NULL) OR (ga.id = :alumnoId AND r.grupo IS NOT NULL)')
            ->setParameter('alumnoId', $alumno->getId())
            ->groupBy('r.id') // Eliminar duplicados a nivel SQL
            ->orderBy('r.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $recursos;
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











