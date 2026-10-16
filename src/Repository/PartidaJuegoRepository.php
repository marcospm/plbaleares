<?php

namespace App\Repository;

use App\Entity\PartidaJuego;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PartidaJuego>
 */
class PartidaJuegoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartidaJuego::class);
    }

    /**
     * Obtiene el conteo de partidas por tipo de juego para un usuario
     * @return array Array con ['tipoJuego' => 'cantidad']
     */
    public function getConteoPorTipoJuego(User $usuario): array
    {
        $result = $this->createQueryBuilder('pj')
            ->select('pj.tipoJuego, COUNT(pj.id) as cantidad')
            ->where('pj.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->groupBy('pj.tipoJuego')
            ->getQuery()
            ->getResult();

        $conteo = [];
        foreach ($result as $row) {
            $conteo[$row['tipoJuego']] = (int) $row['cantidad'];
        }

        return $conteo;
    }

    /**
     * Obtiene el ranking de partidas por tipo de juego
     * Filtra automáticamente usuarios activos y excluye profesores/admins
     * @param string $tipoJuego Tipo de juego
     * @param array|null $alumnosIds Si se proporciona, solo incluye estos alumnos (para profesores)
     * @return array Array con ['usuario' => User, 'cantidad' => int] ordenado por cantidad DESC
     */
    public function getRankingPorTipoJuego(string $tipoJuego, ?array $alumnosIds = null): array
    {
        // Primero obtener los IDs de usuarios con sus conteos
        $qb = $this->createQueryBuilder('pj')
            ->select('IDENTITY(pj.usuario) as usuarioId, COUNT(pj.id) as cantidad')
            ->innerJoin('pj.usuario', 'u')
            ->where('pj.tipoJuego = :tipoJuego')
            ->andWhere('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('tipoJuego', $tipoJuego)
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->groupBy('pj.usuario')
            ->orderBy('cantidad', 'DESC');

        // Si se proporcionan IDs de alumnos, filtrar por ellos
        if ($alumnosIds !== null && !empty($alumnosIds)) {
            $qb->andWhere('u.id IN (:alumnosIds)')
                ->setParameter('alumnosIds', $alumnosIds);
        }

        $result = $qb->getQuery()->getResult();

        // Obtener usuarios en una sola consulta
        $usuarioIds = array_column($result, 'usuarioId');
        if (empty($usuarioIds)) {
            return [];
        }

        $usuarios = $this->getEntityManager()
            ->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $usuarioIds)
            ->getQuery()
            ->getResult();

        // Crear mapa de usuarios
        $usuariosMap = [];
        foreach ($usuarios as $usuario) {
            $usuariosMap[$usuario->getId()] = $usuario;
        }

        // Construir ranking manteniendo el orden
        $ranking = [];
        foreach ($result as $row) {
            $usuarioId = $row['usuarioId'];
            if (isset($usuariosMap[$usuarioId])) {
                $ranking[] = [
                    'usuario' => $usuariosMap[$usuarioId],
                    'cantidad' => (int) $row['cantidad'],
                ];
            }
        }

        return $ranking;
    }

    /**
     * Obtiene el historial de partidas de un usuario
     * @return PartidaJuego[]
     */
    public function getHistorialUsuario(User $usuario, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('pj')
            ->where('pj.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('pj.fechaCreacion', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtiene todas las partidas agrupadas por usuario y tipo de juego (para profesores/admins)
     * Filtra automáticamente usuarios activos y excluye profesores/admins
     * @param array|null $alumnosIds Si se proporciona, solo incluye estos alumnos (para profesores)
     * @return array Array con ['usuario' => User, 'partidas' => ['tipoJuego' => cantidad]]
     */
    public function getPartidasPorUsuario(?array $alumnosIds = null): array
    {
        $qb = $this->createQueryBuilder('pj')
            ->select('IDENTITY(pj.usuario) as usuarioId, pj.tipoJuego, COUNT(pj.id) as cantidad')
            ->innerJoin('pj.usuario', 'u')
            ->where('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->groupBy('pj.usuario, pj.tipoJuego')
            ->orderBy('pj.usuario', 'ASC');

        // Si se proporcionan IDs de alumnos, filtrar por ellos
        if ($alumnosIds !== null && !empty($alumnosIds)) {
            $qb->andWhere('u.id IN (:alumnosIds)')
                ->setParameter('alumnosIds', $alumnosIds);
        }

        $result = $qb->getQuery()->getResult();

        // Obtener usuarios únicos en una sola consulta
        $usuarioIds = array_unique(array_column($result, 'usuarioId'));
        if (empty($usuarioIds)) {
            return [];
        }

        $usuarios = $this->getEntityManager()
            ->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $usuarioIds)
            ->getQuery()
            ->getResult();

        // Crear mapa de usuarios
        $usuariosMap = [];
        foreach ($usuarios as $usuario) {
            $usuariosMap[$usuario->getId()] = $usuario;
        }

        // Agrupar partidas por usuario
        $partidasPorUsuario = [];
        foreach ($result as $row) {
            $usuarioId = $row['usuarioId'];
            if (!isset($usuariosMap[$usuarioId])) {
                continue;
            }
            
            if (!isset($partidasPorUsuario[$usuarioId])) {
                $partidasPorUsuario[$usuarioId] = [
                    'usuario' => $usuariosMap[$usuarioId],
                    'partidas' => [],
                ];
            }
            $partidasPorUsuario[$usuarioId]['partidas'][$row['tipoJuego']] = (int) $row['cantidad'];
        }

        return array_values($partidasPorUsuario);
    }
}
