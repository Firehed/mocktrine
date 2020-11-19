<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use BadMethodCallException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\{
    Comparison,
    CompositeExpression,
    Expression,
    Value,
};
use DomainException;
use ReflectionClass;
use UnexpectedValueException;
use phpDocumentor\Reflection\DocBlockFactory;

use function array_filter;
use function array_map;
use function array_reduce;
use function assert;
use function get_class;
use function in_array;
use function is_float;
use function is_int;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * This functions similarly to ClosureExpressionVisitor, but uses class
 * reflection rather than a series of cascading accessor detectors (which are
 * potentially unsafe and do not translate 1:1 with the ORM's behavior) to find
 * and compare property values.
 *
 * @template Entity of object
 *
 * @internal
 */
class CriteriaEvaluator
{
    /** @var class-string<Entity> */
    private string $className;

    private DocBlockFactory $docblockFactory;

    /** @var Entity[] */
    private array $entities;

    /** @var ReflectionClass<Entity> */
    private ReflectionClass $rc;

    /**
     * @param class-string<Entity> $className
     * @param Entity[] $entities
     */
    public function __construct(string $className, array $entities)
    {
        $this->rc = new ReflectionClass($className);
        $this->className = $className;

        $this->docblockFactory = DocBlockFactory::createInstance();

        $this->entities = $entities;
    }

    /**
     * @return Entity[]
     */
    public function evaluate(Criteria $criteria): array
    {
        $expr = $criteria->getWhereExpression();
        $entities = $this->match($expr);

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
     * Evaluates the where expression of the criteria
     *
     * @return Entity[]
     */
    private function match(?Expression $expr): array
    {
        if ($expr instanceof Comparison) {
            $comparitor = $this->matchComparison($expr);
            $entities = array_filter(
                $this->entities,
                fn ($e) => $comparitor($this->getValueOfProperty($e, $expr->getField()))
            );
        } elseif ($expr instanceof CompositeExpression) {
            $entities = $this->matchCompositeExpression($expr);
        } elseif ($expr instanceof Value) {
            throw new BadMethodCallException(
                'match() called with Expr\Value. Please file a bug including the criteria used.'
            );
        } elseif ($expr === null) {
            $entities = $this->entities;
        } else {
            throw new DomainException(sprintf('Unknown expression class %s', get_class($expr)));
        }
        return $entities;
    }

    /**
     * @return callable(mixed $entityValue): bool
     * xeturn Entity[]
     */
    private function matchComparison(Comparison $expr): callable
    {
        $field = $expr->getField();
        $value = $expr->getValue()->getValue(); // Unwrap it

        switch ($expr->getOperator()) {
            // case Comparison::IS: ?
            case Comparison::EQ:
                return function ($entVal) use ($value) {
                    if (is_float($entVal) && is_int($value)) {
                        // Perform safe int-to-float cast if the test value
                        // arrived as an integer and the entity has a float
                        return $entVal === (float) $value;
                    }
                    return $entVal === $value;
                };
            case Comparison::NEQ:
                return function ($entVal) use ($value) {
                    // See Comparison::EQ
                    if (is_float($entVal) && is_int($value)) {
                        return $entVal !== (float) $value;
                    }
                    return $entVal !== $value;
                };
            case Comparison::GT:
                return fn ($entVal) => $entVal > $value;
            case Comparison::GTE:
                return fn ($entVal) => $entVal >= $value;
            case Comparison::LT:
                return fn ($entVal) => $entVal < $value;
            case Comparison::LTE:
                return fn ($entVal) => $entVal <= $value;
            case Comparison::IN:
                return fn ($entVal) => in_array($entVal, $value, true);
            case Comparison::NIN:
                return fn ($entVal) => !in_array($entVal, $value, true);
            case Comparison::CONTAINS:
                return fn ($entVal) => str_contains($entVal, $value);
            // TODO: case MEMBER_OF:
            case Comparison::STARTS_WITH:
                return fn ($entVal) => str_starts_with($entVal, $value);
            case Comparison::ENDS_WITH:
                return fn ($entVal) => str_ends_with($entVal, $value);
        }
        // Should be unreachable
        // @codeCoverageIgnoreStart
        throw new DomainException(sprintf('Unhandled operator %s', $expr->getOperator()));
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return Entity[]
     */
    private function matchCompositeExpression(CompositeExpression $expr): array
    {
        $expressions = $expr->getExpressionList();
        // For each expression in the composite, apply the filtering and gather
        // the results.
        $filteredEntitySets = array_map([$this, 'match'], $expressions);

        // Reduce the result sets to a single result set based on the
        // expression type:
        // - AND returns only the items in every set (by strict comparision)
        // - OR returns the unique set of items across all sets
        $type = $expr->getType();
        switch ($type) {
            case CompositeExpression::TYPE_AND:
                // Conceptually, this is expressed by:
                // $inAllSets = array_intersect(...$filteredEntitySets);
                // but array_intersect stringifies each item and can't be used here.
                return array_reduce(
                    $filteredEntitySets,
                    function ($carry, $item) {
                        // First pass
                        if ($carry === null) {
                            return $item;
                        }
                        return array_filter(
                            $carry,
                            fn ($itemInCarry) => in_array($itemInCarry, $item, true),
                        );
                    },
                );
            case CompositeExpression::TYPE_OR:
                return array_reduce($filteredEntitySets, 'array_merge', []);
            default:
                // Should be unreachable
                // @codeCoverageIgnoreStart
                throw new DomainException(sprintf('Unhandled composite expression type %s', $type));
                // @codeCoverageIgnoreEnd
        }
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
                $this->className,
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
                $this->className,
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
     * @param array<string, string> $orderBy (actually
     * Criteria::ASC|Criteria:::DESC but the PHPStan annotations won't work)
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
                    // Don't return 0 - that's only safe if on the last
                    // property in the sorting criteria
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
                    // @codeCoverageIgnoreStart
                    throw new DomainException(sprintf('Unhandled direction %s', $direction));
                    // @codeCoverageIgnoreEnd
                }
            }
            // If all loops have exited without returning a comparision
            // result, all of the sort properties should be equal.
            return 0;
        });
        return $results;
    }
}
