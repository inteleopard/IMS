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

/** @noinspection
 * PhpUnhandledExceptionInspection
 * PhpDocMissingThrowsInspection
 */

use iMSCP\Authentication\AuthEvent;
use iMSCP\Authentication\AuthResult;
use iMSCP\Authentication\AuthService;
use iMSCP\Crypt;
use iMSCP\Event\Event;
use iMSCP\Event\EventAggregator;
use iMSCP\Event\EventManagerInterface;
use iMSCP\Event\Events;
use iMSCP\Exception\Exception;
use iMSCP\Plugin\BruteForce;
use iMSCP\Registry;

/**
 * Initialize login
 *
 * @param EventManagerInterface $eventManager
 * @return void
 */
function init_login(EventManagerInterface $eventManager)
{
    do_session_timeout();

    if (Registry::get('config')['BRUTEFORCE']) {
        $bruteforce = new BruteForce(Registry::get('pluginManager'));
        $bruteforce->register($eventManager);
    }

    // Register default authentication handler with high-priority
    $eventManager->registerListener(
        Events::onAuthentication, 'login_credentials', 99
    );

    // Register listener that is responsible to check domain status and expire
    // date
    $eventManager->registerListener(
        Events::onBeforeSetIdentity, 'login_checkDomainAccount'
    );
}

/**
 * Credentials authentication handler
 *
 * @param AuthEvent $authEvent
 */
function login_credentials(AuthEvent $authEvent)
{
    $username = (!empty($_POST['uname']))
        ? encode_idna(clean_input($_POST['uname'])) : '';
    $password = (!empty($_POST['upass'])) ? clean_input($_POST['upass']) : '';

    if ($username === '' || $password === '') {
        $message = [];

        if (empty($username)) {
            $message[] = tr('The username field is empty.');
        }

        if (empty($password)) {
            $message[] = tr('The password field is empty.');
        }

        $authEvent->setAuthenticationResult(new AuthResult(
            (count($message) == 2)
                ? AuthResult::FAILURE_CREDENTIAL_EMPTY
                : AuthResult::FAILURE_CREDENTIAL_INVALID,
            NULL,
            $message
        ));
        return;
    }

    $stmt = exec_query(
        '
            SELECT admin_id, admin_name, admin_pass, admin_type, email,
                created_by
            FROM admin
            WHERE admin_name = ?
        ',
        [$username]
    );

    if (!$stmt->rowCount()) {
        $authEvent->setAuthenticationResult(new AuthResult(
            AuthResult::FAILURE_IDENTITY_NOT_FOUND,
            NULL,
            tr('Unknown username.')
        ));
        return;
    }

    $identity = $stmt->fetchRow(PDO::FETCH_OBJ);

    if (!Crypt::hashEqual($identity->admin_pass, md5($password))
        && !Crypt::verify($password, $identity->admin_pass)
    ) {
        $authEvent->setAuthenticationResult(new AuthResult(
            AuthResult::FAILURE_CREDENTIAL_INVALID,
            NULL,
            tr('Bad password.')
        ));
        return;
    }

    if (strpos($identity->admin_pass, '$apr1$') !== 0) {
        // Not an APR-1 hashed password, we recreate the hash

        // We must postpone update until the onAfterAuthentication event to
        // handle cases where the authentication process fail later on (case
        //of a multi-factor authentication process)
        EventAggregator::getInstance()->registerListener(
            Events::onAfterAuthentication,
            function (Event $event) use ($password) {
                /** @var AuthResult $authResult */
                $authResult = $event->getParam('authResult');

                if (!$authResult->isValid()) {
                    return;
                }

                $identity = $authResult->getIdentity();

                exec_query(
                    '
                        UPDATE admin
                        SET admin_pass = ?, admin_status = ?
                        WHERE admin_id = ?
                    ',
                    [
                        Crypt::apr1MD5($password),
                        ($identity->admin_type) == 'user'
                            ? 'tochangepwd' : 'ok',
                        $identity->admin_id
                    ]
                );

                write_log(
                    sprintf(
                        'Password for user %s has been re-encrypted using APR-1 algorithm',
                        $identity->admin_name
                    ),
                    E_USER_NOTICE
                );

                if ($identity->admin_type == 'user') {
                    send_request();
                }
            },
            ['password' => $password, 'identity' => $identity]
        );
    }

    $authEvent->setAuthenticationResult(new AuthResult(
        AuthResult::SUCCESS, $identity
    ));
}

