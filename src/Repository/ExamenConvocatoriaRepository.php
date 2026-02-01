<?php

namespace App\Repository;

use App\Entity\ExamenConvocatoria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExamenConvocatoria>
 */
class ExamenConvocatoriaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamenConvocatoria::class);
    }

    /**
     * Obtiene los últimos N exámenes de otras convocatorias ordenados por fecha de subida descendente
     * @return ExamenConvocatoria[]
     */
    public function findUltimos(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.fechaSubida', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
