<?php

namespace App\Repository;

use App\Entity\TareaAsignada;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TareaAsignada>
 */
class TareaAsignadaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TareaAsignada::class);
    }

    /**
     * @return TareaAsignada[]
     */
    public function findByUsuarioYsemana(User $usuario, \DateTimeInterface $semana): array
    {
        // Asegurar que $semana es el lunes
        $lunes = clone $semana;
        if ($lunes->format('N') != '1') {
            $lunes->modify('monday this week');
        }
        
        return $this->createQueryBuilder('ta')
            ->join('ta.tarea', 't')
            ->where('ta.usuario = :usuario')
            ->andWhere('t.semanaAsignacion = :semana')
            ->setParameter('usuario', $usuario)
            ->setParameter('semana', $lunes)
            ->orderBy('t.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TareaAsignada[]
     */
    public function findPendientesByUsuario(User $usuario): array
    {
        return $this->createQueryBuilder('ta')
            ->where('ta.usuario = :usuario')
            ->andWhere('ta.completada = :completada')
            ->setParameter('usuario', $usuario)
            ->setParameter('completada', false)
            ->join('ta.tarea', 't')
            ->orderBy('t.semanaAsignacion', 'ASC')
            ->addOrderBy('t.fechaCreacion', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

