<?php

namespace App\Repository;

use App\Entity\ExamenPDF;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExamenPDF>
 */
class ExamenPDFRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamenPDF::class);
    }

    /**
     * Obtiene los últimos N exámenes PDF ordenados por fecha de subida descendente
     * @return ExamenPDF[]
     */
    public function findUltimos(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.fechaSubida', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

//    public function findOneBySomeField($value): ?ExamenPDF
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
