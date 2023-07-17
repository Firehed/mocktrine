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
use Doctrine\Persistence\Mapping\ClassMetadata;
use DomainException;
use ReflectionClass;
use UnexpectedValueException;

use function array_filter;
use function array_map;
use function array_reduce;
use function array_slice;
use function array_values;
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
    /** @var ClassMetadata<Entity> */
    private ClassMetadata $metadata;

    /** @var \ReflectionProperty[] */
    private array $reflectionProperties;

    /**
     * @param ClassMetadata<Entity> $metadata
     */
    public function __construct(ClassMetadata $metadata)
    {
        $this->metadata = $metadata;
        $className = $metadata->getName();
        $rc = new ReflectionClass($className);
        foreach ($metadata->getFieldNames() as $fieldName) {
            $rp = $rc->getProperty($fieldName);
            $rp->setAccessible(true);
            $this->reflectionProperties[$fieldName] = $rp;
        }
    }

    /**
     * @param Entity[] $entities
     * @return Entity[]
     */
    public function evaluate(array $entities, Criteria $criteria): array
    {
        $expr = $criteria->getWhereExpression();
        $entities = $this->match($entities, $expr);

        if ($orderings = $criteria->getOrderings()) {
            $entities = $this->sortResults($entities, $orderings);
        }

        if ($offset = $criteria->getFirstResult()) {
            $entities = array_slice($entities, $offset);
        }

        if ($limit = $criteria->getMaxResults()) {
            $entities = array_slice($entities, 0, $limit);
        }

        return array_values($entities);
    }

    /**
     * Evaluates the where expression of the criteria
     *
     * @param Entity[] $entities
     * @return Entity[]
     */
    private function match(array $entities, ?Expression $expr): array
    {
        if ($expr instanceof Comparison) {
            $comparitor = $this->matchComparison($expr);
            return array_filter(
                $entities,
                fn ($e) => $comparitor($this->getValueOfProperty($e, $expr->getField()))
            );
        } elseif ($expr instanceof CompositeExpression) {
            return $this->matchCompositeExpression($entities, $expr);
        } elseif ($expr instanceof Value) {
            throw new BadMethodCallException(
                'match() called with Expr\Value. Please file a bug including the criteria used.'
            );
        } elseif ($expr === null) {
            return $entities;
        } else {
            throw new DomainException(sprintf('Unknown expression class %s', get_class($expr)));
        }
    }

    /**
     * @return callable(mixed $entityValue): bool
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
                assert(is_array($value));
                return fn ($entVal) => in_array($entVal, $value, true);
            case Comparison::NIN:
                assert(is_array($value));
                return fn ($entVal) => !in_array($entVal, $value, true);
            case Comparison::CONTAINS:
                assert(is_string($value));
                return fn ($entVal) => str_contains($entVal, $value);
            // TODO: case MEMBER_OF:
            case Comparison::STARTS_WITH:
                assert(is_string($value));
                return fn ($entVal) => str_starts_with($entVal, $value);
            case Comparison::ENDS_WITH:
                assert(is_string($value));
                return fn ($entVal) => str_ends_with($entVal, $value);
        }
        // Should be unreachable
        // @codeCoverageIgnoreStart
        throw new DomainException(sprintf('Unhandled operator %s', $expr->getOperator()));
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param Entity[] $entities
     * @return Entity[]
     */
    private function matchCompositeExpression(array $entities, CompositeExpression $expr): array
    {
        $type = $expr->getType();
        switch ($type) {
            case CompositeExpression::TYPE_AND:
                return $this->matchCompositeAndExpression($entities, $expr);
            case CompositeExpression::TYPE_OR:
                return $this->matchCompositeOrExpression($entities, $expr);
            default:
                // Should be unreachable
                // @codeCoverageIgnoreStart
                throw new DomainException(sprintf('Unhandled composite expression type %s', $type));
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param Entity[] $entities
     * @return Entity[]
     */
    private function matchCompositeOrExpression(array $entities, CompositeExpression $expr): array
    {
        $expressions = $expr->getExpressionList();
        // For each expression in the composite, apply the filtering and gather
        // the results.
        $filteredEntitySets = array_map(fn ($expr) => $this->match($entities, $expr), $expressions);

        return array_reduce($filteredEntitySets, 'array_merge', []);
    }

    /**
     * @param Entity[] $entities
     * @return Entity[]
     */
    private function matchCompositeAndExpression(array $entities, CompositeExpression $expr): array
    {
        $expressions = $expr->getExpressionList();
        // This is effectively a reduce operation by intersecting the results
        // of mapping match across the entities, but instead iterative reduces
        // the set to match for performance reasons.
        foreach ($expressions as $expression) {
            $entities = $this->match($entities, $expression);
        }
        return $entities;
    }

    /**
     * @param Entity $entity
     * @return mixed
     */
    private function getValueOfProperty(object $entity, string $property)
    {
        if (!array_key_exists($property, $this->reflectionProperties)) {
            throw new UnexpectedValueException(sprintf(
                'Property "%s" is not a mapped field on class "%s"',
                $property,
                $this->metadata->getName(),
            ));
        }
        return $this->reflectionProperties[$property]->getValue($entity);
    }

    /**
     * @param Entity[] $results
     * @param array<string, string> $orderBy (actually
     * Criteria::ASC|Criteria:::DESC but the PHPStan annotations won't work)
     * @return Entity[]
     */
    private function sortResults(array $results, array $orderBy): array
    {
        // Baseline check for correct field mapping of ORDER BY clauses since
        // they won't be checked if usort no-ops on <2 results
        foreach ($orderBy as $property => $_) {
            if (!array_key_exists($property, $this->reflectionProperties)) {
                throw new UnexpectedValueException(sprintf(
                    'Sort field "%s" is not a mapped field on class "%s"',
                    $property,
                    $this->metadata->getName(),
                ));
            }
        }
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
