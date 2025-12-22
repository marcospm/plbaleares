<?php

namespace App\Repository;

use App\Entity\Examen;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Examen>
 */
class ExamenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Examen::class);
    }

    /**
     * @return Examen[] Returns an array of Examen objects
     */
    public function findByUsuario(User $usuario, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('e.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getEstadisticasUsuario(User $usuario): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) as total', 'AVG(e.nota) as promedio', 'MAX(e.nota) as mejorNota')
            ->andWhere('e.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $qb['total'],
            'promedio' => $qb['promedio'] ? round((float) $qb['promedio'], 2) : 0,
            'mejorNota' => $qb['mejorNota'] ? round((float) $qb['mejorNota'], 2) : 0,
        ];
    }

    /**
     * Obtiene la nota media de un usuario para los últimos N exámenes de una dificultad específica
     */
    public function getNotaMediaUsuario(User $usuario, string $dificultad, int $cantidadExamenes): ?float
    {
        $examenes = $this->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.dificultad = :dificultad')
            ->setParameter('usuario', $usuario)
            ->setParameter('dificultad', $dificultad)
            ->orderBy('e.fecha', 'DESC')
            ->setMaxResults($cantidadExamenes)
            ->getQuery()
            ->getResult();

        if (empty($examenes)) {
            return null;
        }

        $suma = 0;
        foreach ($examenes as $examen) {
            $suma += (float) $examen->getNota();
        }

        return round($suma / count($examenes), 2);
    }

    /**
     * Obtiene el ranking de usuarios según su nota media de los últimos N exámenes por dificultad
     * @return array Array con ['usuario' => User, 'notaMedia' => float, 'cantidadExamenes' => int]
     */
    public function getRankingPorDificultad(string $dificultad, int $cantidadExamenes): array
    {
        // Obtener todos los usuarios activos
        $usuarios = $this->getEntityManager()
            ->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->where('u.activo = :activo')
            ->setParameter('activo', true)
            ->getQuery()
            ->getResult();

        $ranking = [];
        foreach ($usuarios as $usuario) {
            $notaMedia = $this->getNotaMediaUsuario($usuario, $dificultad, $cantidadExamenes);
            if ($notaMedia !== null) {
                // Contar cuántos exámenes tiene realmente
                $examenesReales = $this->createQueryBuilder('e')
                    ->where('e.usuario = :usuario')
                    ->andWhere('e.dificultad = :dificultad')
                    ->setParameter('usuario', $usuario)
                    ->setParameter('dificultad', $dificultad)
                    ->orderBy('e.fecha', 'DESC')
                    ->setMaxResults($cantidadExamenes)
                    ->getQuery()
                    ->getResult();

                $ranking[] = [
                    'usuario' => $usuario,
                    'notaMedia' => $notaMedia,
                    'cantidadExamenes' => count($examenesReales),
                ];
            }
        }

        // Ordenar por nota media descendente
        usort($ranking, function($a, $b) {
            if ($a['notaMedia'] == $b['notaMedia']) {
                return 0;
            }
            return ($a['notaMedia'] > $b['notaMedia']) ? -1 : 1;
        });

        return $ranking;
    }

    /**
     * Obtiene la posición de un usuario en el ranking por dificultad
     */
    public function getPosicionUsuario(User $usuario, string $dificultad, int $cantidadExamenes): ?int
    {
        $ranking = $this->getRankingPorDificultad($dificultad, $cantidadExamenes);
        
        foreach ($ranking as $index => $entry) {
            if ($entry['usuario']->getId() === $usuario->getId()) {
                return $index + 1; // Posición (empezando en 1)
            }
        }

        return null; // Usuario no está en el ranking
    }
}

