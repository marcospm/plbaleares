<?php

namespace App\Repository;

use App\Entity\Examen;
use App\Entity\ExamenSemanal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExamenSemanal>
 */
class ExamenSemanalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamenSemanal::class);
    }

    /**
     * Cuenta realizaciones de alumnos sin hidratar los exámenes (las respuestas van en JSON).
     *
     * @param int[] $ids
     * @return array<int, int>
     */
    public function contarRealizaciones(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('es.id AS examenSemanalId, COUNT(e.id) AS total')
            ->from(Examen::class, 'e')
            ->innerJoin('e.examenSemanal', 'es')
            ->where('es.id IN (:ids)')
            ->groupBy('es.id')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getArrayResult();

        $conteos = [];
        foreach ($rows as $row) {
            $conteos[(int) $row['examenSemanalId']] = (int) $row['total'];
        }

        return $conteos;
    }

//    /**
//     * @return ExamenSemanal[] Returns an array of ExamenSemanal objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ExamenSemanal
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
