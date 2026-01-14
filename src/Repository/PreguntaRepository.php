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
        // Excluir ley "Accidentes de Tráfico"
        $subquery = $this->getEntityManager()->createQueryBuilder()
            ->select('l2.id')
            ->from('App\Entity\Ley', 'l2')
            ->where('l2.nombre = :nombreLeyExcluida')
            ->setMaxResults(1);

        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.ley', 'l')
            ->where('p.activo = :activo')
            ->andWhere('l.id != (' . $subquery->getDQL() . ')')
            ->andWhere('p.texto IS NOT NULL')
            ->andWhere('p.texto != :vacio')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
            ->setParameter('vacio', '')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total == 0) {
            return null;
        }

        // Obtener un offset aleatorio
        $offset = random_int(0, max(0, $total - 1));

        // Obtener la pregunta en esa posición
        $subquery2 = $this->getEntityManager()->createQueryBuilder()
            ->select('l3.id')
            ->from('App\Entity\Ley', 'l3')
            ->where('l3.nombre = :nombreLeyExcluida2')
            ->setMaxResults(1);

        return $this->createQueryBuilder('p')
            ->innerJoin('p.tema', 't')
            ->addSelect('t')
            ->innerJoin('p.ley', 'l')
            ->addSelect('l')
            ->where('p.activo = :activo')
            ->andWhere('l.id != (' . $subquery2->getDQL() . ')')
            ->andWhere('p.texto IS NOT NULL')
            ->andWhere('p.texto != :vacio')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida2', 'Accidentes de Tráfico')
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
        // Excluir ley "Accidentes de Tráfico"
        $subquery = $this->getEntityManager()->createQueryBuilder()
            ->select('l2.id')
            ->from('App\Entity\Ley', 'l2')
            ->where('l2.nombre = :nombreLeyExcluida')
            ->setMaxResults(1);

        $ids = $this->createQueryBuilder('p')
            ->select('p.id')
            ->innerJoin('p.ley', 'l')
            ->where('p.activo = :activo')
            ->andWhere('l.id != (' . $subquery->getDQL() . ')')
            ->andWhere('p.texto IS NOT NULL')
            ->andWhere('p.texto != :vacio')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
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

    /**
     * Obtiene preguntas activas aleatorias con filtro de dificultad
     * Solo del temario general (no municipales)
     * @param int $limit Número de preguntas a obtener
     * @param string|null $dificultad Dificultad ('facil', 'media', 'dificil') o null para todas
     * @return Pregunta[]
     */
    public function findAleatoriasActivasPorDificultad(int $limit = 20, ?string $dificultad = null): array
    {
        // Obtener solo los IDs de las preguntas activas con texto
        // Excluir ley "Accidentes de Tráfico"
        $subquery = $this->getEntityManager()->createQueryBuilder()
            ->select('l2.id')
            ->from('App\Entity\Ley', 'l2')
            ->where('l2.nombre = :nombreLeyExcluida')
            ->setMaxResults(1);

        $qb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->innerJoin('p.tema', 't')
            ->innerJoin('p.ley', 'l')
            ->where('p.activo = :activo')
            ->andWhere('t.activo = :activo')
            ->andWhere('l.id != (' . $subquery->getDQL() . ')')
            ->andWhere('p.texto IS NOT NULL')
            ->andWhere('p.texto != :vacio')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
            ->setParameter('vacio', '');

        // Filtrar por dificultad si se especifica (normalizar el valor)
        if ($dificultad !== null && $dificultad !== '') {
            // Asegurar que la dificultad sea válida
            $dificultadesValidas = ['facil', 'media', 'dificil'];
            if (in_array(strtolower($dificultad), $dificultadesValidas)) {
                $qb->andWhere('p.dificultad = :dificultad')
                   ->setParameter('dificultad', strtolower($dificultad));
            }
        }

        $ids = $qb->getQuery()->getResult();

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

    /**
     * Obtiene preguntas por sus IDs
     * @param array $ids Array de IDs de preguntas
     * @return Pregunta[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->innerJoin('p.tema', 't')
            ->addSelect('t')
            ->innerJoin('p.ley', 'l')
            ->addSelect('l')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}

