<?php

namespace App\Repository;

use App\Entity\PreguntaMunicipal;
use App\Entity\Municipio;
use App\Entity\TemaMunicipal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreguntaMunicipal>
 */
class PreguntaMunicipalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreguntaMunicipal::class);
    }

    /**
     * Obtiene todas las preguntas activas de una plantilla municipal de forma optimizada
     */
    public function findActivasByPlantilla($plantilla): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.plantilla = :plantilla')
            ->andWhere('p.activo = :activo')
            ->setParameter('plantilla', $plantilla)
            ->setParameter('activo', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene preguntas activas de un municipio con una dificultad específica
     * @return PreguntaMunicipal[]
     */
    public function findActivasByMunicipioYDificultad(Municipio $municipio, string $dificultad, ?array $temasMunicipalesIds = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.municipio = :municipio')
            ->andWhere('p.dificultad = :dificultad')
            ->andWhere('p.activo = :activo')
            ->setParameter('municipio', $municipio)
            ->setParameter('dificultad', $dificultad)
            ->setParameter('activo', true);

        if ($temasMunicipalesIds && !empty($temasMunicipalesIds)) {
            $qb->andWhere('p.temaMunicipal IN (:temas)')
               ->setParameter('temas', $temasMunicipalesIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtiene preguntas activas de temas municipales específicos
     * @return PreguntaMunicipal[]
     */
    public function findByTemasMunicipales(array $temasMunicipales, string $dificultad): array
    {
        if (empty($temasMunicipales)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.temaMunicipal IN (:temas)')
            ->andWhere('p.dificultad = :dificultad')
            ->andWhere('p.activo = :activo')
            ->setParameter('temas', $temasMunicipales)
            ->setParameter('dificultad', $dificultad)
            ->setParameter('activo', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene preguntas municipales por sus IDs con eager loading
     * @param array $ids Array de IDs de preguntas municipales
     * @return PreguntaMunicipal[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->leftJoin('p.temaMunicipal', 'tm')
            ->addSelect('tm')
            ->leftJoin('p.municipio', 'm')
            ->addSelect('m')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}












