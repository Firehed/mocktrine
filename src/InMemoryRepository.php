<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Common\Collections\{
    ArrayCollection,
    Collection,
    Criteria,
    Expr,
    Selectable,
};
use Doctrine\Persistence\ObjectRepository;
use Doctrine\ORM\ORMException;
use DomainException;
use ReflectionClass;
use TypeError;
use UnexpectedValueException;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;

/**
 * @template Entity of object
 *
 * @implements ObjectRepository<Entity>
 * @implements Selectable<array-key, Entity>
 */
class InMemoryRepository implements ObjectRepository, Selectable
{
    /**
     * @var class-string<Entity>
     */
    private $className;

    /** @var DocBlockFactory */
    private $docblockFactory;

    /** @var ?string */
    private $idField;

    /** @var ?string */
    private $idType;

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
            $this->idType,
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
        $expr = Criteria::expr();
        $crit = Criteria::create();
        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                // Convert list arguments to IN(...)
                $crit->andWhere($expr->in($field, $value));
            } else {
                $crit->andWhere($expr->eq($field, $value));
            }
        }

        if ($orderBy) {
            // Criteria::orderBy silently converts any invalid inputs to 'DESC'
            // This pre-validates them
            foreach ($orderBy as $field => $direction) {
                if ($direction !== Criteria::ASC && $direction !== Criteria::DESC) {
                    throw ORMException::invalidOrientation($this->getClassName(), $field);
                }
            }
            $crit->orderBy($orderBy);
        }
        if ($offset) {
            $crit->setFirstResult($offset);
        }
        if ($limit) {
            $crit->setMaxResults($limit);
        }

        return $this->doMatch($crit);
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
     * Selectable implementation
     * {@inheritdoc}
     */
    public function matching(Criteria $criteria): Collection
    {
        return new ArrayCollection($this->doMatch($criteria));
    }

    /**
     * @return Entity[]
     */
    private function doMatch(Criteria $criteria): array
    {
        $expr = $criteria->getWhereExpression();

        $matcher = new ExpressionMatcher($this->getClassName(), $this->managedEntities);
        $entities = $matcher->match($expr);

        if ($orderings = $criteria->getOrderings()) {
            $entities = $this->sortResults($entities, $orderings);
        }

        if ($offset = $criteria->getFirstResult()) {
            $entities = array_slice($entities, $offset);
        }

        if ($limit = $criteria->getMaxResults()) {
            $entities = array_slice($entities, 0, $limit);
        }

        return $entities;
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
                if ($direction === Criteria::ASC) {
                    if ($v1 > $v2) {
                        return 1;
                    } else {
                        return -1;
                    }
                } elseif ($direction === Criteria::DESC) {
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
     *
     * @return array{string|null, string|null, bool}
     */
    private function findIdField(): array
    {
        foreach ($this->rc->getProperties() as $reflectionProp) {
            $docComment = $reflectionProp->getDocComment();
            assert($docComment !== false);
            $docblock = $this->docblockFactory->create($docComment);
            if ($docblock->hasTag('Id')) {
                // If an Id Column doesn't have type="integer" it defaults to
                // string (like all other columns)
                $idType = 'string';
                $columnTags = $docblock->getTagsByName('Column');
                if (count($columnTags) > 0) {
                    $columnTag = $columnTags[0];
                    assert($columnTag instanceof BaseTag);
                    $desc = $columnTag->getDescription();
                    if ($desc !== null) {
                        $descString = $desc->render();
                        $matchCount = preg_match('#type="([a-z]+)"#', $descString, $matches);
                        if ($matchCount > 0) {
                            $idType = $matches[1];
                            if ($idType !== 'string' && $idType !== 'integer') {
                                throw new UnexpectedValueException(sprintf(
                                    'Detected Id type is %s, only %s and %s are valid',
                                    $idType,
                                    'string',
                                    'integer'
                                ));
                            }
                        }
                    }
                }
                return [
                    $reflectionProp->getName(),
                    $idType,
                    $docblock->hasTag('GeneratedValue'),
                ];
            }
        }
        return [null, null, false];
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
     * This is used to generate identifiers when flush() is called. It should
     * not be used except by the EntityManager.
     *
     * @internal
     */
    public function getIdType(): ?string
    {
        return $this->idType;
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
