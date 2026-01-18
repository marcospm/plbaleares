<?php

namespace App\Repository;

use App\Entity\PreguntaRiesgo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreguntaRiesgo>
 */
class PreguntaRiesgoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreguntaRiesgo::class);
    }

    /**
     * Encuentra una pregunta de riesgo por usuario y pregunta (general)
     */
    public function findByUsuarioYPregunta(User $usuario, int $preguntaId): ?PreguntaRiesgo
    {
        return $this->createQueryBuilder('pr')
            ->innerJoin('pr.pregunta', 'p')
            ->where('pr.usuario = :usuario')
            ->andWhere('p.id = :preguntaId')
            ->andWhere('pr.preguntaMunicipal IS NULL')
            ->setParameter('usuario', $usuario)
            ->setParameter('preguntaId', $preguntaId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra una pregunta de riesgo por usuario y pregunta municipal
     */
    public function findByUsuarioYPreguntaMunicipal(User $usuario, int $preguntaMunicipalId): ?PreguntaRiesgo
    {
        return $this->createQueryBuilder('pr')
            ->innerJoin('pr.preguntaMunicipal', 'pm')
            ->where('pr.usuario = :usuario')
            ->andWhere('pm.id = :preguntaMunicipalId')
            ->andWhere('pr.pregunta IS NULL')
            ->setParameter('usuario', $usuario)
            ->setParameter('preguntaMunicipalId', $preguntaMunicipalId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtiene todas las preguntas de riesgo de un usuario con sus temas
     * Retorna un array con ['tema_id' => ['total' => int, 'acertadas' => int]]
     */
    public function findEstadisticasPorTema(User $usuario, array $temaIds = []): array
    {
        $qb = $this->createQueryBuilder('pr')
            ->select('t.id as tema_id')
            ->addSelect('COUNT(pr.id) as total')
            ->addSelect('SUM(CASE WHEN pr.acertada = true THEN 1 ELSE 0 END) as acertadas')
            ->innerJoin('pr.pregunta', 'p')
            ->innerJoin('p.tema', 't')
            ->where('pr.usuario = :usuario')
            ->andWhere('pr.pregunta IS NOT NULL')
            ->andWhere('pr.preguntaMunicipal IS NULL')
            ->setParameter('usuario', $usuario)
            ->groupBy('t.id');

        if (!empty($temaIds)) {
            $qb->andWhere('t.id IN (:temaIds)')
                ->setParameter('temaIds', $temaIds);
        }

        $result = $qb->getQuery()->getResult();

        $estadisticas = [];
        foreach ($result as $row) {
            $estadisticas[$row['tema_id']] = [
                'total' => (int) $row['total'],
                'acertadas' => (int) $row['acertadas'],
            ];
        }

        return $estadisticas;
    }

    /**
     * Obtiene todas las preguntas de riesgo de un usuario con sus temas municipales
     * Retorna un array con ['tema_municipal_id' => ['total' => int, 'acertadas' => int]]
     */
    public function findEstadisticasPorTemaMunicipal(User $usuario, array $temaMunicipalIds = []): array
    {
        $qb = $this->createQueryBuilder('pr')
            ->select('tm.id as tema_municipal_id')
            ->addSelect('COUNT(pr.id) as total')
            ->addSelect('SUM(CASE WHEN pr.acertada = true THEN 1 ELSE 0 END) as acertadas')
            ->innerJoin('pr.preguntaMunicipal', 'pm')
            ->innerJoin('pm.temaMunicipal', 'tm')
            ->where('pr.usuario = :usuario')
            ->andWhere('pr.preguntaMunicipal IS NOT NULL')
            ->andWhere('pr.pregunta IS NULL')
            ->setParameter('usuario', $usuario)
            ->groupBy('tm.id');

        if (!empty($temaMunicipalIds)) {
            $qb->andWhere('tm.id IN (:temaMunicipalIds)')
                ->setParameter('temaMunicipalIds', $temaMunicipalIds);
        }

        $result = $qb->getQuery()->getResult();

        $estadisticas = [];
        foreach ($result as $row) {
            $estadisticas[$row['tema_municipal_id']] = [
                'total' => (int) $row['total'],
                'acertadas' => (int) $row['acertadas'],
            ];
        }

        return $estadisticas;
    }

    /**
     * Obtiene los IDs de preguntas marcadas como riesgo por el usuario
     * Retorna array con IDs de preguntas generales
     */
    public function findPreguntasMarcadasComoRiesgo(User $usuario): array
    {
        $result = $this->createQueryBuilder('pr')
            ->select('IDENTITY(pr.pregunta) as pregunta_id')
            ->where('pr.usuario = :usuario')
            ->andWhere('pr.pregunta IS NOT NULL')
            ->setParameter('usuario', $usuario)
            ->getQuery()
            ->getResult();

        return array_filter(array_map(function($row) {
            return $row['pregunta_id'];
        }, $result));
    }

    /**
     * Obtiene los IDs de preguntas municipales marcadas como riesgo por el usuario
     * Retorna array con IDs de preguntas municipales
     */
    public function findPreguntasMunicipalesMarcadasComoRiesgo(User $usuario): array
    {
        $result = $this->createQueryBuilder('pr')
            ->select('IDENTITY(pr.preguntaMunicipal) as pregunta_municipal_id')
            ->where('pr.usuario = :usuario')
            ->andWhere('pr.preguntaMunicipal IS NOT NULL')
            ->setParameter('usuario', $usuario)
            ->getQuery()
            ->getResult();

        return array_filter(array_map(function($row) {
            return $row['pregunta_municipal_id'];
        }, $result));
    }
}
