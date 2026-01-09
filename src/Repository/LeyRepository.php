<?php

namespace App\Repository;

use App\Entity\Ley;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ley>
 */
class LeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ley::class);
    }

    /**
     * Encuentra todas las leyes activas que tienen el formato "número/número, de día de mes"
     * Ejemplo: "20/2006, de 15 de diciembre" o "Ley 20/2006, de 15 de diciembre"
     */
    public function findLeyesConFormatoFecha(): array
    {
        // Excluir ley "Accidentes de Tráfico"
        $qb = $this->createQueryBuilder('l')
            ->where('l.activo = :activo')
            ->andWhere('l.nombre != :nombreLeyExcluida')
            ->setParameter('activo', true)
            ->setParameter('nombreLeyExcluida', 'Accidentes de Tráfico')
            ->orderBy('l.nombre', 'ASC');

        $leyes = $qb->getQuery()->getResult();
        
        // Filtrar leyes que coincidan con el patrón: número/número, de día de mes
        // Patrón más flexible: puede empezar con "Ley" o no, y permite espacios variables
        // Ejemplos: "20/2006, de 15 de diciembre", "Ley 20/2006, de 15 de diciembre"
        $patron = '/\d+\/\d+,\s*de\s+\d+\s+de\s+\w+/i';
        
        return array_filter($leyes, function(Ley $ley) use ($patron) {
            $nombre = $ley->getNombre() ?? '';
            return preg_match($patron, $nombre) === 1;
        });
    }
}

