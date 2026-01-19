<?php

namespace App\Repository;

use App\Entity\Plantilla;
use App\Entity\Tema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plantilla>
 */
class PlantillaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plantilla::class);
    }

    /**
     * Obtiene todas las plantillas de un tema con eager loading
     */
    public function findByTema(Tema $tema): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tema', 't')
            ->addSelect('t')
            ->where('p.tema = :tema')
            ->setParameter('tema', $tema)
            ->orderBy('p.dificultad', 'ASC')
            ->addOrderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene plantillas por tema y dificultad con eager loading
     */
    public function findByTemaYDificultad(Tema $tema, string $dificultad): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tema', 't')
            ->addSelect('t')
            ->where('p.tema = :tema')
            ->andWhere('p.dificultad = :dificultad')
            ->setParameter('tema', $tema)
            ->setParameter('dificultad', $dificultad)
            ->orderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta el número de preguntas activas asociadas a una plantilla usando SQL directo
     */
    public function countPreguntasActivas(Plantilla $plantilla): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Pregunta', 'p')
            ->where('p.plantilla = :plantilla')
            ->andWhere('p.activo = :activo')
            ->setParameter('plantilla', $plantilla)
            ->setParameter('activo', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtiene plantillas con conteo de preguntas activas en una sola consulta
     */
    public function findAllWithPreguntasCount(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tema', 't')
            ->addSelect('t')
            ->leftJoin('p.preguntas', 'pr')
            ->addSelect('COUNT(pr.id) as preguntas_count')
            ->where('pr.activo = :activo OR pr.id IS NULL')
            ->setParameter('activo', true)
            ->groupBy('p.id')
            ->orderBy('p.tema', 'ASC')
            ->addOrderBy('p.dificultad', 'ASC')
            ->addOrderBy('p.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
