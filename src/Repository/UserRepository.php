<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    /**
     * Busca usuarios con paginación y filtros
     * @param string $search Término de búsqueda (username o nombre)
     * @param string|null $activo Filtro por estado activo ('1', '0' o null)
     * @param int $page Número de página (empezando en 1)
     * @param int $itemsPerPage Items por página
     * @return array ['users' => User[], 'total' => int]
     */
    public function findPaginated(?string $search = null, ?string $activo = null, int $page = 1, int $itemsPerPage = 20): array
    {
        $qb = $this->createQueryBuilder('u');
        
        // Filtro por búsqueda
        if (!empty($search)) {
            $qb->andWhere('(u.username LIKE :search OR u.nombre LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtro por estado activo
        if ($activo !== null && $activo !== '') {
            $activoBool = $activo === '1';
            $qb->andWhere('u.activo = :activo')
               ->setParameter('activo', $activoBool);
        }
        
        // Contar total antes de paginar
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(u.id)')
                               ->getQuery()
                               ->getSingleScalarResult();
        
        // Aplicar paginación
        $offset = ($page - 1) * $itemsPerPage;
        $qb->orderBy('u.username', 'ASC')
           ->setFirstResult($offset)
           ->setMaxResults($itemsPerPage);
        
        return [
            'users' => $qb->getQuery()->getResult(),
            'total' => $total,
        ];
    }
}
