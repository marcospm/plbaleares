<?php

namespace App\Repository;

use App\Entity\TemaMunicipal;
use App\Entity\Municipio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TemaMunicipal>
 */
class TemaMunicipalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemaMunicipal::class);
    }

    /**
     * @return TemaMunicipal[] Returns an array of TemaMunicipal objects for a municipio
     */
    public function findByMunicipio(Municipio $municipio): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.municipio = :municipio')
            ->setParameter('municipio', $municipio)
            ->andWhere('t.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('t.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

