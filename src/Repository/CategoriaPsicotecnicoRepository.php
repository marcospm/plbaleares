<?php

namespace App\Repository;

use App\Entity\CategoriaPsicotecnico;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoriaPsicotecnico>
 */
class CategoriaPsicotecnicoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoriaPsicotecnico::class);
    }

    /**
     * @return list<CategoriaPsicotecnico>
     */
    public function findOrdenadas(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.archivos', 'a')
            ->addSelect('a')
            ->orderBy('c.orden', 'ASC')
            ->addOrderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<CategoriaPsicotecnico>
     */
    public function findActivas(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.activo = true')
            ->orderBy('c.orden', 'ASC')
            ->addOrderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{id: int, nombre: string, total: int}>
     */
    public function resumenVisible(): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT c.id AS id, c.nombre AS nombre, COUNT(a.id) AS total
             FROM App\Entity\CategoriaPsicotecnico c
             LEFT JOIN c.archivos a
             WHERE c.activo = true
             GROUP BY c.id, c.nombre, c.orden
             ORDER BY c.orden ASC, c.nombre ASC'
        )->getResult();
    }
}
