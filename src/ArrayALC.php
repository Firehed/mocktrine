<?php

declare(strict_types=1);

namespace Firehed\Mocktrine;

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
 * @internal
 */
class ArrayALC extends AbstractLazyCollection implements Selectable
{
    public function __construct(
        protected ?Collection $collection,
    ) {
    }

    protected function doInitialize(): void
    {
        // no-op, constructor does this
    }

    public function matching($criteria)
    {
    }
}
