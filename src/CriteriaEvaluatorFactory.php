<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use function array_key_exists;

/**
 * @template Entity of object
 *
 * @internal
 */
class CriteriaEvaluatorFactory
{
    /**
     * @var array<class-string<Entity>, CriteriaEvaluator<Entity>>
     */
    private static array $instances = [];

    /**
     * @param class-string<Entity> $className
     * @return CriteriaEvaluator<Entity>
     */
    public static function getInstance(string $className): CriteriaEvaluator
    {
        if (!array_key_exists($className, self::$instances)) {
            self::$instances[$className] = new CriteriaEvaluator($className);
        }
        // @phpstan-ignore-next-line (see #3273)
        return self::$instances[$className];
    }
}
