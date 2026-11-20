<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\{
    Comparison,
    CompositeExpression,
    Expression,
};
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\Mapping\ClassMetadata;
use UnexpectedValueException;

use function array_keys;
use function array_map;
use function in_array;
use function is_int;
use function sprintf;

/**
 * Adjusts a Criteria so that ArrayCollection::matching() behaves like a
 * database query: only mapped fields may be used, and an int compared to a
 * float column matches the equal float value.
 *
 * @template Entity of object
 *
 * @internal
 */
class CriteriaNormalizer
{
    /**
     * @param ClassMetadata<Entity> $metadata
     */
    public function __construct(
        private ClassMetadata $metadata,
    ) {
    }

    public function normalize(Criteria $criteria): Criteria
    {
        $orderings = $criteria->getOrderings();
        foreach (array_keys($orderings) as $field) {
            $this->assertMapped($field);
        }

        $where = $criteria->getWhereExpression();
        return new Criteria(
            $where === null ? null : $this->normalizeExpression($where),
            $orderings,
            $criteria->getFirstResult() ?? 0,
            $criteria->getMaxResults(),
        );
    }

    private function normalizeExpression(Expression $expr): Expression
    {
        return match (true) {
            $expr instanceof Comparison => $this->normalizeComparison($expr),
            $expr instanceof CompositeExpression => new CompositeExpression(
                $expr->getType(),
                array_map($this->normalizeExpression(...), $expr->getExpressionList()),
            ),
        };
    }

    private function normalizeComparison(Comparison $comparison): Comparison
    {
        $field = $comparison->getField();
        $this->assertMapped($field);

        $operator = $comparison->getOperator();
        $value = $comparison->getValue()->getValue();
        if (
            is_int($value)
            && in_array($operator, [Comparison::EQ, Comparison::NEQ], true)
            && $this->metadata->getTypeOfField($field) === Types::FLOAT
        ) {
            return new Comparison($field, $operator, (float) $value);
        }
        return $comparison;
    }

    private function assertMapped(string $field): void
    {
        if (!$this->metadata->hasField($field)) {
            throw new UnexpectedValueException(sprintf(
                '"%s" is not a mapped field on class "%s"',
                $field,
                $this->metadata->getName(),
            ));
        }
    }
}
