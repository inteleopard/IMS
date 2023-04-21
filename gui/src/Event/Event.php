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

namespace iMSCP\Event;

use ArrayAccess;

/**
 * Class Event
 *
 * Representation of an event
 *
 * Encapsulates the parameters passed, and provides some behavior for
 * interacting with the events manager.
 *
 * @package iMSCP\Event
 */
class Event implements EventDescription
{
    /**
     * @var string Event name
     */
    protected $name;

    /**
     * @var array|ArrayAccess|object The event parameters
     */
    protected $params = [];

    /**
     * @var bool Whether or not to stop propagation
     */
    protected $stopPropagation = false;

    /**
     * Constructor
     *
     * @param string $name Event name
     * @param array|ArrayAccess $params
     * @throws EventException
     */
    public function __construct($name = NULL, $params = NULL)
    {
        if (NULL !== $name) {
            $this->setName($name);
        }

        if (NULL !== $params) {
            $this->setParams($params);
        }
    }

    /**
     * Returns event name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the event name
     *
     * @param string $name Event Name
     * @return Event Provides fluent interface, returns self
     */
    public function setName($name)
    {
        $this->name = (string)$name;

        return $this;
    }

    /**
     * Returns all parameters
     *
     * @return array|object|ArrayAccess
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Set parameters
     *
     * Overwrites parameters
     *
     * @param array|ArrayAccess|object $params
     * @return Event Provides fluent interface, returns self
     * @throws EventException
     */
    public function setParams($params)
    {
        if (!is_array($params) && !is_object($params)) {
            throw new EventException(
                'Event parameters must be an array or object'
            );
        }

        $this->params = $params;

        return $this;
    }

    /**
     * Return an individual parameter
     *
     * If the parameter does not exist, the $default value will be returned.
     *
     * @param string|int $name Parameter name
     * @param mixed $default Default value to be returned if $name doesn't exist
     * @return mixed
     */
    public function getParam($name, $default = NULL)
    {
        // Check in params that are arrays or implement array access
        if (is_array($this->params) || $this->params instanceof ArrayAccess) {
            if (!isset($this->params[$name])) {
                return $default;
            }

            return $this->params[$name];
        }

        // Check in normal objects
        if (!isset($this->params->{$name})) {
            return $default;
        }

        return $this->params->{$name};
    }

    /**
     * Set an individual parameter to a value
     *
     * @param string|int $name Parameter name
     * @param mixed $value Parameter value
     * @return Event
     */
    public function setParam($name, $value)
    {
        if (is_array($this->params) || $this->params instanceof ArrayAccess) {
            // Arrays or objects implementing array access
            $this->params[$name] = $value;
        } else {
            // Objects
            $this->params->{$name} = $value;
        }

        return $this;
    }

    /**
     * Stop further event propagation
     *
     * @param bool $flag TRUE to stop propagation, FALSE otherwise
     * @return void
     */
    public function stopPropagation($flag = true)
    {
        $this->stopPropagation = (bool)$flag;
    }

    /**
     * Is propagation stopped?
     *
     * @return bool TRUE if propagation is stopped, FALSE otherwise
     */
    public function propagationIsStopped()
    {
        return $this->stopPropagation;
    }
}
