<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\ORM\{
    Configuration,
    EntityManagerInterface,
    Mapping\ClassMetadata,
    Mapping\ClassMetadataFactory,
    NativeQuery,
    ORMException,
    OptimisticLockException,
    PessimisticLockException,
    Query,
    QueryBuilder,
    Query\ResultSetMapping,
    UnitOfWork,
};
use RuntimeException;

class InMemoryEntityManager implements EntityManagerInterface
{
    /**
     * This holds all of the InMemoryRepository objects, which will be lazily
     * instantiated as they are first used.
     *
     * @template Entity of object
     * @var array<class-string<Entity>, InMemoryRepository<Entity>>
     */
    private $repos = [];

    /**
     * @template Entity of object
     * @var array<class-string<Entity>, array<Entity>>
     */
    private $needIds = [];

    /**
     * @template Entity of object
     * @var array<class-string<Entity>, array<Entity>>
     */
    private $pendingDeletes = [];

    /**
     * @var callable[]
     */
    private array $onFlushCallbacks = [];

    public function addOnFlushCallback(callable $callback): void
    {
        $this->onFlushCallbacks[] = $callback;
    }

    // ObjectMangaer (parent interface)

    /**
     * Finds an object by its identifier.
     *
     * This is just a convenient shortcut for getRepository($className)->find($id).
     *
     * @template Entity of object
     * @param class-string<Entity> $className
     * @param mixed  $id        The identity of the object to find.
     *
     * @return ?Entity The found object.
     */
    public function find($className, $id)
    {
        return $this->getRepository($className)->find($id);
    }

    /**
     * Tells the ObjectManager to make an instance managed and persistent.
     *
     * The object will be entered into the database as a result of the flush operation.
     *
     * NOTE: The persist operation always considers objects that are not yet known to
     * this ObjectManager as NEW. Do not pass detached objects to the persist operation.
     *
     * @param object $object The instance to make managed and persistent.
     *
     * @return void
     */
    public function persist($object)
    {
        $class = get_class($object);
        $this->getRepository($class)->manage($object);
        $this->needIds[$class][] = $object;
    }

    /**
     * Removes an object instance.
     *
     * A removed object will be removed from the database as a result of the flush operation.
     *
     * @param object $object The object instance to remove.
     *
     * @return void
     */
    public function remove($object)
    {
        $this->pendingDeletes[get_class($object)][] = $object;
    }

    /**
     * Merges the state of a detached object into the persistence context
     * of this ObjectManager and returns the managed copy of the object.
     * The object passed to merge will not become associated/managed with this ObjectManager.
     *
     * @param object $object
     *
     * @return object
     */
    public function merge($object)
    {
        $repo = $this->getRepository(get_class($object));
        $repo->manage($object);
        return $object;
    }

    /**
     * Clears the ObjectManager. All objects that are currently managed
     * by this ObjectManager become detached.
     *
     * @param string|null $objectName if given, only objects of this type will get detached.
     *
     * @return void
     */
    public function clear($objectName = null)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Detaches an object from the ObjectManager, causing a managed object to
     * become detached. Unflushed changes made to the object if any
     * (including removal of the object), will not be synchronized to the database.
     * Objects which previously referenced the detached object will continue to
     * reference it.
     *
     * @param object $object The object to detach.
     *
     * @return void
     */
    public function detach($object)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Refreshes the persistent state of an object from the database,
     * overriding any local changes that have not yet been persisted.
     *
     * @param object $object The object to refresh.
     *
     * @return void
     */
    public function refresh($object)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Flushes all changes to objects that have been queued up to now to the database.
     * This effectively synchronizes the in-memory state of managed objects with the
     * database.
     *
     * @return void
     */
    public function flush()
    {
        /**
         * @template Entity of object
         * @var class-string<Entity> $className
         * @var Entity[] $entities
         */
        foreach ($this->pendingDeletes as $className => $entities) {
            $repo = $this->getRepository($className);
            foreach ($entities as $entity) {
                $repo->remove($entity);
            }
        }
        $this->pendingDeletes = [];

        /**
         * @template Entity of object
         * @var class-string<Entity> $className
         * @var Entity[] $entities
         */
        foreach ($this->needIds as $className => $entities) {
            $repo = $this->getRepository($className);
            if (!$repo->isIdGenerated()) {
                continue;
            }
            $idField = $repo->getIdField();
            $idType = $repo->getIdType();
            assert($idField !== null);
            $rp = new \ReflectionProperty($className, $idField);
            $rp->setAccessible(true);
            foreach ($entities as $entity) {
                if ($rp->getValue($entity) === null) {
                    $id = random_int(0, PHP_INT_MAX);
                    if ($idType === 'string') {
                        $id = (string) $id;
                    }
                    $rp->setValue($entity, $id);
                }
            }
        }
        $this->needIds = [];
        foreach ($this->onFlushCallbacks as $callback) {
            $callback();
        }
    }

