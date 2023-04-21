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

namespace iMSCP\Authentication;

use stdClass;

/**
 * Class AuthResult
 * @package iMSCP\Authentication
 */
class AuthResult
{
    /**
     * @const int General Failure
     */
    const FAILURE = 0;

    /**
     * @const int Failure due to identity not being found
     */
    const FAILURE_IDENTITY_NOT_FOUND = -1;

    /**
     * @const int Failure due to identity being ambiguous.
     */
    const FAILURE_IDENTITY_AMBIGUOUS = -2;

    /**
     * @const int Failure due to invalid credential being supplied
     */
    const FAILURE_CREDENTIAL_INVALID = -3;

    /**
     * @const int Failure due to empty credential
     */
    const FAILURE_CREDENTIAL_EMPTY = -4;

    /**
     * @const int Failure due to un-categorized reasons
     */
    const FAILURE_UNCATEGORIZED = -5;

    /**
     * @const int Authentication success
     */
    const SUCCESS = 1;

    /**
     * Authentication result code
     *
     * @var int
     */
    protected $_code;

    /**
     * @var stdClass The identity used in the authentication attempt
     */
    protected $_identity;

    /**
     * An array of string reasons why the authentication attempt was
     * unsuccessful
     *
     * If authentication was successful, this should be an empty array.
     *
     * @var array
     */
    protected $_messages;

    /**
     * Sets the result code, identity, and failure messages
     *
     * @param int $code
     * @param mixed $identity
     * @param array|string $messages Message(s)
     */
    public function __construct($code, $identity, $messages = [])
    {
        $code = (int)$code;

        if ($code < self::FAILURE_UNCATEGORIZED) {
            $code = self::FAILURE;
        } elseif ($code > self::SUCCESS) {
            $code = 1;
        }

        $this->_code = $code;
        $this->_identity = $identity;
        $this->_messages = (array)$messages;
    }

    /**
     * Returns whether the result represents a successful authentication attempt
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return ($this->_code > 0) ? true : false;
    }

    /**
     * getCode() - Get the result code for this authentication attempt
     *
     * @return int
     */
    public function getCode(): int
    {
        return $this->_code;
    }

    /**
     * Returns the identity used in the authentication attempt
     *
     * @return mixed
     */
    public function getIdentity()
    {
        return $this->_identity;
    }

    /**
     * Set error messages
     *
     * @param array|string $messages Message(s)
     * @return void
     */
    public function setMessage($messages): void
    {
        $this->_messages = (array)$messages;
    }

    /**
     * Returns an array of string reasons why the authentication attempt was
     * unsuccessful
     *
     * If authentication was successful, this method returns an empty array.
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->_messages;
    }
}
