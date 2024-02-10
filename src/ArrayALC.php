<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

use BadMethodCallException;
use Doctrine\Common\Collections\{
    AbstractLazyCollection,
    Collection,
    Selectable,
};

/**
 * see doctrine/orm#xxxx
 *
 * force AbstractLazyCollection instead of simple ReadableCollection
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

    public function matching($criteria)
    {
        throw new BadMethodCallException('Nested matching not supported');
    }
}
