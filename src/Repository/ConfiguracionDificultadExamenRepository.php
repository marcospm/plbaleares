<?php

namespace App\Repository;

use App\Entity\ConfiguracionDificultadExamen;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfiguracionDificultadExamen>
 */
class ConfiguracionDificultadExamenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfiguracionDificultadExamen::class);
    }

    public function obtener(): ConfiguracionDificultadExamen
    {
        $config = $this->findOneBy([]);
        if ($config instanceof ConfiguracionDificultadExamen) {
            return $config;
        }

        $config = new ConfiguracionDificultadExamen();
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();

        return $config;
    }
}
