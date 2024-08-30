<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\DBAL\{
    Connection,
    LockMode,
};
use Doctrine\ORM\{
    Cache,
    Configuration,
    EntityManagerInterface,
    Exception\ORMException,
    NativeQuery,
    OptimisticLockException,
    PessimisticLockException,
    Query,
    QueryBuilder,
    Query\ResultSetMapping,
    UnitOfWork,
};
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\{
    ClassMetadata,
    ClassMetadataFactory,
    Driver\MappingDriver,
};
use RuntimeException;

use function class_exists;

class InMemoryEntityManager implements EntityManagerInterface
{
    /**
     * This holds all of the InMemoryRepository objects, which will be lazily
     * instantiated as they are first used.
     */
    private RepositoryContainer $repos;

    /**
     * Default instance, for performance
     */
    private static ?MappingDriver $defaultMappingDriver = null;

    /**
     * The mapping driver used for reading the Doctrine ORM mappings from
     * entities.
     */
    private MappingDriver $mappingDriver;

    /**
     * @var array<class-string, object[]>
     */
    private $needIds = [];

    /**
     * @var array<class-string, object[]>
     */
    private $pendingDeletes = [];

    /**
     * @var callable[]
     */
    private array $onFlushCallbacks = [];

    public function __construct(?MappingDriver $driver = null)
    {
        if ($driver === null) {
            // Doctrine's default
            // `createAnnotationMetadataDriverConfiguration()` uses the simple
            // annotation reader. This is configurable in Setup, but we will
            // emulate the default case.
            // If you would like different behavior, provide the driver
            // directly.
            $driver = self::getDefaultMappingDriver();
        }
        $this->mappingDriver = $driver;
        $this->repos = new RepositoryContainer();
    }

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
     *
     * @return ?Entity The found object.
     */
    public function find(
        string $className,
        mixed $id,
        LockMode|int|null $lockMode = null,
        ?int $lockVersion = null,
    ): ?object {
        return $this->getRepository($className)->find($id);
    }

    /**
     * Tells the ObjectManager to make an instance managed and persistent.
     *
     * The object will be entered into the database as a result of the flush operation.
     *
     * NOTE: The persist operation always considers objects that are not yet known to
     * this ObjectManager as NEW. Do not pass detached objects to the persist operation.
     */
    public function persist(object $object): void
    {
        $class = get_class($object);
        $this->getRepository($class)->manage($object);
        $this->needIds[$class][] = $object;
    }

    /**
     * Removes an object instance.
     *
     * A removed object will be removed from the database as a result of the flush operation.
     */
    public function remove(object $object): void
    {
        $this->pendingDeletes[get_class($object)][] = $object;
    }

