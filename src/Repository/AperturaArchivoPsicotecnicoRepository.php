<?php

namespace App\Repository;

use App\Entity\AperturaArchivoPsicotecnico;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AperturaArchivoPsicotecnico>
 */
class AperturaArchivoPsicotecnicoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AperturaArchivoPsicotecnico::class);
    }

    /**
     * Veces que los alumnos han pulsado Abrir, y cuántos alumnos distintos lo han hecho.
     *
     * @param list<int> $archivoIds
     * @return array<int, array{veces: int, alumnos: int}>
     */
    public function conteoPorArchivo(array $archivoIds): array
    {
        if ($archivoIds === []) {
            return [];
        }

        $filas = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT ap.archivo_id, COUNT(*) AS veces, COUNT(DISTINCT ap.usuario_id) AS alumnos
             FROM apertura_archivo_psicotecnico ap
             INNER JOIN `user` u ON u.id = ap.usuario_id
             WHERE ap.archivo_id IN (?)
               AND u.eliminado = 0 AND u.suprimido = 0
               AND u.roles NOT LIKE \'%"ROLE_PROFESOR"%\'
               AND u.roles NOT LIKE \'%"ROLE_ADMIN"%\'
             GROUP BY ap.archivo_id',
            [$archivoIds],
            [ArrayParameterType::INTEGER]
        );

        $conteo = [];
        foreach ($filas as $fila) {
            $conteo[(int) $fila['archivo_id']] = [
                'veces' => (int) $fila['veces'],
                'alumnos' => (int) $fila['alumnos'],
            ];
        }

        return $conteo;
    }
}
