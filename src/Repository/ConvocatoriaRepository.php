<?php

namespace App\Repository;

use App\Entity\Convocatoria;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Convocatoria>
 */
class ConvocatoriaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Convocatoria::class);
    }

    /**
     * Obtiene todas las convocatorias activas
     * @return Convocatoria[]
     */
    public function findActivas(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('c.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene las convocatorias de un usuario específico
     * @return Convocatoria[]
     */
    public function findByUsuario(User $usuario): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.usuarios', 'u')
            ->where('u.id = :usuarioId')
            ->andWhere('c.activo = :activo')
            ->setParameter('usuarioId', $usuario->getId())
            ->setParameter('activo', true)
            ->orderBy('c.fechaCreacion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}


