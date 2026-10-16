<?php

namespace App\Repository;

use App\Entity\ConfiguracionRanking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfiguracionRanking>
 */
class ConfiguracionRankingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfiguracionRanking::class);
    }

    public function getFechaCorte(): ?\DateTimeInterface
    {
        $config = $this->findOneBy([]);

        return $config?->getFechaCorte();
    }

    public function establecerFechaCorte(?\DateTimeInterface $fechaCorte): ConfiguracionRanking
    {
        $config = $this->findOneBy([]);
        if ($config === null) {
            $config = new ConfiguracionRanking();
            $this->getEntityManager()->persist($config);
        }

        $config->setFechaCorte($fechaCorte);
        $this->getEntityManager()->flush();

        return $config;
    }
}
