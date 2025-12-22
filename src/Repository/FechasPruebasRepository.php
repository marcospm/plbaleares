<?php

namespace App\Repository;

use App\Entity\FechasPruebas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FechasPruebas>
 */
class FechasPruebasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FechasPruebas::class);
    }

    public function findActivas(): ?FechasPruebas
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.fechaActualizacion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
