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
            ->leftJoin('f.ley', 'fl')->addSelect('fl')
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
            ->leftJoin('f.ley', 'fl')->addSelect('fl')
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

    /**
     * Flashcards guardadas por alumno activo. No cuenta profesores ni administradores.
     *
     * @param int[]|null $alumnosIds
     * @return list<array{usuario: User, cantidad: int}>
     */
    public function rankingPorAlumno(?array $alumnosIds = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.usuario) as usuarioId, COUNT(f.id) as cantidad')
            ->innerJoin('f.usuario', 'u')
            ->andWhere('u.activo = :activo')
            ->andWhere('u.roles NOT LIKE :roleProfesor')
            ->andWhere('u.roles NOT LIKE :roleAdmin')
            ->setParameter('activo', true)
            ->setParameter('roleProfesor', '%"ROLE_PROFESOR"%')
            ->setParameter('roleAdmin', '%"ROLE_ADMIN"%')
            ->groupBy('f.usuario')
            ->orderBy('cantidad', 'DESC');

        if ($alumnosIds !== null && $alumnosIds !== []) {
            $qb->andWhere('u.id IN (:alumnosIds)')
                ->setParameter('alumnosIds', $alumnosIds);
        }

        $result = $qb->getQuery()->getResult();
        $usuarioIds = array_column($result, 'usuarioId');
        if ($usuarioIds === []) {
            return [];
        }

        $usuarios = $this->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $usuarioIds)
            ->getQuery()
            ->getResult();

        $usuariosMap = [];
        foreach ($usuarios as $usuario) {
            $usuariosMap[$usuario->getId()] = $usuario;
        }

        $ranking = [];
        foreach ($result as $row) {
            $usuarioId = (int) $row['usuarioId'];
            if (!isset($usuariosMap[$usuarioId])) {
                continue;
            }
            $ranking[] = [
                'usuario' => $usuariosMap[$usuarioId],
                'cantidad' => (int) $row['cantidad'],
            ];
        }

        return $ranking;
    }
}