    /**
     * Gets the repository for a class.
     *
     * @template Entity of object
     * @param class-string<Entity> $className
     * @return InMemoryRepository<Entity>
     */
    public function getRepository($className)
    {
        // https://github.com/phpstan/phpstan/issues/2761
        if (!isset($this->repos[$className])) {
            $this->repos[$className] = new InMemoryRepository($className);
        }

        return $this->repos[$className];
    }

    /**
     * Returns the ClassMetadata descriptor for a class.
     *
     * The class name must be the fully-qualified class name without a leading backslash
     * (as it is returned by get_class($obj)).
     *
     * @template T of object
     * @param class-string<T> $className
     *
     * @return ClassMetadata<T>
     */
    public function getClassMetadata($className)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the metadata factory used to gather the metadata of classes.
     *
     * @return ClassMetadataFactory
     */
    public function getMetadataFactory()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Helper method to initialize a lazy loading proxy or persistent collection.
     *
     * This method is a no-op for other objects.
     *
     * @param object $obj
     *
     * @return void
     */
    public function initializeObject($obj)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Checks if the object is part of the current UnitOfWork and therefore managed.
     *
     * @param object $object
     *
     * @return bool
     */
    public function contains($object)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    // EntityManagerInterface

    /**
     * Returns the cache API for managing the second level cache regions or NULL if the cache is not enabled.
     *
     * @return \Doctrine\ORM\Cache|null
     */
    public function getCache()
    {
        return null;
    }

