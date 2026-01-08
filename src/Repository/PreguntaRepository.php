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

    /**
     * Obtiene 20 preguntas activas aleatorias con tema y ley cargados
     * Mezcla preguntas de diferentes leyes
     * @return Pregunta[]
     */
    public function findAleatoriasActivas(int $limit = 20): array
    {
        // Primero obtener solo los IDs de las preguntas activas con texto
        // Esto es mucho más rápido que cargar todas las entidades
        $ids = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.activo = :activo')
            ->andWhere('p.texto IS NOT NULL')
            ->andWhere('p.texto != :vacio')
            ->setParameter('activo', true)
            ->setParameter('vacio', '')
            ->getQuery()
            ->getResult();

        if (empty($ids)) {
            return [];
        }

        // Convertir a array simple de IDs
        $idsArray = array_map(function($row) {
            return $row['id'];
        }, $ids);

        // Mezclar los IDs para aleatoriedad
        shuffle($idsArray);

        // Tomar solo el número necesario de IDs
        $idsSeleccionados = array_slice($idsArray, 0, min($limit, count($idsArray)));

        if (empty($idsSeleccionados)) {
            return [];
        }

        // Ahora cargar solo las preguntas seleccionadas con sus relaciones
        return $this->createQueryBuilder('p')
            ->innerJoin('p.tema', 't')
            ->addSelect('t')
            ->innerJoin('p.ley', 'l')
            ->addSelect('l')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $idsSeleccionados)
            ->getQuery()
            ->getResult();
    }
}

