<?php

namespace App\Repository;

use App\Entity\Examen;
use App\Entity\User;
use App\Entity\Municipio;
use App\Entity\Tema;
use App\Entity\TemaMunicipal;
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
     * Si se proporciona un tema, solo considera exámenes íntegramente de ese tema (que tengan exactamente ese tema único)
     */
    public function getNotaMediaUsuario(User $usuario, string $dificultad, int $cantidadExamenes, ?Tema $tema = null): ?float
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.dificultad = :dificultad')
            ->andWhere('e.municipio IS NULL') // Solo exámenes generales (no municipales)
            ->setParameter('usuario', $usuario)
            ->setParameter('dificultad', $dificultad);

        // Si hay un tema específico, filtrar solo exámenes íntegramente de ese tema
        if ($tema !== null) {
            $qb->innerJoin('e.temas', 't')
               ->andWhere('t.id = :temaId')
               ->setParameter('temaId', $tema->getId())
               ->groupBy('e.id')
               ->having('COUNT(t.id) = 1'); // Solo exámenes con exactamente 1 tema (ese tema)
        }

        $qb->orderBy('e.fecha', 'DESC')
           ->setMaxResults($cantidadExamenes);

        $examenes = $qb->getQuery()->getResult();

        // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema
        if ($tema !== null) {
            $examenes = array_filter($examenes, function($examen) use ($tema) {
                return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
            });
        }

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
     * Si se proporciona un tema, solo considera exámenes íntegramente de ese tema
     * @return array Array con ['usuario' => User, 'notaMedia' => float, 'cantidadExamenes' => int]
     */
    public function getRankingPorDificultad(string $dificultad, int $cantidadExamenes, ?Tema $tema = null): array
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
            $notaMedia = $this->getNotaMediaUsuario($usuario, $dificultad, $cantidadExamenes, $tema);
            if ($notaMedia !== null) {
                // Contar cuántos exámenes tiene realmente
                $qb = $this->createQueryBuilder('e')
                    ->where('e.usuario = :usuario')
                    ->andWhere('e.dificultad = :dificultad')
                    ->andWhere('e.municipio IS NULL')
                    ->setParameter('usuario', $usuario)
                    ->setParameter('dificultad', $dificultad);

                if ($tema !== null) {
                    $qb->innerJoin('e.temas', 't')
                       ->andWhere('t.id = :temaId')
                       ->setParameter('temaId', $tema->getId())
                       ->groupBy('e.id')
                       ->having('COUNT(t.id) = 1');
                }

                $examenesReales = $qb->orderBy('e.fecha', 'DESC')
                    ->setMaxResults($cantidadExamenes)
                    ->getQuery()
                    ->getResult();

                // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema
                if ($tema !== null) {
                    $examenesReales = array_filter($examenesReales, function($examen) use ($tema) {
                        return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
                    });
                }

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
    public function getPosicionUsuario(User $usuario, string $dificultad, int $cantidadExamenes, ?Tema $tema = null): ?int
    {
        $ranking = $this->getRankingPorDificultad($dificultad, $cantidadExamenes, $tema);
        
        foreach ($ranking as $index => $entry) {
            if ($entry['usuario']->getId() === $usuario->getId()) {
                return $index + 1; // Posición (empezando en 1)
            }
        }

        return null; // Usuario no está en el ranking
    }

    /**
     * Obtiene la nota media de un usuario para los últimos N exámenes de un municipio específico
     * Si se proporciona un tema municipal, solo considera exámenes íntegramente de ese tema
     */
    public function getNotaMediaUsuarioPorMunicipio(User $usuario, Municipio $municipio, string $dificultad, int $cantidadExamenes, ?TemaMunicipal $temaMunicipal = null): ?float
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.municipio = :municipio')
            ->andWhere('e.dificultad = :dificultad')
            ->setParameter('usuario', $usuario)
            ->setParameter('municipio', $municipio)
            ->setParameter('dificultad', $dificultad);

        // Si hay un tema municipal específico, filtrar solo exámenes íntegramente de ese tema
        if ($temaMunicipal !== null) {
            $qb->innerJoin('e.temasMunicipales', 'tm')
               ->andWhere('tm.id = :temaMunicipalId')
               ->setParameter('temaMunicipalId', $temaMunicipal->getId())
               ->groupBy('e.id')
               ->having('COUNT(tm.id) = 1'); // Solo exámenes con exactamente 1 tema municipal (ese tema)
        }

        $qb->orderBy('e.fecha', 'DESC')
           ->setMaxResults($cantidadExamenes);

        $examenes = $qb->getQuery()->getResult();

        // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema municipal
        if ($temaMunicipal !== null) {
            $examenes = array_filter($examenes, function($examen) use ($temaMunicipal) {
                return $examen->getTemasMunicipales()->count() === 1 && $examen->getTemasMunicipales()->contains($temaMunicipal);
            });
        }

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
     * Obtiene el ranking de usuarios según su nota media de los últimos N exámenes por municipio y dificultad
     * Si se proporciona un tema municipal, solo considera exámenes íntegramente de ese tema
     * @return array Array con ['usuario' => User, 'notaMedia' => float, 'cantidadExamenes' => int]
     */
    public function getRankingPorMunicipioYDificultad(Municipio $municipio, string $dificultad, int $cantidadExamenes, ?TemaMunicipal $temaMunicipal = null): array
    {
        // Obtener usuarios que tienen este municipio activado
        $usuarios = $this->getEntityManager()
            ->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.municipios', 'm')
            ->where('u.activo = :activo')
            ->andWhere('m.id = :municipioId')
            ->setParameter('activo', true)
            ->setParameter('municipioId', $municipio->getId())
            ->getQuery()
            ->getResult();

        $ranking = [];
        foreach ($usuarios as $usuario) {
            $notaMedia = $this->getNotaMediaUsuarioPorMunicipio($usuario, $municipio, $dificultad, $cantidadExamenes, $temaMunicipal);
            if ($notaMedia !== null) {
                // Contar cuántos exámenes tiene realmente
                $qb = $this->createQueryBuilder('e')
                    ->where('e.usuario = :usuario')
                    ->andWhere('e.municipio = :municipio')
                    ->andWhere('e.dificultad = :dificultad')
                    ->setParameter('usuario', $usuario)
                    ->setParameter('municipio', $municipio)
                    ->setParameter('dificultad', $dificultad);

                if ($temaMunicipal !== null) {
                    $qb->innerJoin('e.temasMunicipales', 'tm')
                       ->andWhere('tm.id = :temaMunicipalId')
                       ->setParameter('temaMunicipalId', $temaMunicipal->getId())
                       ->groupBy('e.id')
                       ->having('COUNT(tm.id) = 1');
                }

                $examenesReales = $qb->orderBy('e.fecha', 'DESC')
                    ->setMaxResults($cantidadExamenes)
                    ->getQuery()
                    ->getResult();

                // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema municipal
                if ($temaMunicipal !== null) {
                    $examenesReales = array_filter($examenesReales, function($examen) use ($temaMunicipal) {
                        return $examen->getTemasMunicipales()->count() === 1 && $examen->getTemasMunicipales()->contains($temaMunicipal);
                    });
                }

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
     * Obtiene la posición de un usuario en el ranking por municipio y dificultad
     */
    public function getPosicionUsuarioPorMunicipio(User $usuario, Municipio $municipio, string $dificultad, int $cantidadExamenes, ?TemaMunicipal $temaMunicipal = null): ?int
    {
        $ranking = $this->getRankingPorMunicipioYDificultad($municipio, $dificultad, $cantidadExamenes, $temaMunicipal);
        
        foreach ($ranking as $index => $entry) {
            if ($entry['usuario']->getId() === $usuario->getId()) {
                return $index + 1; // Posición (empezando en 1)
            }
        }

        return null; // Usuario no está en el ranking
    }

    /**
     * Obtiene estadísticas de un usuario para un municipio específico
     */
    public function getEstadisticasUsuarioPorMunicipio(User $usuario, Municipio $municipio): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) as total', 'AVG(e.nota) as promedio', 'MAX(e.nota) as mejorNota')
            ->andWhere('e.usuario = :usuario')
            ->andWhere('e.municipio = :municipio')
            ->setParameter('usuario', $usuario)
            ->setParameter('municipio', $municipio)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $qb['total'],
            'promedio' => $qb['promedio'] ? round((float) $qb['promedio'], 2) : 0,
            'mejorNota' => $qb['mejorNota'] ? round((float) $qb['mejorNota'], 2) : 0,
        ];
    }
}