    /**
     * Gets the database connection object used by the EntityManager.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets an ExpressionBuilder used for object-oriented construction of query expressions.
     *
     * Example:
     *
     * <code>
     *     $qb = $em->createQueryBuilder();
     *     $expr = $em->getExpressionBuilder();
     *     $qb->select('u')->from('User', 'u')
     *         ->where($expr->orX($expr->eq('u.id', 1), $expr->eq('u.id', 2)));
     * </code>
     *
     * @return \Doctrine\ORM\Query\Expr
     */
    public function getExpressionBuilder()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Starts a transaction on the underlying database connection.
     *
     * @return void
     */
    public function beginTransaction()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Executes a function in a transaction.
     *
     * The function gets passed this EntityManager instance as an (optional) parameter.
     *
     * {@link flush} is invoked prior to transaction commit.
     *
     * If an exception occurs during execution of the function or flushing or transaction commit,
     * the transaction is rolled back, the EntityManager closed and the exception re-thrown.
     *
     * @param callable $func The function to execute transactionally.
     *
     * @return mixed The non-empty value returned from the closure or true instead.
     */
    public function transactional($func)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Commits a transaction on the underlying database connection.
     *
     * @return void
     */
    public function commit()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Performs a rollback on the underlying database connection.
     *
     * @return void
     */
    public function rollback()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Creates a new Query object.
     *
     * @param string $dql The DQL string.
     *
     * @return Query
     */
    public function createQuery($dql = '')
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Creates a Query from a named query.
     *
     * @param string $name
     *
     * @return Query
     */
    public function createNamedQuery($name)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Creates a native SQL query.
     *
     * @param string           $sql
     * @param ResultSetMapping $rsm The ResultSetMapping to use.
     *
     * @return NativeQuery
     */
    public function createNativeQuery($sql, ResultSetMapping $rsm)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Creates a NativeQuery from a named native query.
     *
     * @param string $name
     *
     * @return NativeQuery
     */
    public function createNamedNativeQuery($name)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Create a QueryBuilder instance
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets a reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * @param string $entityName The name of the entity type.
     * @param mixed  $id         The entity identifier.
     *
     * @return object|null The entity reference.
     *
     * @throws ORMException
     */
    public function getReference($entityName, $id)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets a partial reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * The returned reference may be a partial object if the entity is not yet loaded/managed.
     * If it is a partial object it will not initialize the rest of the entity state on access.
     * Thus you can only ever safely access the identifier of an entity obtained through
     * this method.
     *
     * The use-cases for partial references involve maintaining bidirectional associations
     * without loading one side of the association or to update an entity without loading it.
     * Note, however, that in the latter case the original (persistent) entity data will
     * never be visible to the application (especially not event listeners) as it will
     * never be loaded in the first place.
     *
     * @param string $entityName The name of the entity type.
     * @param mixed  $identifier The entity identifier.
     *
     * @return object|null The (partial) entity reference.
     */
    public function getPartialReference($entityName, $identifier)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Closes the EntityManager. All entities that are currently managed
     * by this EntityManager become detached. The EntityManager may no longer
     * be used after it is closed.
     *
     * @return void
     */
    public function close()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Creates a copy of the given entity. Can create a shallow or a deep copy.
     *
     * @param object  $entity The entity to copy.
     * @param boolean $deep   FALSE for a shallow copy, TRUE for a deep copy.
     *
     * @return object The new entity.
     *
     * @throws \BadMethodCallException
     */
    public function copy($entity, $deep = false)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Acquire a lock on the given entity.
     *
     * @param object   $entity
     * @param int      $lockMode
     * @param int|null $lockVersion
     *
     * @return void
     *
     * @throws OptimisticLockException
     * @throws PessimisticLockException
     */
    public function lock($entity, $lockMode, $lockVersion = null)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the EventManager used by the EntityManager.
     *
     * @return \Doctrine\Common\EventManager
     */
    public function getEventManager()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the Configuration used by the EntityManager.
     *
     * @return Configuration
     */
    public function getConfiguration()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Check if the Entity manager is open or closed.
     *
     * @return bool
     */
    public function isOpen()
    {
        // No database connection, always open.
        return true;
    }

    /**
     * Gets the UnitOfWork used by the EntityManager to coordinate operations.
     *
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
    * Gets a hydrator for the given hydration mode.
    *
    * This method caches the hydrator instances which is used for all queries that don't
    * selectively iterate over the result.
    *
    * @deprecated
    *
    * @param string|int $hydrationMode
    *
    * @return \Doctrine\ORM\Internal\Hydration\AbstractHydrator
    */
    public function getHydrator($hydrationMode)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Create a new instance for the given hydration mode.
     *
     * @param string|int $hydrationMode
     *
     * @return \Doctrine\ORM\Internal\Hydration\AbstractHydrator
     *
     * @throws ORMException
     */
    public function newHydrator($hydrationMode)
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the proxy factory used by the EntityManager to create entity proxies.
     *
     * @return \Doctrine\ORM\Proxy\ProxyFactory
     */
    public function getProxyFactory()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the enabled filters.
     *
     * @return \Doctrine\ORM\Query\FilterCollection The active filter collection.
     */
    public function getFilters()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Checks whether the state of the filter collection is clean.
     *
     * @return boolean True, if the filter collection is clean.
     */
    public function isFiltersStateClean()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Checks whether the Entity Manager has filters.
     *
     * @return boolean True, if the EM has a filter collection.
     */
    public function hasFilters()
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }
}
