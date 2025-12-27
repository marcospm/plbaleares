<?php

namespace App\Repository;

use App\Entity\ConfiguracionExamen;
use App\Entity\Tema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfiguracionExamen>
 */
class ConfiguracionExamenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfiguracionExamen::class);
    }

    /**
     * Obtener configuración para un array de temas
     * 
     * @param array<Tema> $temas
     * @return array<ConfiguracionExamen>
     */
    public function findByTemas(array $temas): array
    {
        if (empty($temas)) {
            return [];
        }

        $temaIds = array_map(fn($tema) => $tema->getId(), $temas);

        return $this->createQueryBuilder('c')
            ->where('c.tema IN (:temas)')
            ->andWhere('c.activo = :activo')
            ->setParameter('temas', $temaIds)
            ->setParameter('activo', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtener todas las configuraciones activas
     * 
     * @return array<ConfiguracionExamen>
     */
    public function findAllActivos(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('c.tema', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtener configuración por tema
     * 
     * @param Tema $tema
     * @return ConfiguracionExamen|null
     */
    public function findByTema(Tema $tema): ?ConfiguracionExamen
    {
        return $this->createQueryBuilder('c')
            ->where('c.tema = :tema')
            ->andWhere('c.activo = :activo')
            ->setParameter('tema', $tema)
            ->setParameter('activo', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Resetear todos los porcentajes a null (distribución equitativa)
     */
    public function resetearPorcentajes(): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.porcentaje', ':null')
            ->setParameter('null', null)
            ->getQuery()
            ->execute();
    }
}

