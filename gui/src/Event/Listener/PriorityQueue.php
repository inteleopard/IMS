<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2019 by Laurent Declercq <l.declercq@nuxwin.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * @noinspection
 * PhpDocMissingThrowsInspection
 * PhpUnhandledExceptionInspection
 */

declare(strict_types=1);

namespace iMSCP\Event\Listener;

use Countable;
use IteratorAggregate;

/**
 * Class PriorityQueue
 * @package iMSCP\Event\Listener
 */
class PriorityQueue implements Countable, IteratorAggregate
{
    /**
     * Actual items aggregated in the priority queue. Each item is an array with
     * keys "listener" and "priority"
     *
     * @var array
     */
    protected $items = [];

    /**
     * @var SplPriorityQueue Inner queue object
     */
    protected $queue;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->queue = new SplPriorityQueue();
    }

    /**
     * Add the given listener into the queue.
     *
     * Priority defaults to 1 (low priority) if none provided.
     *
     * @param EventListener $listener Listener
     * @param int $priority Listener priority
     * @return PriorityQueue
     */
    public function addListener(EventListener $listener, $priority = 1)
    {
        $priority = (int)$priority;
        $this->items[] = ['listener' => $listener, 'priority' => $priority];
        $this->queue->insert($listener, $priority);

        return $this;
    }

    /**
     * Remove the given listener from the queue
     *
     * Note: This removes the first listener matching the provided listener
     * found. If the same listener item has been added multiple times, it will
     * not remove other instances.
     *
     * @param EventListener $listener Listener to remove from the queue
     * @return bool FALSE if the item was not found, TRUE otherwise.
     */
    public function removeListener(EventListener $listener)
    {
        $key = false;

        foreach ($this->items as $key => $item) {
            if ($item['listener'] === $listener) {
                break;
            }
        }

        if ($key) {
            unset($this->items[$key]);
            $this->queue = new SplPriorityQueue();

            foreach ($this->items as $item) {
                $this->queue->insert($item['listener'], $item['priority']);
            }

            return true;
        }

        return false;
    }

    /**
     * Is the queue empty?
     *
     * @return bool TRUE if the queue is empty, FALSE otherwise
     */
    public function isEmpty()
    {
        return (0 === $this->count());
    }

    /**
     * How many items are in the queue?
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * Retrieve the inner iterator
     *
     * SplPriorityQueue acts as a heap, which typically implies that as items
     * are iterated, they are also removed. This method retrieves the inner
     * queue object, and clones it for purposes of iteration.
     *
     * @return SplPriorityQueue
     */
    public function getIterator()
    {
        return clone $this->queue;
    }

    /**
     * Does the queue have a listener with the given priority?
     *
     * @param int $priority
     * @return bool
     */
    public function hasPriority($priority)
    {
        foreach ($this->items as $item) {
            if ($item['priority'] === $priority) {
                return true;
            }
        }

        return false;
    }
}
