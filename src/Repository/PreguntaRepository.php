<?php

namespace App\Repository;

use App\Entity\Pregunta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pregunta>
 */
class PreguntaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pregunta::class);
    }

    /**
     * Obtiene una pregunta activa aleatoria con tema y ley cargados
     */
    public function findAleatoriaActiva(): ?Pregunta
    {
        // Primero obtener el total de preguntas activas con texto
        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.activo = :activo')
            ->andWhere('p.texto IS NOT NULL')
            ->andWhere('p.texto != :vacio')
            ->setParameter('activo', true)
            ->setParameter('vacio', '')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total == 0) {
            return null;
        }

        // Obtener un offset aleatorio
        $offset = random_int(0, max(0, $total - 1));

        // Obtener la pregunta en esa posición
        return $this->createQueryBuilder('p')
            ->innerJoin('p.tema', 't')
            ->addSelect('t')
            ->innerJoin('p.ley', 'l')
            ->addSelect('l')
            ->where('p.activo = :activo')
            ->andWhere('p.texto IS NOT NULL')
            ->andWhere('p.texto != :vacio')
            ->setParameter('activo', true)
            ->setParameter('vacio', '')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

