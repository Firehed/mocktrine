<?php
declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\ORMException;
use ReflectionClass;
use TypeError;
use UnexpectedValueException;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * @template Entity of object
 */
class InMemoryRepository implements ObjectRepository
{
    /**
     * @var class-string<Entity>
     */
    private $className;

    /** @var DocBlockFactory */
    private $docblockFactory;

    /** @var ?string */
    private $idField;

    /** @var bool */
    private $isIdGenerated;

    /** @var Entity[] */
    private $managedEntities = [];

    /** @var ReflectionClass<Entity> */
    private $rc;

    /**
     * @param class-string<Entity> $fqcn
     */
    public function __construct(string $fqcn)
    {
        $this->className = $fqcn;
        $this->docblockFactory = DocBlockFactory::createInstance();
        $this->rc = new ReflectionClass($fqcn);
        [
            $this->idField,
            $this->isIdGenerated
        ] = $this->findIdField();
        // TODO: define behavior of non-int generated id fields
    }

    /**
     * @internal
     * @param Entity $entity
     */
    public function manage(object $entity): void
    {
        if (!$entity instanceof $this->className) {
            throw new TypeError(sprintf(
                'Argument 1 passed to manage() must be of the type %s, %s given',
                $this->getClassName(),
                get_class($entity)
            ));
        }
        $this->managedEntities[spl_object_hash($entity)] = $entity;
    }

    /**
     * Used by the EntityManager when entities with deletion pending are
     * flushed.
     *
     * @internal
     *
     * @param Entity $entity
     */
    public function remove(object $entity): void
    {
        unset($this->managedEntities[spl_object_hash($entity)]);
    }

    // ObjectRepository implementation

    /**
     * Finds an object by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     *
     * @return ?Entity The object.
     */
    public function find($id)
    {
        if (!$this->idField) {
            throw new \Exception('Entity has no id...?');
        }
        return $this->findOneBy([$this->idField => $id]);
    }

    /**
     * Finds all objects in the repository.
     *
     * @return Entity[] The objects.
     */
    public function findAll()
    {
        return $this->findBy([]);
    }

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param array<array-key, mixed>       $criteria
     * @param array<array-key, string>|null $orderBy
     * @param int|null      $limit
     * @param int|null      $offset
     *
     * @return Entity[] The objects.
     *
     * @throws UnexpectedValueException
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
    {
        $result = array_values(array_filter($this->managedEntities, function ($entity) use ($criteria) {
            foreach ($criteria as $paramName => $paramValue) {
                $propVal = $this->getValueOfProperty($entity, $paramName);
                if ($propVal === $paramValue) {
                    continue;
                }
                // If $paramValue is an array, we're simulating `$paramName IN
                // ($paramValue)`
                if (is_array($paramValue) && in_array($propVal, $paramValue)) {
                    continue;
                }
                return false;
            }
            return true;
        }));

        if ($orderBy) {
            $result = $this->sortResults($result, $orderBy);
        }
        if ($offset) {
            $result = array_slice($result, $offset);
        }
        if ($limit) {
            $result = array_slice($result, 0, $limit);
        }

        return $result;
    }

    /**
     * Finds a single object by a set of criteria.
     *
     * @param mixed[] $criteria The criteria.
     *
     * @return ?Entity The object.
     */
    public function findOneBy(array $criteria)
    {
        $results = $this->findBy($criteria);
        if (count($results) > 0) {
            return current($results);
        }
        return null;
    }

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @return class-string<Entity>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param Entity $entity
     * @return mixed
     */
    private function getValueOfProperty(object $entity, string $property)
    {
        if (!$this->rc->hasProperty($property)) {
            throw new UnexpectedValueException(sprintf(
                'Property "%s" does not exist on class "%s"',
                $property,
                $this->getClassName()
            ));
        }
        $rp = $this->rc->getProperty($property);

        $docComment = $rp->getDocComment();
        assert($docComment !== false);
        $docblock = $this->docblockFactory->create($docComment);
        // TODO: suppport other relations
        if (!$docblock->hasTag('Column')) {
            throw new UnexpectedValueException(sprintf(
                'Property "%s" is not a mapped field on class "%s"',
                $property,
                $this->getClassName()
            ));
        }

        // $isAccessible = $rp->isPublic();
        $rp->setAccessible(true);
        $propVal = $rp->getValue($entity);
        // $rp->setAccessible($isAccessible);
        return $propVal;
    }

    /**
     * @param Entity[] $results
     * @param array<array-key, string> $orderBy
     * @return Entity[]
     */
    private function sortResults(array $results, array $orderBy): array
    {
        // WARNING: this has the potential to get quite slow due to the use
        // of reflection on TWO entities on every single comparision. On
        // typical test datasets this is unlikely to be a major issue, but
        // it's quite inefficient and should be re-examined in the future.
        /**
         * @param Entity $a
         * @param Entity $b
         */
        usort($results, function ($a, $b) use ($orderBy) {
            foreach ($orderBy as $propName => $direction) {
                $v1 = $this->getValueOfProperty($a, $propName);
                $v2 = $this->getValueOfProperty($b, $propName);
                if ($v1 === $v2) {
                    continue;
                }
                if ($direction === 'ASC') {
                    if ($v1 > $v2) {
                        return 1;
                    } else {
                        return -1;
                    }
                } elseif ($direction === 'DESC') {
                    if ($v1 < $v2) {
                        return 1;
                    } else {
                        return -1;
                    }
                } else {
                    throw ORMException::invalidOrientation($this->getClassName(), $propName);
                }
            }
            // If all loops have exited without returning a comparision
            // result, all of the sort properties should be equal.
            return 0;
        });
        return $results;
    }

    /**
     * Searches for an @Id tag on the entity, and returns a tuple containing
     * the associated property name and whether the value is generated.
     */
    private function findIdField(): array
    {
        foreach ($this->rc->getProperties() as $reflectionProp) {
            $docComment = $reflectionProp->getDocComment();
            assert($docComment !== false);
            $docblock = $this->docblockFactory->create($docComment);
            if ($docblock->hasTag('Id')) {
                return [
                    $reflectionProp->getName(),
                    $docblock->hasTag('GeneratedValue'),
                ];
            }
        }
        return [null, false];
    }

    /**
     * This is used to generate identifiers when flush() is called. It should
     * not be used except by the EntityManager.
     *
     * @internal
     */
    public function getIdField(): ?string
    {
        return $this->idField;
    }

    /**
     * This is used to determine if IDs need to be generated when
     * EntityManager's flush() method is called. Is should not be used by
     * anything else.
     *
     * @internal
     */
    public function isIdGenerated(): bool
    {
        return $this->isIdGenerated;
    }
}
