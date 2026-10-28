<?php

namespace App\Pagination;

use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * One page of results, with what the template needs to draw the pager.
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Page implements \IteratorAggregate, \Countable
{
    private readonly int $totalItems;

    /**
     * @param Paginator<T> $paginator
     */
    public function __construct(
        private readonly Paginator $paginator,
        public readonly int $current,
        public readonly int $perPage,
    ) {
        $this->totalItems = \count($paginator);
    }

    public function getIterator(): \Traversable
    {
        return $this->paginator->getIterator();
    }

    /** Number of items on this page. */
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getLastPage(): int
    {
        return max(1, (int) ceil($this->totalItems / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->current > 1;
    }

    public function hasNext(): bool
    {
        return $this->current < $this->getLastPage();
    }
}
