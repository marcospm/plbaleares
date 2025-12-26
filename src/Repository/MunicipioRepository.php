<?php

namespace App\Repository;

use App\Entity\Municipio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Municipio>
 */
class MunicipioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Municipio::class);
    }

    /**
     * @return Municipio[] Returns an array of active Municipio objects
     */
    public function findActivos(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('m.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}












