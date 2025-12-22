<?php

namespace App\Repository;

use App\Entity\Tarea;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tarea>
 */
class TareaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarea::class);
    }

    /**
     * @return Tarea[]
     */
    public function findBySemana(\DateTimeInterface $semana): array
    {
        // Asegurar que $semana es el lunes
        $lunes = clone $semana;
        if ($lunes->format('N') != '1') {
            $lunes->modify('monday this week');
        }
        
        return $this->createQueryBuilder('t')
            ->where('t.semanaAsignacion = :semana')
            ->setParameter('semana', $lunes)
            ->orderBy('t.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tarea[]
     */
    public function findByUsuarioYsemana(User $usuario, \DateTimeInterface $semana): array
    {
        // Asegurar que $semana es el lunes
        $lunes = clone $semana;
        if ($lunes->format('N') != '1') {
            $lunes->modify('monday this week');
        }
        
        return $this->createQueryBuilder('t')
            ->join('t.asignaciones', 'a')
            ->where('a.usuario = :usuario')
            ->andWhere('t.semanaAsignacion = :semana')
            ->setParameter('usuario', $usuario)
            ->setParameter('semana', $lunes)
            ->orderBy('t.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

