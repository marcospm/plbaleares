<?php

namespace App\Repository;

use App\Entity\MensajePregunta;
use App\Entity\Pregunta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MensajePregunta>
 */
class MensajePreguntaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MensajePregunta::class);
    }

    /**
     * Obtiene todos los mensajes de una pregunta (solo mensajes principales, sin respuestas)
     * Carga también las respuestas (eager loading)
     * 
     * @return MensajePregunta[]
     */
    public function findMensajesPrincipales(Pregunta $pregunta): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.respuestas', 'r')
            ->addSelect('r')
            ->leftJoin('m.autor', 'a')
            ->addSelect('a')
            ->leftJoin('r.autor', 'ra')
            ->addSelect('ra')
            ->where('m.pregunta = :pregunta')
            ->andWhere('m.mensajePadre IS NULL')
            ->setParameter('pregunta', $pregunta)
            ->orderBy('m.fechaCreacion', 'DESC')
            ->addOrderBy('r.fechaCreacion', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta los mensajes principales de una pregunta
     */
    public function countMensajesPrincipales(Pregunta $pregunta): int
    {
        try {
            $result = $this->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.pregunta = :pregunta')
                ->andWhere('m.mensajePadre IS NULL')
                ->setParameter('pregunta', $pregunta)
                ->getQuery()
                ->getSingleScalarResult();
            
            return (int) $result;
        } catch (\Doctrine\ORM\NoResultException $e) {
            return 0;
        }
    }
}

