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
}


