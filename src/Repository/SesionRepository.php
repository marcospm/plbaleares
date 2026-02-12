<?php

namespace App\Repository;

use App\Entity\Sesion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sesion>
 */
class SesionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sesion::class);
    }

    /**
     * Busca sesiones con paginación y filtros para alumnos
     * @param string|null $search Término de búsqueda (nombre)
     * @param int|null $temaId Filtro por tema general
     * @param int|null $temaMunicipalId Filtro por tema municipal
     * @param int|null $municipioId Filtro por municipio
     * @param int|null $convocatoriaId Filtro por convocatoria
     * @param int $page Número de página (empezando en 1)
     * @param int $itemsPerPage Items por página
     * @return array ['sesiones' => Sesion[], 'total' => int]
     */
    public function findPaginatedForAlumno(
        ?string $search = null,
        ?int $temaId = null,
        ?int $temaMunicipalId = null,
        ?int $municipioId = null,
        ?int $convocatoriaId = null,
        int $page = 1,
        int $itemsPerPage = 20
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.temas', 't')
            ->leftJoin('s.temasMunicipales', 'tm')
            ->leftJoin('s.municipios', 'm')
            ->leftJoin('s.convocatorias', 'c')
            ->addSelect('t', 'tm', 'm', 'c');
        
        // Filtro por búsqueda
        if (!empty($search)) {
            $qb->andWhere('s.nombre LIKE :search OR s.descripcion LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtro por tema general
        if ($temaId !== null) {
            $qb->andWhere('t.id = :temaId')
               ->setParameter('temaId', $temaId);
        }
        
        // Filtro por tema municipal
        if ($temaMunicipalId !== null) {
            $qb->andWhere('tm.id = :temaMunicipalId')
               ->setParameter('temaMunicipalId', $temaMunicipalId);
        }
        
        // Filtro por municipio
        if ($municipioId !== null) {
            $qb->andWhere('m.id = :municipioId')
               ->setParameter('municipioId', $municipioId);
        }
        
        // Filtro por convocatoria
        if ($convocatoriaId !== null) {
            $qb->andWhere('c.id = :convocatoriaId')
               ->setParameter('convocatoriaId', $convocatoriaId);
        }
        
        // Contar total antes de paginar
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(s.id)')
                               ->getQuery()
                               ->getSingleScalarResult();
        
        // Aplicar paginación - ordenar por fecha de creación descendente (más recientes primero)
        $offset = ($page - 1) * $itemsPerPage;
        $qb->orderBy('s.fechaCreacion', 'DESC')
           ->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);
        
        return [
            'sesiones' => $qb->getQuery()->getResult(),
            'total' => $total,
        ];
    }

    /**
     * Busca sesiones con paginación y filtros para profesores
     * @param string|null $search Término de búsqueda (nombre)
     * @param User|null $creadoPor Filtro por creador
     * @param int $page Número de página (empezando en 1)
     * @param int $itemsPerPage Items por página
     * @return array ['sesiones' => Sesion[], 'total' => int]
     */
    /**
     * Busca sesiones con paginación y filtros para profesores
     * @param string|null $search Término de búsqueda (nombre)
     * @param int|null $temaId Filtro por tema general
     * @param int|null $temaMunicipalId Filtro por tema municipal
     * @param int|null $municipioId Filtro por municipio
     * @param int|null $convocatoriaId Filtro por convocatoria
     * @param User|null $creadoPor Filtro por creador
     * @param int $page Número de página (empezando en 1)
     * @param int $itemsPerPage Items por página
     * @return array ['sesiones' => Sesion[], 'total' => int]
     */
    public function findPaginatedForProfesor(
        ?string $search = null,
        ?int $temaId = null,
        ?int $temaMunicipalId = null,
        ?int $municipioId = null,
        ?int $convocatoriaId = null,
        ?User $creadoPor = null,
        int $page = 1,
        int $itemsPerPage = 20
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.temas', 't')
            ->leftJoin('s.temasMunicipales', 'tm')
            ->leftJoin('s.municipios', 'm')
            ->leftJoin('s.convocatorias', 'c')
            ->leftJoin('s.creadoPor', 'u')
            ->addSelect('t', 'tm', 'm', 'c', 'u');
        
        // Filtro por búsqueda
        if (!empty($search)) {
            $qb->andWhere('s.nombre LIKE :search OR s.descripcion LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtro por tema general
        if ($temaId !== null) {
            $qb->andWhere('t.id = :temaId')
               ->setParameter('temaId', $temaId);
        }
        
        // Filtro por tema municipal
        if ($temaMunicipalId !== null) {
            $qb->andWhere('tm.id = :temaMunicipalId')
               ->setParameter('temaMunicipalId', $temaMunicipalId);
        }
        
        // Filtro por municipio
        if ($municipioId !== null) {
            $qb->andWhere('m.id = :municipioId')
               ->setParameter('municipioId', $municipioId);
        }
        
        // Filtro por convocatoria
        if ($convocatoriaId !== null) {
            $qb->andWhere('c.id = :convocatoriaId')
               ->setParameter('convocatoriaId', $convocatoriaId);
        }
        
        // Filtro por creador
        if ($creadoPor !== null) {
            $qb->andWhere('s.creadoPor = :creadoPor')
               ->setParameter('creadoPor', $creadoPor);
        }
        
        // Contar total antes de paginar
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(DISTINCT s.id)')
                               ->getQuery()
                               ->getSingleScalarResult();
        
        // Aplicar paginación - ordenar por fecha de creación descendente
        $offset = ($page - 1) * $itemsPerPage;
        $qb->orderBy('s.fechaCreacion', 'DESC')
           ->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);
        
        return [
            'sesiones' => $qb->getQuery()->getResult(),
            'total' => $total,
        ];
    }
}
