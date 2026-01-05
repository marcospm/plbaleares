<?php

namespace App\Repository;

use App\Entity\ExamenBorrador;
use App\Entity\User;
use App\Entity\ExamenSemanal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExamenBorrador>
 */
class ExamenBorradorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExamenBorrador::class);
    }

    /**
     * @return ExamenBorrador[] Returns an array of ExamenBorrador objects for a user
     */
    public function findByUsuario(User $usuario): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('e.fechaActualizacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a borrador by user and tipo
     */
    public function findOneByUsuarioAndTipo(User $usuario, string $tipoExamen): ?ExamenBorrador
    {
        return $this->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.tipoExamen = :tipoExamen')
            ->setParameter('usuario', $usuario)
            ->setParameter('tipoExamen', $tipoExamen)
            ->orderBy('e.fechaActualizacion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a borrador by user and ExamenSemanal
     */
    public function findOneByUsuarioAndExamenSemanal(User $usuario, ExamenSemanal $examenSemanal): ?ExamenBorrador
    {
        return $this->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.examenSemanal = :examenSemanal')
            ->setParameter('usuario', $usuario)
            ->setParameter('examenSemanal', $examenSemanal)
            ->orderBy('e.fechaActualizacion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