    /**
     * Clears the ObjectManager. All objects that are currently managed
     * by this ObjectManager become detached.
     */
    public function clear(): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Detaches an object from the ObjectManager, causing a managed object to
     * become detached. Unflushed changes made to the object if any
     * (including removal of the object), will not be synchronized to the database.
     * Objects which previously referenced the detached object will continue to
     * reference it.
     */
    public function detach(object $object): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Refreshes the persistent state of an object from the database,
     * overriding any local changes that have not yet been persisted.
     */
    public function refresh(object $object, LockMode|int|null $lockMode = null): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Flushes all changes to objects that have been queued up to now to the database.
     * This effectively synchronizes the in-memory state of managed objects with the
     * database.
     */
    public function flush(): void
    {
        foreach ($this->pendingDeletes as $className => $entities) {
            $repo = $this->getRepository($className);
            foreach ($entities as $entity) {
                $repo->remove($entity);
            }
        }
        $this->pendingDeletes = [];

        foreach ($this->needIds as $className => $entities) {
            $repo = $this->getRepository($className);
            if (!$repo->isIdGenerated()) {
                continue;
            }
            $idField = $repo->getIdField();
            $idType = $repo->getIdType();
            $rp = new \ReflectionProperty($className, $idField);
            $rp->setAccessible(true);
            foreach ($entities as $entity) {
                if (!$rp->isInitialized($entity) || $rp->getValue($entity) === null) {
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
    public function getRepository(string $className): InMemoryRepository
    {
        if (!$this->repos->has($className)) {
            $this->repos->set($className, new InMemoryRepository($className, $this->mappingDriver));
        }

        return $this->repos->get($className);
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
     * return ClassMetadata<T>
     */
    public function getClassMetadata($className): \Doctrine\ORM\Mapping\ClassMetadata
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the metadata factory used to gather the metadata of classes.
     *
     * return ClassMetadataFactory<ClassMetadata<object>>
     */
    public function getMetadataFactory(): never
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Helper method to initialize a lazy loading proxy or persistent collection.
     *
     * This method is a no-op for other objects.
     */
    public function initializeObject(object $obj): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Checks if the object is part of the current UnitOfWork and therefore managed.
     */
    public function contains(object $object): bool
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    // EntityManagerInterface

    /**
     * Returns the cache API for managing the second level cache regions or NULL if the cache is not enabled.
     */
    public function getCache(): ?Cache
    {
        return null;
    }

    /**
     * Gets the database connection object used by the EntityManager.
     */
    public function getConnection(): Connection
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
     */
    public function getExpressionBuilder(): Query\Expr
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Starts a transaction on the underlying database connection.
     */
    public function beginTransaction(): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Commits a transaction on the underlying database connection.
     */
    public function commit(): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Performs a rollback on the underlying database connection.
     */
    public function rollback(): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Creates a new Query object.
     *
     * @param string $dql The DQL string.
     */
    public function createQuery($dql = ''): Query
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
     */
    public function createNativeQuery($sql, ResultSetMapping $rsm): NativeQuery
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
     */
    public function createQueryBuilder(): QueryBuilder
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets a reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * @template T of object
     * @param class-string<T> $entityName The name of the entity type.
     *
     * @return ?T The entity reference.
     *
     * @throws ORMException
     */
    public function getReference(string $entityName, mixed $id): ?object
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
     */
    public function close(): void
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
     * @throws OptimisticLockException
     * @throws PessimisticLockException
     */
    public function lock($entity, $lockMode, $lockVersion = null): void
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the EventManager used by the EntityManager.
     */
    public function getEventManager(): \Doctrine\Common\EventManager
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the Configuration used by the EntityManager.
     */
    public function getConfiguration(): Configuration
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Check if the Entity manager is open or closed.
     */
    public function isOpen(): bool
    {
        // No database connection, always open.
        return true;
    }

    /**
     * Gets the UnitOfWork used by the EntityManager to coordinate operations.
     */
    public function getUnitOfWork(): \Doctrine\ORM\UnitOfWork
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
     * @throws ORMException
     */
    public function newHydrator($hydrationMode): \Doctrine\ORM\Internal\Hydration\AbstractHydrator
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the proxy factory used by the EntityManager to create entity proxies.
     */
    public function getProxyFactory(): \Doctrine\ORM\Proxy\ProxyFactory
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Gets the enabled filters.
     */
    public function getFilters(): \Doctrine\ORM\Query\FilterCollection
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Checks whether the state of the filter collection is clean.
     */
    public function isFiltersStateClean(): bool
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Checks whether the Entity Manager has filters.
     */
    public function hasFilters(): bool
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }

    public static function setDefaultMappingDriver(MappingDriver $driver): void
    {
        self::$defaultMappingDriver = $driver;
    }

    private static function getDefaultMappingDriver(): MappingDriver
    {
        if (self::$defaultMappingDriver === null) {
            $driver = new AttributeDriver(['.']);
            self::$defaultMappingDriver = $driver;
        }
        return self::$defaultMappingDriver;
    }

    public function wrapInTransaction(callable $func): mixed
    {
        throw new RuntimeException(__METHOD__ . ' not yet implemented');
    }
}
