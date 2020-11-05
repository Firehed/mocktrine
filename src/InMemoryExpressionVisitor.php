<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use Doctrine\Common\Collections\Expr\{
    Comparison,
    CompositeExpression,
    Expression,
    ExpressionVisitor,
    Value,
};
use DomainException;
use ReflectionClass;
use UnexpectedValueException;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * @template Entity of object
 *
 * @internal
 */
class InMemoryExpressionVisitor // extends ExpressionVisitor
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
    public function dispatch(?Expression $expr): array
    {
        if ($expr instanceof Comparison) {
            $entities = $this->walkComparison($expr);
        } elseif ($expr instanceof CompositeExpression) {
            $entities = $this->walkCompositeExpression($expr);
        } elseif ($expr instanceof Value) {
            // ??
        } elseif ($expr === null) {
            $entities = $this->entities;
        } else {
            throw new DomainException(sprintf('Unknown expression class %s', get_class($expr)));
        }
        return $entities;
    }

    /**
     * @return Entity[]
     */
    public function walkComparison(Comparison $expr): array
    {
        $field = $expr->getField();
        $value = $expr->getValue()->getValue(); // Unwrap it
        // var_dump($field, $value);
        switch ($expr->getOperator()) {
            case Comparison::EQ:
                $matching = array_filter(
                    $this->entities,
                    function ($entity) use ($field, $value): bool {
                        return $this->getValueOfProperty($entity, $field) === $value;
                    },
                );
                return $matching;
            default:
                throw new DomainException(sprintf('Unhandled operator %s', $expr->getOperator()));
        }
    }

    // public const NEQ         = '<>';
    // public const LT          = '<';
    // public const LTE         = '<=';
    // public const GT          = '>';
    // public const GTE         = '>=';
    // public const IS          = '='; // no difference with EQ
    // public const IN          = 'IN';
    // public const NIN         = 'NIN';
    // public const CONTAINS    = 'CONTAINS';
    // public const MEMBER_OF   = 'MEMBER_OF';
    // public const STARTS_WITH = 'STARTS_WITH';
    // public const ENDS_WITH   = 'ENDS_WITH';
    // public function walkValue(Value $value)
    // {
    // }

    /**
     * @return Entity[]
     */
    public function walkCompositeExpression(CompositeExpression $expr): array
    {
        $expressions = $expr->getExpressionList();
        // For each expression in the composite, apply the filtering and gather
        // the results.
        $filteredEntitySets = array_map([$this, 'dispatch'], $expressions);

        // Reduce the result sets to a single result set based on the
        // expression type:
        // - AND returns only the items in every set (by strict comparision)
        // - OR returns the unique set of items across all sets
        $type = $expr->getType();
        if ($type === CompositeExpression::TYPE_AND) {
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
                    // var_dump($carry);
                    // var_dump($item);
                    // return $carry;
                },
                // [],
            );
        } elseif ($type === CompositeExpression::TYPE_OR) {
            return array_reduce($filteredEntitySets, 'array_merge', []);
        } else {
            throw new DomainException('Recived unhandled expression $type');
        }

        // print_r($filteredEntitySets);
    }

    /**
     * FIXME: copied from InMemoryRepository
     *
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
}
