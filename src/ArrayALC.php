<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use BadMethodCallException;
use Doctrine\Common\Collections\{
    AbstractLazyCollection,
    Criteria,
    Collection,
    Selectable,
};

/**
 * Since we're currently forced to extend EntityRepository instead of merely
 * implementing the interface in orm:3, we're consequently forced to return an
 * implementation-specific `AbstractLazyCollection` intead of the interface's
 * `ReadableCollection`. This does some light wrapping to allow things to work
 * for the common case.
 *
 * @see doctrine/orm#11019
 *
 * @template TKey of array-key
 * @template T of object
 *
 * @extends AbstractLazyCollection<TKey, T>
 * @implements Selectable<TKey, T>
 *
 * @internal
 */
class ArrayALC extends AbstractLazyCollection implements Selectable
{
    /**
     * @param Collection<TKey, T> $collection
     */
    public function __construct(
        Collection $collection,
    ) {
        $this->collection = $collection;
    }

    protected function doInitialize(): void
    {
        // no-op, constructor does this
    }

    public function matching(Criteria $criteria)
    {
        throw new BadMethodCallException('Nested matching not supported');
    }
}