/**
 * Check domain account state (status and expires date)
 *
 * Note: Listen to the onBeforeSetIdentity event triggered in the
 * AuthService component.
 *
 * @param Event $event
 * @return void
 */
function login_checkDomainAccount(Event $event)
{
    /** @var $identity stdClass */
    $identity = $event->getParam('identity');

    if ($identity->admin_type != 'user') {
        return;
    }

    $stmt = exec_query(
        '
            SELECT domain_expires, domain_status, admin_status
            FROM domain
            JOIN admin ON(domain_admin_id = admin_id)
            WHERE domain_admin_id = ?
        ',
        [$identity->admin_id]
    );

    $event->stopPropagation();

    if (!$stmt->rowCount()) {
        write_log(
            sprintf(
                'Account data not found in database for the %s user',
                $identity->admin_name
            ),
            E_USER_ERROR
        );
        set_page_message(
            tohtml(tr('An unexpected error occurred. Please contact your reseller.')),
            'error'
        );
        return;
    }

    $row = $stmt->fetchRow(PDO::FETCH_ASSOC);

    if ($row['admin_status'] == 'disabled'
        || $row['domain_status'] == 'disabled'
    ) {
        set_page_message(
            tohtml(tr(
                'Your account has been disabled. Please, contact your reseller.'
            )),
            'error'
        );
        return;
    }

    if ($row['domain_expires'] > 0 && $row['domain_expires'] < time()) {
        set_page_message(
            tohtml(tr(
                'Your account has expired. Please, contact your reseller.'
            )),
            'error'
        );
        return;
    }

    $event->stopPropagation(false);
}

/**
 * Session garbage collector
 *
 * @return void
 */
function do_session_timeout()
{
    exec_query(
        'DELETE FROM login WHERE lastaccess < ? AND user_name <> ?',
        [
            time() - Registry::get('config')['SESSION_TIMEOUT'] * 60,
            '__bruteforce__'
        ]
    );
}

/**
 * Check login
 *
 * @param string $userLevel User level (admin|reseller|user)
 * @param bool $preventExternalLogin If TRUE, external login is disallowed
 */
function check_login($userLevel, $preventExternalLogin = true)
{
    do_session_timeout();
    $auth = AuthService::getInstance();

    if (!$auth->hasIdentity()) {
        // Ensure deletion of all identity data
        $auth->unsetIdentity();

        if (is_xhr()) {
            showForbiddenErrorPage();
        }

        redirectTo('/index.php');
    }

    $identity = $auth->getIdentity();

    // When the panel is in maintenance mode, only administrators can access
    // the interface
    /** @noinspection PhpUndefinedFieldInspection */
    if (Registry::get('config')['MAINTENANCEMODE']
        && $identity->admin_type != 'admin'
        && (!isset($_SESSION['logged_from_type'])
            || $_SESSION['logged_from_type'] != 'admin'
        )
    ) {
        $auth->unsetIdentity();
        redirectTo('/index.php');
    }

    // Check user level
    /** @noinspection PhpUndefinedFieldInspection */
    if (empty($userLevel) || ($userLevel !== 'all'
            && $identity->admin_type != $userLevel)
    ) {
        $auth->unsetIdentity();
        redirectTo('/index.php');
    }

    // prevent external login / check for referer
    if ($preventExternalLogin
        && !empty($_SERVER['HTTP_REFERER'])
        && ($fromHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST))
        && $fromHost !== getRequestHost()
    ) {
        $auth->unsetIdentity();
        showForbiddenErrorPage();
    }

    // If all goes fine update session and last access
    $_SESSION['user_login_time'] = time();
    exec_query(
        'UPDATE login SET lastaccess = ? WHERE session_id = ?',
        [$_SESSION['user_login_time'], session_id()]
    );
}

