<?php

namespace App\Repository;

use App\Entity\PlantillaMunicipal;
use App\Entity\TemaMunicipal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlantillaMunicipal>
 */
class PlantillaMunicipalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlantillaMunicipal::class);
    }

    /**
     * Obtiene todas las plantillas de un tema municipal con eager loading
     */
    public function findByTemaMunicipal(TemaMunicipal $temaMunicipal): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.temaMunicipal', 't')
            ->addSelect('t')
            ->leftJoin('t.municipio', 'm')
            ->addSelect('m')
            ->where('p.temaMunicipal = :temaMunicipal')
            ->setParameter('temaMunicipal', $temaMunicipal)
            ->orderBy('p.dificultad', 'ASC')
            ->addOrderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene plantillas por tema municipal y dificultad con eager loading
     */
    public function findByTemaMunicipalYDificultad(TemaMunicipal $temaMunicipal, string $dificultad): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.temaMunicipal', 't')
            ->addSelect('t')
            ->leftJoin('t.municipio', 'm')
            ->addSelect('m')
            ->where('p.temaMunicipal = :temaMunicipal')
            ->andWhere('p.dificultad = :dificultad')
            ->setParameter('temaMunicipal', $temaMunicipal)
            ->setParameter('dificultad', $dificultad)
            ->orderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta el número de preguntas activas asociadas a una plantilla usando SQL directo
     */
    public function countPreguntasActivas(PlantillaMunicipal $plantilla): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\PreguntaMunicipal', 'p')
            ->where('p.plantilla = :plantilla')
            ->andWhere('p.activo = :activo')
            ->setParameter('plantilla', $plantilla)
            ->setParameter('activo', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
