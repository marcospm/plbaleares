<?php

namespace App\Service;

use App\Entity\Convocatoria;
use App\Entity\User;
use App\Repository\ConvocatoriaRepository;
use App\Repository\UserRepository;

class AsignacionConvocatoriaService
{
    public function __construct(
        private ConvocatoriaRepository $convocatoriaRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Asigna al usuario las convocatorias activas que todavía no tiene.
     * Consulta la base de datos directamente para que Doctrine persista la relación.
     */
    public function asignarConvocatoriasActivas(User $user): void
    {
        $convocatorias = $this->convocatoriaRepository->createQueryBuilder('c')
            ->where('c.activo = :activo')
            ->setParameter('activo', true)
            ->getQuery()
            ->getResult();

        foreach ($convocatorias as $convocatoria) {
            $user->addConvocatoria($convocatoria);
        }
    }

    /**
     * Asigna la convocatoria a los usuarios que todavía no la tienen.
     * No incluye cuentas eliminadas ni con datos suprimidos.
     */
    public function asignarAUsuariosSinAsignar(Convocatoria $convocatoria): void
    {
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.eliminado = :eliminado')
            ->andWhere('u.suprimido = :suprimido')
            ->setParameter('eliminado', false)
            ->setParameter('suprimido', false);

        if ($convocatoria->getId() !== null) {
            $qb->andWhere(':convocatoria NOT MEMBER OF u.convocatorias')
                ->setParameter('convocatoria', $convocatoria);
        }

        foreach ($qb->getQuery()->getResult() as $usuario) {
            $usuario->addConvocatoria($convocatoria);
        }
    }
}
