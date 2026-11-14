<?php

namespace App\Repository;

use App\Entity\ArchivoPsicotecnico;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArchivoPsicotecnico>
 */
class ArchivoPsicotecnicoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArchivoPsicotecnico::class);
    }

    /**
     * @return list<ArchivoPsicotecnico>
     */
    public function findParaAdmin(?int $categoriaId = null, ?string $search = null): array
    {
        $ids = $this->buscarIds($categoriaId, $search, false);
        if ($ids === []) {
            return [];
        }

        return $this->cargarPorIds($ids);
    }

    /**
     * Archivos que tienen al menos una categoría activa.
     *
     * @return list<ArchivoPsicotecnico>
     */
    public function findVisibles(?int $categoriaId = null, ?string $search = null): array
    {
        $ids = $this->buscarIds($categoriaId, $search, true);
        if ($ids === []) {
            return [];
        }

        return $this->cargarPorIds($ids);
    }

    /**
     * @return list<array{nombre: string, descripcion: ?string, fecha: ?\DateTimeInterface, archivos: list<ArchivoPsicotecnico>}>
     */
    public function findGruposParaAdmin(?int $categoriaId = null, ?string $search = null): array
    {
        return $this->agrupar($this->completarLotes($this->findParaAdmin($categoriaId, $search)));
    }

    /**
     * @return list<array{nombre: string, descripcion: ?string, fecha: ?\DateTimeInterface, archivos: list<ArchivoPsicotecnico>}>
     */
    public function findGruposVisibles(?int $categoriaId = null, ?string $search = null): array
    {
        return $this->agrupar($this->completarLotes($this->findVisibles($categoriaId, $search)));
    }

    /**
     * @return list<ArchivoPsicotecnico>
     */
    public function findByLote(string $lote): array
    {
        return $this->findByLotes([$lote]);
    }

    /**
     * @param list<string> $lotes
     * @return list<ArchivoPsicotecnico>
     */
    public function findByLotes(array $lotes): array
    {
        if ($lotes === []) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->leftJoin('a.categorias', 'c')
            ->addSelect('c')
            ->where('a.lote IN (:lotes)')
            ->setParameter('lotes', $lotes)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<ArchivoPsicotecnico> $archivos
     * @return list<ArchivoPsicotecnico>
     */
    private function completarLotes(array $archivos): array
    {
        $porId = [];
        $lotes = [];
        foreach ($archivos as $archivo) {
            $id = $archivo->getId();
            if ($id !== null) {
                $porId[$id] = $archivo;
            }
            $lote = $archivo->getLote();
            if ($lote !== null && $lote !== '') {
                $lotes[$lote] = $lote;
            }
        }

        if ($lotes !== []) {
            foreach ($this->findByLotes(array_values($lotes)) as $extra) {
                $id = $extra->getId();
                if ($id !== null) {
                    $porId[$id] = $extra;
                }
            }
        }

        return array_values($porId);
    }

    /**
     * @param list<ArchivoPsicotecnico> $archivos
     * @return list<array{nombre: string, descripcion: ?string, fecha: ?\DateTimeInterface, archivos: list<ArchivoPsicotecnico>}>
     */
    private function agrupar(array $archivos): array
    {
        $grupos = [];
        foreach ($archivos as $archivo) {
            $lote = $archivo->getLote();
            $clave = ($lote !== null && $lote !== '') ? 'lote-' . $lote : 'archivo-' . $archivo->getId();
            $grupos[$clave][] = $archivo;
        }

        $resultado = [];
        foreach ($grupos as $items) {
            usort($items, static fn (ArchivoPsicotecnico $a, ArchivoPsicotecnico $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));
            $primero = $items[0];
            $fecha = $primero->getFechaCreacion();
            foreach ($items as $item) {
                $fechaItem = $item->getFechaCreacion();
                if ($fecha === null || ($fechaItem !== null && $fechaItem > $fecha)) {
                    $fecha = $fechaItem;
                }
            }
            $resultado[] = [
                'nombre' => $primero->getNombre() ?? '',
                'descripcion' => $primero->getDescripcion(),
                'fecha' => $fecha,
                'archivos' => $items,
            ];
        }

        usort($resultado, static function (array $a, array $b): int {
            $marcaA = $a['fecha'] instanceof \DateTimeInterface ? $a['fecha']->getTimestamp() : 0;
            $marcaB = $b['fecha'] instanceof \DateTimeInterface ? $b['fecha']->getTimestamp() : 0;

            return $marcaB <=> $marcaA;
        });

        return $resultado;
    }

    /**
     * @return list<int>
     */
    private function buscarIds(?int $categoriaId, ?string $search, bool $soloCategoriasActivas): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.id AS id')
            ->distinct()
            ->innerJoin('a.categorias', 'c');

        if ($soloCategoriasActivas) {
            $qb->andWhere('c.activo = true');
        }

        if ($categoriaId !== null) {
            $qb->andWhere('c.id = :categoriaId')
                ->setParameter('categoriaId', $categoriaId);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('a.nombre LIKE :search OR a.descripcion LIKE :search OR a.nombreArchivoOriginal LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $filas = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $fila): int => (int) $fila['id'], $filas);
    }

    /**
     * @param list<int> $ids
     * @return list<ArchivoPsicotecnico>
     */
    private function cargarPorIds(array $ids): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.categorias', 'c')
            ->addSelect('c')
            ->leftJoin('a.creadoPor', 'u')
            ->addSelect('u')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('a.fechaCreacion', 'DESC')
            ->addOrderBy('c.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
