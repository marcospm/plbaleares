<?php

namespace App\Repository;

use App\Entity\Examen;
use App\Entity\User;
use App\Entity\Municipio;
use App\Entity\Tema;
use App\Entity\TemaMunicipal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends ServiceEntityRepository<Examen>
 */
class ExamenRepository extends ServiceEntityRepository
{
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache = null)
    {
        parent::__construct($registry, Examen::class);
        $this->cache = $cache;
    }

    /**
     * @return Examen[] Returns an array of Examen objects
     */
    public function findByUsuario(User $usuario, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.usuario', 'u')
            ->addSelect('u')
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
     * OPTIMIZADO: Usa AVG() directamente en SQL cuando no hay filtro de tema
     * Si se proporciona un tema, solo considera exámenes íntegramente de ese tema (que tengan exactamente ese tema único)
     */
    public function getNotaMediaUsuario(User $usuario, string $dificultad, int $cantidadExamenes, ?Tema $tema = null): ?float
    {
        // Si no hay filtro de tema, podemos usar AVG() directamente en SQL con subconsulta
        if ($tema === null) {
            // Primero obtener los IDs de los últimos N exámenes
            $idsSubquery = $this->createQueryBuilder('e2')
                ->select('e2.id')
                ->where('e2.usuario = :usuario')
                ->andWhere('e2.dificultad = :dificultad')
                ->andWhere('e2.municipio IS NULL')
                ->setParameter('usuario', $usuario)
                ->setParameter('dificultad', $dificultad)
                ->orderBy('e2.fecha', 'DESC')
                ->setMaxResults($cantidadExamenes)
                ->getDQL();

            // Calcular AVG sobre esos IDs
            $result = $this->createQueryBuilder('e')
                ->select('AVG(e.nota) as promedio')
                ->where('e.id IN (' . $idsSubquery . ')')
                ->setParameter('usuario', $usuario)
                ->setParameter('dificultad', $dificultad)
                ->getQuery()
                ->getSingleScalarResult();

            return $result ? round((float) $result, 2) : null;
        }

        // Si hay tema, necesitamos filtrar en PHP porque debemos verificar que tenga exactamente 1 tema
        $qb = $this->createQueryBuilder('e')
            ->where('e.usuario = :usuario')
            ->andWhere('e.dificultad = :dificultad')
            ->andWhere('e.municipio IS NULL')
            ->setParameter('usuario', $usuario)
            ->setParameter('dificultad', $dificultad);

        $qb->innerJoin('e.temas', 't')
           ->andWhere('t.id = :temaId')
           ->setParameter('temaId', $tema->getId())
           ->groupBy('e.id')
           ->having('COUNT(t.id) = 1');

        $qb->orderBy('e.fecha', 'DESC')
           ->setMaxResults($cantidadExamenes * 2); // Obtener más para filtrar después

        $examenes = $qb->getQuery()->getResult();

        // Filtrar en PHP para asegurar que solo sean exámenes íntegramente del tema
        $examenes = array_filter($examenes, function($examen) use ($tema) {
            return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
        });

        // Tomar solo los primeros N después del filtro
        $examenes = array_slice($examenes, 0, $cantidadExamenes);

        if (empty($examenes)) {
            return null;
        }

        // Calcular promedio (en este caso es necesario en PHP por el filtro complejo)
        $suma = 0;
        foreach ($examenes as $examen) {
            $suma += (float) $examen->getNota();
        }

        return round($suma / count($examenes), 2);
    }

    /**
     * Obtiene el ranking de usuarios según su nota media de los últimos N exámenes por dificultad
     * OPTIMIZADO: Usa una sola consulta SQL en lugar de N+1 queries
     * Si se proporciona un tema, solo considera exámenes íntegramente de ese tema
     * @return array Array con ['usuario' => User, 'notaMedia' => float, 'cantidadExamenes' => int]
     */
    public function getRankingPorDificultad(string $dificultad, int $cantidadExamenes, ?Tema $tema = null): array
    {
        // Cache key único para este ranking
        $temaId = $tema ? $tema->getId() : 'all';
        $cacheKey = sprintf('ranking_dificultad_%s_%d_%s', $dificultad, $cantidadExamenes, $temaId);
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }
        // Obtener todos los exámenes relevantes en una sola consulta con eager loading de usuario
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.usuario', 'u')
            ->addSelect('u')
            ->leftJoin('e.temas', 't')
            ->addSelect('t')
            ->where('e.dificultad = :dificultad')
            ->andWhere('e.municipio IS NULL')
            ->andWhere('u.activo = :activo')
            ->setParameter('dificultad', $dificultad)
            ->setParameter('activo', true)
            ->orderBy('e.usuario', 'ASC')
            ->addOrderBy('e.fecha', 'DESC');

        if ($tema !== null) {
            $qb->andWhere('t.id = :temaId')
               ->setParameter('temaId', $tema->getId());
        }

        $examenes = $qb->getQuery()->getResult();

        // Si hay tema, filtrar solo exámenes íntegramente de ese tema
        if ($tema !== null) {
            $examenes = array_filter($examenes, function($examen) use ($tema) {
                return $examen->getTemas()->count() === 1 && $examen->getTemas()->contains($tema);
            });
        }

        // Agrupar exámenes por usuario y calcular promedio de los últimos N
        $examenesPorUsuario = [];
        foreach ($examenes as $examen) {
            $usuarioId = $examen->getUsuario()->getId();
            if (!isset($examenesPorUsuario[$usuarioId])) {
                $examenesPorUsuario[$usuarioId] = [
                    'usuario' => $examen->getUsuario(),
                    'examenes' => []
                ];
            }
            $examenesPorUsuario[$usuarioId]['examenes'][] = $examen;
        }

        // Calcular nota media para cada usuario (solo últimos N exámenes)
        // OPTIMIZADO: Usar array_sum y array_map para cálculos más eficientes
        $ranking = [];
        foreach ($examenesPorUsuario as $data) {
            $examenesUsuario = array_slice($data['examenes'], 0, $cantidadExamenes);
            
            if (empty($examenesUsuario)) {
                continue;
            }

            // Extraer notas y calcular promedio
            $notas = array_map(function($examen) {
                return (float) $examen->getNota();
            }, $examenesUsuario);
            
            $notaMedia = round(array_sum($notas) / count($notas), 2);

            $ranking[] = [
                'usuario' => $data['usuario'],
                'notaMedia' => $notaMedia,
                'cantidadExamenes' => count($examenesUsuario),
            ];
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

        // Calcular promedio (necesario en PHP por el filtro complejo de tema municipal único)
        $notas = array_map(function($examen) {
            return (float) $examen->getNota();
        }, $examenes);
        
        return round(array_sum($notas) / count($notas), 2);
    }

    /**
     * Obtiene el ranking de usuarios según su nota media de los últimos N exámenes por municipio y dificultad
     * OPTIMIZADO: Usa una sola consulta SQL en lugar de N+1 queries
     * Si se proporciona un tema municipal, solo considera exámenes íntegramente de ese tema
     * @return array Array con ['usuario' => User, 'notaMedia' => float, 'cantidadExamenes' => int]
     */
    public function getRankingPorMunicipioYDificultad(Municipio $municipio, string $dificultad, int $cantidadExamenes, ?TemaMunicipal $temaMunicipal = null): array
    {
        // Cache key único para este ranking
        $temaMunicipalId = $temaMunicipal ? $temaMunicipal->getId() : 'all';
        $cacheKey = sprintf('ranking_municipio_%d_%s_%d_%s', $municipio->getId(), $dificultad, $cantidadExamenes, $temaMunicipalId);
        
        $cacheItem = null;
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        // Obtener todos los exámenes relevantes en una sola consulta con eager loading de usuario
        // Solo usuarios que tienen acceso a este municipio
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.usuario', 'u')
            ->addSelect('u')
            ->innerJoin('u.convocatorias', 'c')
            ->innerJoin('c.municipios', 'm')
            ->leftJoin('e.temasMunicipales', 'tm')
            ->addSelect('tm')
            ->where('e.municipio = :municipio')
            ->andWhere('e.dificultad = :dificultad')
            ->andWhere('u.activo = :activo')
            ->andWhere('c.activo = :convocatoriaActiva')
            ->andWhere('m.id = :municipioId')
            ->setParameter('municipio', $municipio)
            ->setParameter('dificultad', $dificultad)
            ->setParameter('activo', true)
            ->setParameter('convocatoriaActiva', true)
            ->setParameter('municipioId', $municipio->getId())
            ->orderBy('e.usuario', 'ASC')
            ->addOrderBy('e.fecha', 'DESC');

        if ($temaMunicipal !== null) {
            $qb->andWhere('tm.id = :temaMunicipalId')
               ->setParameter('temaMunicipalId', $temaMunicipal->getId());
        }

        $examenes = $qb->getQuery()->getResult();

        // Si hay tema municipal, filtrar solo exámenes íntegramente de ese tema
        if ($temaMunicipal !== null) {
            $examenes = array_filter($examenes, function($examen) use ($temaMunicipal) {
                return $examen->getTemasMunicipales()->count() === 1 && $examen->getTemasMunicipales()->contains($temaMunicipal);
            });
        }

        // Agrupar exámenes por usuario y calcular promedio de los últimos N
        $examenesPorUsuario = [];
        foreach ($examenes as $examen) {
            $usuarioId = $examen->getUsuario()->getId();
            if (!isset($examenesPorUsuario[$usuarioId])) {
                $examenesPorUsuario[$usuarioId] = [
                    'usuario' => $examen->getUsuario(),
                    'examenes' => []
                ];
            }
            $examenesPorUsuario[$usuarioId]['examenes'][] = $examen;
        }

        // Calcular nota media para cada usuario (solo últimos N exámenes)
        // OPTIMIZADO: Usar array_sum y array_map para cálculos más eficientes
        $ranking = [];
        foreach ($examenesPorUsuario as $data) {
            $examenesUsuario = array_slice($data['examenes'], 0, $cantidadExamenes);
            
            if (empty($examenesUsuario)) {
                continue;
            }

            // Extraer notas y calcular promedio
            $notas = array_map(function($examen) {
                return (float) $examen->getNota();
            }, $examenesUsuario);
            
            $notaMedia = round(array_sum($notas) / count($notas), 2);

            $ranking[] = [
                'usuario' => $data['usuario'],
                'notaMedia' => $notaMedia,
                'cantidadExamenes' => count($examenesUsuario),
            ];
        }

        // Ordenar por nota media descendente
        usort($ranking, function($a, $b) {
            if ($a['notaMedia'] == $b['notaMedia']) {
                return 0;
            }
            return ($a['notaMedia'] > $b['notaMedia']) ? -1 : 1;
        });

        // Guardar en cache (TTL: 10 minutos para rankings)
        if ($this->cache && $cacheItem) {
            $cacheItem->set($ranking);
            $cacheItem->expiresAfter(600); // 10 minutos
            $this->cache->save($cacheItem);
        }

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

    /**
     * Obtiene la nota media de un usuario para los últimos N exámenes de una convocatoria específica
     * OPTIMIZADO: Usa AVG() directamente en SQL
     */
    public function getNotaMediaUsuarioPorConvocatoria(User $usuario, \App\Entity\Convocatoria $convocatoria, string $dificultad, int $cantidadExamenes, ?\App\Entity\Municipio $municipio = null): ?float
    {
        // Primero obtener los IDs de los últimos N exámenes
        $idsSubquery = $this->createQueryBuilder('e2')
            ->select('e2.id')
            ->where('e2.usuario = :usuario')
            ->andWhere('e2.convocatoria = :convocatoria')
            ->andWhere('e2.dificultad = :dificultad')
            ->setParameter('usuario', $usuario)
            ->setParameter('convocatoria', $convocatoria)
            ->setParameter('dificultad', $dificultad);
        
        if ($municipio) {
            $idsSubquery->andWhere('e2.municipio = :municipio')
                       ->setParameter('municipio', $municipio);
        }
        
        $idsSubquery->orderBy('e2.fecha', 'DESC')
                    ->setMaxResults($cantidadExamenes);

        // Calcular AVG sobre esos IDs
        $avgQuery = $this->createQueryBuilder('e')
            ->select('AVG(e.nota) as promedio')
            ->where('e.id IN (' . $idsSubquery->getDQL() . ')')
            ->setParameter('usuario', $usuario)
            ->setParameter('convocatoria', $convocatoria)
            ->setParameter('dificultad', $dificultad);

        if ($municipio) {
            $avgQuery->setParameter('municipio', $municipio);
        }

        $result = $avgQuery->getQuery()->getSingleScalarResult();

        return $result ? round((float) $result, 2) : null;
    }

    /**
     * Obtiene el ranking de usuarios según su nota media de los últimos N exámenes por convocatoria y dificultad
     * OPTIMIZADO: Usa una sola consulta SQL en lugar de N+1 queries
     * @return array Array con ['usuario' => User, 'notaMedia' => float, 'cantidadExamenes' => int]
     */
    public function getRankingPorConvocatoriaYDificultad(\App\Entity\Convocatoria $convocatoria, string $dificultad, int $cantidadExamenes, ?\App\Entity\Municipio $municipio = null): array
    {
        // Cache key único para este ranking
        $municipioId = $municipio ? $municipio->getId() : 'all';
        $cacheKey = sprintf('ranking_convocatoria_%d_%s_%d_%s', $convocatoria->getId(), $dificultad, $cantidadExamenes, $municipioId);
        
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }
        // Obtener todos los exámenes relevantes en una sola consulta con eager loading de usuario
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.usuario', 'u')
            ->addSelect('u')
            ->innerJoin('u.convocatorias', 'c')
            ->where('e.convocatoria = :convocatoria')
            ->andWhere('e.dificultad = :dificultad')
            ->andWhere('u.activo = :activo')
            ->andWhere('c.id = :convocatoriaId')
            ->setParameter('convocatoria', $convocatoria)
            ->setParameter('dificultad', $dificultad)
            ->setParameter('activo', true)
            ->setParameter('convocatoriaId', $convocatoria->getId())
            ->orderBy('e.usuario', 'ASC')
            ->addOrderBy('e.fecha', 'DESC');
        
        if ($municipio) {
            $qb->andWhere('e.municipio = :municipio')
               ->setParameter('municipio', $municipio);
        }

        $examenes = $qb->getQuery()->getResult();

        // Agrupar exámenes por usuario y calcular promedio de los últimos N
        $examenesPorUsuario = [];
        foreach ($examenes as $examen) {
            $usuarioId = $examen->getUsuario()->getId();
            if (!isset($examenesPorUsuario[$usuarioId])) {
                $examenesPorUsuario[$usuarioId] = [
                    'usuario' => $examen->getUsuario(),
                    'examenes' => []
                ];
            }
            $examenesPorUsuario[$usuarioId]['examenes'][] = $examen;
        }

        // Calcular nota media para cada usuario (solo últimos N exámenes)
        // OPTIMIZADO: Usar array_sum y array_map para cálculos más eficientes
        $ranking = [];
        foreach ($examenesPorUsuario as $data) {
            $examenesUsuario = array_slice($data['examenes'], 0, $cantidadExamenes);
            
            if (empty($examenesUsuario)) {
                continue;
            }

            // Extraer notas y calcular promedio
            $notas = array_map(function($examen) {
                return (float) $examen->getNota();
            }, $examenesUsuario);
            
            $notaMedia = round(array_sum($notas) / count($notas), 2);

            $ranking[] = [
                'usuario' => $data['usuario'],
                'notaMedia' => $notaMedia,
                'cantidadExamenes' => count($examenesUsuario),
            ];
        }

        // Ordenar por nota media descendente
        usort($ranking, function($a, $b) {
            if ($a['notaMedia'] == $b['notaMedia']) {
                return 0;
            }
            return ($a['notaMedia'] > $b['notaMedia']) ? -1 : 1;
        });

        // Guardar en cache (TTL: 10 minutos para rankings)
        if ($this->cache && $cacheItem) {
            $cacheItem->set($ranking);
            $cacheItem->expiresAfter(600); // 10 minutos
            $this->cache->save($cacheItem);
        }

        return $ranking;
    }

    /**
     * Obtiene la posición de un usuario en el ranking por convocatoria y dificultad
     */
    public function getPosicionUsuarioPorConvocatoria(User $usuario, \App\Entity\Convocatoria $convocatoria, string $dificultad, int $cantidadExamenes, ?\App\Entity\Municipio $municipio = null): ?int
    {
        $ranking = $this->getRankingPorConvocatoriaYDificultad($convocatoria, $dificultad, $cantidadExamenes, $municipio);
        
        foreach ($ranking as $index => $entry) {
            if ($entry['usuario']->getId() === $usuario->getId()) {
                return $index + 1; // Posición (empezando en 1)
            }
        }

        return null; // Usuario no está en el ranking
    }
}

