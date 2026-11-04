<?php

namespace App\Repository;

use App\Entity\ConvocatoriaBase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConvocatoriaBase>
 */
class ConvocatoriaBaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConvocatoriaBase::class);
    }
}
