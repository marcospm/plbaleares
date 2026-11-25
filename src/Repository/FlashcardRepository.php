<?php

namespace App\Repository;

use App\Entity\Flashcard;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Flashcard>
 */
class FlashcardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Flashcard::class);
    }

    /**
     * @return Flashcard[]
     */
    public function findPropias(User $usuario): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.tema', 't')->addSelect('t')
            ->leftJoin('f.temaMunicipal', 'tm')->addSelect('tm')
            ->leftJoin('tm.municipio', 'm')->addSelect('m')
            ->leftJoin('f.pregunta', 'p')->addSelect('p')
            ->leftJoin('p.ley', 'l')->addSelect('l')
            ->andWhere('f.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('f.actualizadoEn', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $ids
     * @return Flashcard[]
     */
    public function findPropiasPorIds(User $usuario, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->leftJoin('f.tema', 't')->addSelect('t')
            ->leftJoin('f.temaMunicipal', 'tm')->addSelect('tm')
            ->leftJoin('tm.municipio', 'm')->addSelect('m')
            ->leftJoin('f.pregunta', 'p')->addSelect('p')
            ->leftJoin('p.ley', 'l')->addSelect('l')
            ->andWhere('f.usuario = :usuario')
            ->andWhere('f.id IN (:ids)')
            ->setParameter('usuario', $usuario)
            ->setParameter('ids', $ids)
            ->orderBy('f.actualizadoEn', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUnaPorPregunta(User $usuario, int $preguntaId, bool $municipal): ?Flashcard
    {
        $campo = $municipal ? 'preguntaMunicipal' : 'pregunta';

        return $this->createQueryBuilder('f')
            ->andWhere('f.usuario = :usuario')
            ->andWhere('f.'.$campo.' = :pregunta')
            ->setParameter('usuario', $usuario)
            ->setParameter('pregunta', $preguntaId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $ids
     * @return array<int, Flashcard>
     */
    public function indexadasPorPregunta(User $usuario, array $ids, bool $municipal): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $campo = $municipal ? 'preguntaMunicipal' : 'pregunta';
        $flashcards = $this->createQueryBuilder('f')
            ->innerJoin('f.'.$campo, 'p')->addSelect('p')
            ->leftJoin('f.tema', 't')->addSelect('t')
            ->leftJoin('f.temaMunicipal', 'tm')->addSelect('tm')
            ->leftJoin('tm.municipio', 'm')->addSelect('m')
            ->andWhere('f.usuario = :usuario')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('usuario', $usuario)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $mapa = [];
        foreach ($flashcards as $flashcard) {
            $pregunta = $municipal ? $flashcard->getPreguntaMunicipal() : $flashcard->getPregunta();
            if ($pregunta?->getId() !== null) {
                $mapa[$pregunta->getId()] = $flashcard;
            }
        }

        return $mapa;
    }
}
