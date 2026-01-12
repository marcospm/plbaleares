<?php

namespace App\Repository;

use App\Entity\Articulo;
use App\Entity\MensajeArticulo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MensajeArticulo>
 */
class MensajeArticuloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MensajeArticulo::class);
    }

    /**
     * Obtiene todos los mensajes de un artículo (solo mensajes principales, sin respuestas)
     * Carga también las respuestas (eager loading)
     * 
     * @return MensajeArticulo[]
     */
    public function findMensajesPrincipales(Articulo $articulo): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.respuestas', 'r')
            ->addSelect('r')
            ->leftJoin('m.autor', 'a')
            ->addSelect('a')
            ->leftJoin('r.autor', 'ra')
            ->addSelect('ra')
            ->where('m.articulo = :articulo')
            ->andWhere('m.mensajePadre IS NULL')
            ->setParameter('articulo', $articulo)
            ->orderBy('m.fechaCreacion', 'DESC')
            ->addOrderBy('r.fechaCreacion', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta los mensajes principales de un artículo
     */
    public function countMensajesPrincipales(Articulo $articulo): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.articulo = :articulo')
            ->andWhere('m.mensajePadre IS NULL')
            ->setParameter('articulo', $articulo)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) $result;
    }

    /**
     * Cuenta los mensajes principales de múltiples artículos en una sola consulta
     * 
     * @param array $articulosIds Array de IDs de artículos
     * @return array Array asociativo [articuloId => cantidadMensajes]
     */
    public function countMensajesPrincipalesPorArticulos(array $articulosIds): array
    {
        if (empty($articulosIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.articulo) as articuloId, COUNT(m.id) as cantidad')
            ->where('m.articulo IN (:articulosIds)')
            ->andWhere('m.mensajePadre IS NULL')
            ->setParameter('articulosIds', $articulosIds)
            ->groupBy('m.articulo')
            ->getQuery()
            ->getResult();

        // Inicializar todos los IDs con 0
        $contadores = array_fill_keys($articulosIds, 0);

        // Actualizar con los valores reales
        foreach ($results as $result) {
            $contadores[$result['articuloId']] = (int) $result['cantidad'];
        }

        return $contadores;
    }
}