/**
 * Switch between user's interfaces
 *
 * @param int $fromId User ID to switch from
 * @param int $toId User ID to switch on
 * @return void
 */
function change_user_interface($fromId, $toId)
{
    $toActionScript = false;

    // We loop over nothing here, it's just a way to avoid code repetition
    while (1) {
        $stmt = exec_query(
            '
              SELECT admin_id, admin_name, admin_type, email, created_by
              FROM admin
              WHERE admin_id IN(?, ?)
              ORDER BY FIELD(admin_id, ?, ?)
              LIMIT 2
            ',
            [$fromId, $toId, $fromId, $toId]
        );

        if ($stmt->rowCount() < 2) {
            set_page_message(tr('Bad request.'), 'error');
        }

        list($from, $to) = $stmt->fetchAll(PDO::FETCH_OBJ);

        $fromToMap = [
            'admin'    => [
                'reseller' => 'users.php',
                'user'     => 'domains_manage.php',
                'back'     => 'users.php'
            ],
            'reseller' => [
                'user' => 'domains_manage.php',
                'back' => 'users.php'
            ]
        ];

        if (!isset($fromToMap[$from->admin_type][$to->admin_type])
            || ($from->admin_type == $to->admin_type)
        ) {
            if (!isset($_SESSION['logged_from_id'])
                || $_SESSION['logged_from_id'] != $to->admin_id
            ) {
                set_page_message(tr('Bad request.'), 'error');
                write_log(
                    sprintf(
                        "%s tried to switch onto %s's interface",
                        $from->admin_name,
                        decode_idna($to->admin_name)
                    ),
                    E_USER_WARNING
                );
                break;
            }

            $toActionScript = $fromToMap[$to->admin_type]['back'];
        }

        $toActionScript = $toActionScript
            ?: $fromToMap[$from->admin_type][$to->admin_type];

        // Set new identity
        $auth = AuthService::getInstance();
        $auth->unsetIdentity();

        if ($from->admin_type != 'user' && $to->admin_type != 'admin') {
            // Set additional data about user from which we are logged from
            $_SESSION['logged_from_type'] = $from->admin_type;
            $_SESSION['logged_from'] = $from->admin_name;
            $_SESSION['logged_from_id'] = $from->admin_id;
            write_log(
                sprintf("%s switched onto %s's interface",
                    $from->admin_name,
                    decode_idna($to->admin_name)
                ),
                E_USER_NOTICE
            );
        } else {
            write_log(
                sprintf(
                    "%s switched back from %s's interface",
                    $to->admin_name,
                    decode_idna($from->admin_name)
                ),
                E_USER_NOTICE
            );
        }

        $auth->setIdentity($to);
        break;
    }

    redirectToUiLevel($toActionScript);
}

/**
 * Redirects to user ui level
 *
 * @param string $actionScript Action script on which user should be redirected
 * @return void
 */
function redirectToUiLevel($actionScript = NULL)
{
    $auth = AuthService::getInstance();

    if (!$auth->hasIdentity()) {
        return;
    }

    /** @noinspection PhpUndefinedFieldInspection */
    switch ($auth->getIdentity()->admin_type) {
        case 'user':
            $userType = 'client';
            if (NULL === $actionScript) {
                $actionScript = 'domains_manage.php';
            }
            break;
        case 'admin':
            $userType = 'admin';
            if (NULL === $actionScript) {
                $actionScript = 'users.php';
            }
            break;
        case 'reseller':
            $userType = 'reseller';
            if (NULL === $actionScript) {
                $actionScript = 'users.php';
            }
            break;
        default:
            throw new Exception('Unknown UI level');
    }

    redirectTo('/' . $userType . '/' . $actionScript);
}
