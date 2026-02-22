<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    private ?CacheItemPoolInterface $cache = null;

    public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache = null)
    {
        parent::__construct($registry, User::class);
        $this->cache = $cache;
    }

    /**
     * Sobrescribe findOneBy para excluir usuarios eliminados por defecto
     * Con caché para mejorar rendimiento del login
     * 
     * NOTA: Para Symfony Security, siempre obtenemos desde BD para asegurar que la entidad esté "managed"
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?User
    {
        // Para login/autenticación, siempre obtener desde BD para evitar problemas con entidades no managed
        // El caché se usa en otros contextos donde no es crítico
        // Si hay caché y solo buscamos por username, email o id, usar caché
        if ($this->cache && count($criteria) === 1) {
            $field = array_key_first($criteria);
            $value = $criteria[$field];
            
            if ($field === 'username') {
                $user = $this->findByUsernameCached($value);
                // Asegurar que está managed (importante para Symfony Security)
                if ($user && !$this->getEntityManager()->contains($user)) {
                    return $this->getEntityManager()->find(User::class, $user->getId());
                }
                return $user;
            } elseif ($field === 'email') {
                $user = $this->findByEmailCached($value);
                if ($user && !$this->getEntityManager()->contains($user)) {
                    return $this->getEntityManager()->find(User::class, $user->getId());
                }
                return $user;
            } elseif ($field === 'id') {
                $user = $this->findByIdCached($value);
                if ($user && !$this->getEntityManager()->contains($user)) {
                    return $this->getEntityManager()->find(User::class, $user->getId());
                }
                return $user;
            }
        }
        
        // Para otros casos, consulta directa sin caché
        $qb = $this->createQueryBuilder('u');
        
        foreach ($criteria as $field => $value) {
            $qb->andWhere("u.{$field} = :{$field}")
               ->setParameter($field, $value);
        }
        
        // Excluir usuarios eliminados
        $qb->andWhere('u.eliminado = :eliminado')
           ->setParameter('eliminado', false);
        
        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy("u.{$field}", $direction);
            }
        }
        
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Busca un usuario incluyendo los eliminados (para validaciones)
     * Con caché para mejorar rendimiento del login
     */
    public function findOneByIncludingDeleted(array $criteria, ?array $orderBy = null): ?User
    {
        // Si hay caché y solo buscamos por username o email, usar caché
        if ($this->cache && count($criteria) === 1) {
            $field = array_key_first($criteria);
            $value = $criteria[$field];
            
            if ($field === 'username') {
                return $this->findByUsernameCached($value, true);
            } elseif ($field === 'email') {
                return $this->findByEmailCached($value, true);
            }
        }
        
        // Para otros casos, consulta directa sin caché
        return parent::findOneBy($criteria, $orderBy);
    }
    
    /**
     * Busca usuario por username con caché
     * @param string $username
     * @param bool $includeDeleted Si true, incluye usuarios eliminados
     * @return User|null
     */
    public function findByUsernameCached(string $username, bool $includeDeleted = false): ?User
    {
        if (!$this->cache) {
            // Sin caché, consulta directa
            if ($includeDeleted) {
                return parent::findOneBy(['username' => $username]);
            }
            return $this->findOneBy(['username' => $username]);
        }
        
        $cacheKey = 'user_by_username_' . md5($username);
        $item = $this->cache->getItem($cacheKey);
        
        if ($item->isHit()) {
            try {
                $cachedUser = $item->get();
                if ($cachedUser instanceof User) {
                    // Verificar si la entidad está "managed" por el EntityManager
                    // Si no está managed, refrescar desde la BD
                    if (!$this->getEntityManager()->contains($cachedUser)) {
                        // La entidad no está managed (puede pasar después de deserialización)
                        // Refrescar desde la BD para asegurar que esté managed
                        $userId = $cachedUser->getId();
                        if ($userId) {
                            $freshUser = $this->getEntityManager()->find(User::class, $userId);
                            if ($freshUser) {
                                // Actualizar caché con la entidad managed
                                $item->set($freshUser);
                                $item->expiresAfter(86400);
                                $this->cache->save($item);
                                return $freshUser;
                            }
                        }
                    } else {
                        // Ya está managed, devolver directamente
                        return $cachedUser;
                    }
                }
            } catch (\Exception $e) {
                // Si hay error al deserializar, continuar y obtener desde BD
                // Esto puede pasar si la estructura de la entidad cambió
            }
        }
        
        // Obtener usuario de la base de datos
        if ($includeDeleted) {
            $user = parent::findOneBy(['username' => $username]);
        } else {
            $qb = $this->createQueryBuilder('u')
                ->where('u.username = :username')
                ->andWhere('u.eliminado = :eliminado')
                ->setParameter('username', $username)
                ->setParameter('eliminado', false);
            $user = $qb->getQuery()->getOneOrNullResult();
        }
        
        // Guardar en caché solo si la entidad está managed
        if ($user && $this->getEntityManager()->contains($user)) {
            $item->set($user);
            $item->expiresAfter(86400); // 24 horas
            $this->cache->save($item);
        }
        
        return $user;
    }
    
    /**
     * Busca usuario por email con caché
     * @param string $email
     * @param bool $includeDeleted Si true, incluye usuarios eliminados
     * @return User|null
     */
    public function findByEmailCached(string $email, bool $includeDeleted = false): ?User
    {
        if (!$this->cache) {
            // Sin caché, consulta directa
            if ($includeDeleted) {
                return parent::findOneBy(['email' => $email]);
            }
            return $this->findOneBy(['email' => $email]);
        }
        
        $cacheKey = 'user_by_email_' . md5(strtolower($email));
        $item = $this->cache->getItem($cacheKey);
        
        if ($item->isHit()) {
            try {
                $cachedUser = $item->get();
                if ($cachedUser instanceof User) {
                    if (!$this->getEntityManager()->contains($cachedUser)) {
                        $userId = $cachedUser->getId();
                        if ($userId) {
                            $freshUser = $this->getEntityManager()->find(User::class, $userId);
                            if ($freshUser) {
                                $item->set($freshUser);
                                $item->expiresAfter(86400);
                                $this->cache->save($item);
                                return $freshUser;
                            }
                        }
                    } else {
                        return $cachedUser;
                    }
                }
            } catch (\Exception $e) {
                // Si hay error, continuar y obtener desde BD
            }
        }
        
        // Obtener usuario de la base de datos
        if ($includeDeleted) {
            $user = parent::findOneBy(['email' => $email]);
        } else {
            $qb = $this->createQueryBuilder('u')
                ->where('u.email = :email')
                ->andWhere('u.eliminado = :eliminado')
                ->setParameter('email', $email)
                ->setParameter('eliminado', false);
            $user = $qb->getQuery()->getOneOrNullResult();
        }
        
        // Guardar en caché solo si la entidad está managed
        if ($user && $this->getEntityManager()->contains($user)) {
            $item->set($user);
            $item->expiresAfter(86400); // 24 horas
            $this->cache->save($item);
        }
        
        return $user;
    }
    
    /**
     * Busca usuario por ID con caché
     * @param int $id
     * @return User|null
     */
    public function findByIdCached(int $id): ?User
    {
        if (!$this->cache) {
            // Sin caché, consulta directa
            return $this->findOneBy(['id' => $id]);
        }
        
        $cacheKey = 'user_by_id_' . $id;
        $item = $this->cache->getItem($cacheKey);
        
        if ($item->isHit()) {
            $cachedUser = $item->get();
            // Asegurar que la entidad esté "managed" por el EntityManager
            if ($cachedUser && $this->getEntityManager()->contains($cachedUser)) {
                return $cachedUser;
            } elseif ($cachedUser) {
                // Refrescar desde la BD
                $freshUser = $this->getEntityManager()->find(User::class, $id);
                if ($freshUser) {
                    $item->set($freshUser);
                    $item->expiresAfter(86400);
                    $this->cache->save($item);
                    return $freshUser;
                }
            }
        }
        
        // Obtener usuario de la base de datos
        $qb = $this->createQueryBuilder('u')
            ->where('u.id = :id')
            ->andWhere('u.eliminado = :eliminado')
            ->setParameter('id', $id)
            ->setParameter('eliminado', false);
        $user = $qb->getQuery()->getOneOrNullResult();
        
        // Guardar en caché solo si la entidad está managed
        if ($user && $this->getEntityManager()->contains($user)) {
            $item->set($user);
            $item->expiresAfter(86400); // 24 horas
            $this->cache->save($item);
        }
        
        return $user;
    }
    
    /**
     * Invalida el caché de un usuario
     * @param User $user
     */
    public function invalidateUserCache(User $user): void
    {
        if (!$this->cache) {
            return;
        }
        
        // Invalidar por username
        if ($user->getUsername()) {
            $cacheKey = 'user_by_username_' . md5($user->getUsername());
            $this->cache->deleteItem($cacheKey);
        }
        
        // Invalidar por email
        if ($user->getEmail()) {
            $cacheKey = 'user_by_email_' . md5(strtolower($user->getEmail()));
            $this->cache->deleteItem($cacheKey);
        }
        
        // Invalidar por ID
        if ($user->getId()) {
            $cacheKey = 'user_by_id_' . $user->getId();
            $this->cache->deleteItem($cacheKey);
        }
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
        
        // Siempre excluir usuarios eliminados
        $qb->andWhere('u.eliminado = :eliminado')
           ->setParameter('eliminado', false);
        
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
