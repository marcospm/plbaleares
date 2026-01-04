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
     * Incluye recursos asignados directamente al alumno o a través de un grupo
     */
    public function findByAlumno(User $alumno): array
    {
        // Recursos asignados directamente al alumno
        $recursosDirectos = $this->createQueryBuilder('r')
            ->innerJoin('r.alumnos', 'a')
            ->where('a.id = :alumnoId')
            ->andWhere('r.grupo IS NULL') // Solo recursos sin grupo (asignación directa)
            ->setParameter('alumnoId', $alumno->getId())
            ->getQuery()
            ->getResult();
        
        // Recursos asignados a grupos a los que pertenece el alumno
        $recursosPorGrupo = $this->createQueryBuilder('r')
            ->innerJoin('r.grupo', 'g')
            ->innerJoin('g.alumnos', 'ga')
            ->where('ga.id = :alumnoId')
            ->andWhere('r.grupo IS NOT NULL')
            ->setParameter('alumnoId', $alumno->getId())
            ->getQuery()
            ->getResult();
        
        // Combinar y eliminar duplicados
        $todosRecursos = array_merge($recursosDirectos, $recursosPorGrupo);
        $recursosUnicos = [];
        $idsVistos = [];
        
        foreach ($todosRecursos as $recurso) {
            $id = $recurso->getId();
            if (!in_array($id, $idsVistos)) {
                $recursosUnicos[] = $recurso;
                $idsVistos[] = $id;
            }
        }
        
        // Ordenar por fecha de creación descendente
        usort($recursosUnicos, function($a, $b) {
            return $b->getFechaCreacion() <=> $a->getFechaCreacion();
        });
        
        return $recursosUnicos;
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











