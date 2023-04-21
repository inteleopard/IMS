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
 * PhpUnhandledExceptionInspection
 * PhpDocMissingThrowsInspection
 * PhpIncludeInspection
 */

use iMSCP\Database\DatabaseMySQL;
use iMSCP\Event\EventAggregator;
use iMSCP\Event\Events;
use iMSCP\Exception\Exception;
use iMSCP\PhpEditor;
use iMSCP\Registry;
use iMSCP\TemplateEngine;
use iMSCP\Uri\UriException;
use iMSCP\Uri\UriRedirect;

/**
 * Get customers list
 *
 * @return array Domains list
 */
function getCustomersList()
{
    static $customersList = NULL;

    if (NULL !== $customersList) {
        return $customersList;
    }

    $stmt = exec_query(
        '
            SELECT admin_id, admin_name, domain_id
            FROM admin
            JOIN domain ON(domain_admin_id = admin_id)
            WHERE created_by = ?
            AND admin_status = ?
            ORDER BY admin_name
        ',
        [$_SESSION['user_id'], 'ok']
    );

    if (!$stmt->rowCount()) {
        showBadRequestErrorPage();
    }

    $customersList = $stmt->fetchAll();
    return $customersList;
}

/**
 * Get domains list for the given customer
 *
 * @param int $customerId Customer unique identifier
 * @return array Domains list
 */
function getDomainsList($customerId)
{
    static $domainsList = NULL;

    if (NULL !== $domainsList) {
        return $domainsList;
    }

    $domainsList = [];
    $mainDmnProps = get_domain_default_props(
        $customerId, $_SESSION['user_id']
    );

    $domainsList = [
        [
            'name'        => $mainDmnProps['domain_name'],
            'id'          => $mainDmnProps['domain_id'],
            'type'        => 'dmn',
            'mount_point' => '/'
        ]
    ];

    $stmt = exec_query(
        "
            SELECT CONCAT(t1.subdomain_name, '.', t2.domain_name) AS name,
                t1.subdomain_mount AS mount_point
            FROM subdomain AS t1
            JOIN domain AS t2 USING(domain_id)
            WHERE t1.domain_id = :domain_id
            AND t1.subdomain_status = :status_ok
            AND t1.subdomain_url_forward = 'no'
            UNION ALL
            SELECT alias_name AS name, alias_mount AS mount_point
            FROM domain_aliasses WHERE domain_id = :domain_id
            AND alias_status = :status_ok
            AND url_forward = 'no'
            UNION ALL
            SELECT CONCAT(t1.subdomain_alias_name, '.', t2.alias_name) AS name,
                t1.subdomain_alias_mount AS mount_point
            FROM subdomain_alias AS t1
            JOIN domain_aliasses AS t2 USING(alias_id)
            WHERE t2.domain_id = :domain_id
            AND t1.subdomain_alias_status = :status_ok
            AND t1.subdomain_alias_url_forward = 'no'
        ",
        [
            'domain_id' => $mainDmnProps['domain_id'],
            'status_ok' => 'ok'
        ]
    );

    if ($stmt->rowCount()) {
        $domainsList = array_merge($domainsList, $stmt->fetchAll());
        usort($domainsList, function ($a, $b) {
            return strnatcmp(decode_idna($a['name']), decode_idna($b['name']));
        });
    }

    return $domainsList;
}

/**
 * Get Json domains list for the given customer
 *
 * @param int $customerId Customer unique identifier
 * @return string Json Domains list
 */
function getJsonDomainsList($customerId)
{
    $jsonData = [];

    foreach (getDomainsList($customerId) as $domain) {
        $jsonData[] = [
            'domain_name'         => tohtml($domain['name']),
            'domain_name_unicode' => tohtml(decode_idna($domain['name']))
        ];
    }

    return json_encode($jsonData);
}

/**
 * Generate page
 *
 * @param $tpl TemplateEngine
 * @return void
 */
function generatePage($tpl)
{
    $customersList = getCustomersList();

    foreach ($customersList as $customer) {
        $tpl->assign([
            'CUSTOMER_ID'       => tohtml($customer['admin_id']),
            'CUSTOMER_NAME'     => tohtml(decode_idna($customer['admin_name'])),
            'CUSTOMER_SELECTED' => isset($_POST['customer']) ? ' selected' : ''
        ]);
        $tpl->parse('CUSTOMER_OPTION', '.customer_option');
    }

    $forwardType = (
        isset($_POST['forward_type'])
        && in_array(
            $_POST['forward_type'], ['301', '302', '303', '307', 'proxy'], true
        )
    ) ? $_POST['forward_type'] : '302';
    $forwardHost = $forwardType == 'proxy' && isset($_POST['forward_host'])
        ? 'On' : 'Off';
    $wildcardAlias = isset($_POST['wildcard_alias'])
    && in_array($_POST['wildcard_alias'], ['yes', 'no'], true)
        ? $_POST['wildcard_alias'] : 'no';

    $tpl->assign([
        'DOMAIN_ALIAS_NAME'  => isset($_POST['domain_alias_name'])
            ? tohtml($_POST['domain_alias_name'], 'htmlAttr') : '',
        'FORWARD_URL_YES'    => isset($_POST['url_forwarding'])
        && $_POST['url_forwarding'] == 'yes'
            ? ' checked' : '',
        'FORWARD_URL_NO'     => isset($_POST['url_forwarding'])
        && $_POST['url_forwarding'] == 'yes'
            ? '' : ' checked',
        'HTTP_YES'           => isset($_POST['forward_url_scheme'])
        && $_POST['forward_url_scheme'] == 'http://'
            ? ' selected' : '',
        'HTTPS_YES'          => isset($_POST['forward_url_scheme'])
        && $_POST['forward_url_scheme'] == 'https://'
            ? ' selected' : '',
        'FORWARD_URL'        => isset($_POST['forward_url'])
            ? tohtml($_POST['forward_url']) : '',
        'FORWARD_TYPE_301'   => $forwardType == '301' ? ' checked' : '',
        'FORWARD_TYPE_302'   => $forwardType == '302' ? ' checked' : '',
        'FORWARD_TYPE_303'   => $forwardType == '303' ? ' checked' : '',
        'FORWARD_TYPE_307'   => $forwardType == '307' ? ' checked' : '',
        'FORWARD_TYPE_PROXY' => $forwardType == 'proxy' ? ' checked' : '',
        'FORWARD_HOST'       => $forwardHost == 'On' ? ' checked' : '',
        'WILDCARD_ALIAS_YES' => $wildcardAlias == 'yes' ? ' checked' : '',
        'WILDCARD_ALIAS_NO'  => $wildcardAlias == 'no' ? ' checked' : ''
    ]);

    $domainList = getDomainsList(
        isset($_POST['customer_id'])
            ? clean_input($_POST['customer_id'])
            : $customersList[0]['admin_id']
    );

    if (!empty($domainList)) {
        $tpl->assign([
            'SHARED_MOUNT_POINT_YES' => isset($_POST['shared_mount_point'])
            && $_POST['shared_mount_point'] == 'yes'
                ? ' checked' : '',
            'SHARED_MOUNT_POINT_NO'  => isset($_POST['shared_mount_point'])
            && $_POST['shared_mount_point'] == 'yes'
                ? '' : ' checked',
        ]);

        foreach ($domainList as $domain) {
            $tpl->assign([
                'DOMAIN_NAME'                        => tohtml($domain['name']),
                'DOMAIN_NAME_UNICODE'                => tohtml(decode_idna($domain['name'])),
                'SHARED_MOUNT_POINT_DOMAIN_SELECTED' => isset($_POST['shared_mount_point_domain'])
                && $_POST['shared_mount_point_domain'] == $domain['name']
                    ? ' selected' : ''
            ]);
            $tpl->parse('SHARED_MOUNT_POINT_DOMAIN', '.shared_mount_point_domain');
        }
    } else {
        $tpl->assign('SHARED_MOUNT_POINT_OPTION_JS', '');
        $tpl->assign('SHARED_MOUNT_POINT_OPTION', '');
    }
}

/**
 * Add new domain alias
 *
 * @return bool
 */
function addDomainAlias()
{
    // Basic check
    if (empty($_POST['customer_id'])) {
        showBadRequestErrorPage();
    }

    $customerId = clean_input($_POST['customer_id']);

    if (empty($_POST['domain_alias_name'])) {
        set_page_message(tr('You must enter a domain alias name.'), 'error');
        return false;
    }

    $domainAliasName = mb_strtolower(clean_input($_POST['domain_alias_name']));

    // Check for domain alias name syntax
    global $dmnNameValidationErrMsg;
    if (!isValidDomainName($domainAliasName)) {
        set_page_message($dmnNameValidationErrMsg, 'error');
        return false;
    }

    // www is considered as an alias of the domain alias
    while (strpos($domainAliasName, 'www.') !== false) {
        $domainAliasName = substr($domainAliasName, 4);
    }

    // Check for domain alias existence
    if (imscp_domain_exists($domainAliasName, $_SESSION['user_id'])) {
        set_page_message(
            tohtml(tr('Domain %s is unavailable.'), $domainAliasName),
            'error'
        );
        return false;
    }

    $domainAliasNameAscii = encode_idna($domainAliasName);

    // Set default mount point
    $mountPoint = "/$domainAliasNameAscii";

    // Check for shared mount point option
    if (isset($_POST['shared_mount_point'])
        && $_POST['shared_mount_point'] == 'yes'
    ) {
        if (!isset($_POST['shared_mount_point_domain'])) {
            showBadRequestErrorPage();
        }

        $sharedMountPointDomain = clean_input(
            $_POST['shared_mount_point_domain']
        );
        $domainList = getDomainsList($customerId);

        if (!empty($domainList)) {
            // Get shared mount point
            foreach ($domainList as $domain) {
                if ($domain['name'] == $sharedMountPointDomain) {
                    $mountPoint = $domain['mount_point'];
                }
            }
        } else {
            showBadRequestErrorPage();
        }
    }

    // Default values
    $documentRoot = '/htdocs';
    $forwardUrl = 'no';
    $forwardType = NULL;
    $forwardHost = 'Off';

    // Check for URL forwarding option
    if (isset($_POST['url_forwarding'])
        && $_POST['url_forwarding'] == 'yes'
        && isset($_POST['forward_type'])
        && in_array(
            $_POST['forward_type'], ['301', '302', '303', '307', 'proxy'], true
        )
    ) {
        if (!isset($_POST['forward_url_scheme'])
            || !isset($_POST['forward_url'])
        ) {
            showBadRequestErrorPage();
        }

        $forwardUrl = clean_input($_POST['forward_url_scheme'])
            . clean_input($_POST['forward_url']);
        $forwardType = clean_input($_POST['forward_type']);

        if ($forwardType == 'proxy' && isset($_POST['forward_host'])) {
            $forwardHost = 'On';
        }

        try {
            try {
                $uri = UriRedirect::fromString($forwardUrl);
            } catch (UriException $e) {
                throw new Exception(
                    tr('Forward URL %s is not valid.', $forwardUrl)
                );
            }

            // Normalize URI host
            $uri->setHost(encode_idna(mb_strtolower($uri->getHost())));
            // Normalize URI path
            $uri->setPath(rtrim(utils_normalizePath($uri->getPath()), '/') . '/');

            if ($uri->getHost() == $domainAliasNameAscii
                && ($uri->getPath() == '/'
                    && in_array($uri->getPort(), ['', 80, 443])
                )
            ) {
                throw new Exception(
                    tr('Forward URL %s is not valid.', $forwardUrl) . ' ' .
                    tr('Domain alias %s cannot be forwarded on itself.', $domainAliasName)
                );
            }

            if ($forwardType == 'proxy') {
                $port = $uri->getPort();
                if ($port && $port < 1025) {
                    throw new Exception(
                        tr('Unallowed port in forward URL. Only ports above 1024 are allowed.')
                    );
                }
            }

            $forwardUrl = $uri->getUri();
        } catch (Exception $e) {
            set_page_message(tohtml($e->getMessage()), 'error');
            return false;
        }
    }

    $wildcardAlias = isset($_POST['wildcard_alias'])
    && in_array($_POST['wildcard_alias'], ['yes', 'no'], true)
        ? $_POST['wildcard_alias'] : 'no';

    $mainDmnProps = get_domain_default_props(
        $customerId, $_SESSION['user_id']
    );
    $db = DatabaseMySQL::getInstance();

    try {
        $db->beginTransaction();

        EventAggregator::getInstance()->dispatch(
            Events::onBeforeAddDomainAlias,
            [
                'domainId'        => $mainDmnProps['domain_id'],
                'domainAliasName' => $domainAliasNameAscii,
                'mountPoint'      => $mountPoint,
                'documentRoot'    => $documentRoot,
                'forwardUrl'      => $forwardUrl,
                'forwardType'     => $forwardType,
                'forwardHost'     => $forwardHost,
                'wildcardAlias'   => $wildcardAlias
            ]
        );

        exec_query(
            '
                INSERT INTO domain_aliasses (
                    domain_id, alias_name, alias_mount, alias_document_root,
                        alias_status, alias_ip_id, url_forward, type_forward,
                        host_forward, wildcard_alias
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ',
            [
                $mainDmnProps['domain_id'], $domainAliasNameAscii, $mountPoint,
                $documentRoot, 'toadd', $mainDmnProps['domain_ip_id'],
                $forwardUrl, $forwardType, $forwardHost, $wildcardAlias
            ]
        );

        $id = $db->insertId();

        // Create the phpini entry for that domain alias
        $phpini = PhpEditor::getInstance();
        // Load reseller PHP permissions
        $phpini->loadResellerPermissions($_SESSION['user_id']);
        // Load client PHP permissions
        $phpini->loadClientPermissions($mainDmnProps['admin_id']);
        // Load main domain PHP configuration options
        $phpini->loadDomainIni(
            $mainDmnProps['admin_id'], $mainDmnProps['domain_id'], 'dmn'
        );
        $phpini->saveDomainIni($mainDmnProps['admin_id'], $id, 'als');

        // Create default email addresses if needed
        if (Registry::get('config')['CREATE_DEFAULT_EMAIL_ADDRESSES']) {
            createDefaultMailAccounts(
                $mainDmnProps['domain_id'],
                $mainDmnProps['email'],
                $domainAliasNameAscii,
                MT_ALIAS_FORWARD,
                $id
            );
        }

        EventAggregator::getInstance()->dispatch(
            Events::onAfterAddDomainAlias,
            [
                'domainId'        => $mainDmnProps['domain_id'],
                'domainAliasName' => $domainAliasNameAscii,
                'domainAliasId'   => $id,
                'mountPoint'      => $mountPoint,
                'documentRoot'    => $documentRoot,
                'forwardUrl'      => $forwardUrl,
                'forwardType'     => $forwardType,
                'forwardHost'     => $forwardHost,
                'wildcardAlias'   => $wildcardAlias
            ]
        );

        $db->commit();
        send_request();
        write_log(
            sprintf(
                'A new domain alias (%s) has been added by %s',
                $domainAliasName,
                $_SESSION['user_logged']
            ),
            E_USER_NOTICE
        );
        set_page_message(
            tohtml(tr('Domain alias successfully scheduled for addition.')),
            'success'
        );
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    return true;
}

require_once 'imscp-lib.php';

check_login('reseller');
EventAggregator::getInstance()->dispatch(Events::onResellerScriptStart);
resellerHasFeature('domain_aliases')
&& resellerHasCustomers() or showBadRequestErrorPage();

if (is_xhr() && isset($_POST['customer_id'])) {
    echo getJsonDomainsList(clean_input($_POST['customer_id']));
    return;
}

$resellerProps = imscp_getResellerProperties($_SESSION['user_id']);
if ($resellerProps['max_als_cnt'] != 0
    && $resellerProps['current_als_cnt'] >= $resellerProps['max_als_cnt']
) {
    set_page_message(
        tohtml(tr('You have reached the maximum number of domain aliases allowed by your subscription.')),
        'warning'
    );
    redirectTo('users.php');
}

if (!empty($_POST) && addDomainAlias()) {
    redirectTo('alias.php');
}

$tpl = new TemplateEngine();
$tpl->define_dynamic([
    'layout'                       => 'shared/layouts/ui.tpl',
    'page'                         => 'reseller/alias_add.tpl',
    'page_message'                 => 'layout',
    'customer_option'              => 'page',
    'shared_mount_point_option_js' => 'page',
    'shared_mount_point_option'    => 'page',
    'shared_mount_point_domain'    => 'shared_mount_point_option'
]);
$tpl->assign([
    'TR_PAGE_TITLE'                 => tohtml(tr('Reseller / Domains / Add Domain Alias')),
    'TR_CUSTOMER_ACCOUNT'           => tohtml(tr('Customer account')),
    'TR_DOMAIN_ALIAS'               => tohtml(tr('Domain alias')),
    'TR_DOMAIN_ALIAS_NAME'          => tohtml(tr('Domain alias name')),
    'TR_SHARED_MOUNT_POINT'         => tohtml(tr('Shared mount point')),
    'TR_SHARED_MOUNT_POINT_TOOLTIP' => tohtml(tr('Allows to share the mount point of another domain.'), 'htmlAttr'),
    'TR_URL_FORWARDING'             => tohtml(tr('URL forwarding')),
    'TR_URL_FORWARDING_TOOLTIP'     => tohtml(tr('Allows to forward any request made to this domain to a specific URL.'), 'htmlAttr'),
    'TR_FORWARD_TO_URL'             => tohtml(tr('Forward to URL')),
    'TR_YES'                        => tohtml(tr('Yes')),
    'TR_NO'                         => tohtml(tr('No')),
    'TR_HTTP'                       => tohtml('http://'),
    'TR_HTTPS'                      => tohtml('https://'),
    'TR_FORWARD_TYPE'               => tohtml(tr('Forward type')),
    'TR_301'                        => tohtml('301'),
    'TR_302'                        => tohtml('302'),
    'TR_303'                        => tohtml('303'),
    'TR_307'                        => tohtml('307'),
    'TR_PROXY'                      => tohtml('PROXY'),
    'TR_PROXY_PRESERVE_HOST'        => tohtml(tr('Preserve Host')),
    'TR_WILDCARD_ALIAS_TOOLTIP'     => tohtml(tr("If enabled, a wildcard alias entry such as '*.domain.tld' will be added in the Web server configuration. This option is most suitable for software that provide multisite feature such as the Wordpress CMS. Be aware that the control panel won't check for possible conflicts with subdomains."), 'htmlAttr'),
    'TR_WILDCARD_ALIAS'             => tohtml(tr('Wildcard alias')),
    'TR_ADD'                        => tohtml(tr('Add'), 'htmlAttr'),
    'TR_CANCEL'                     => tohtml(tr('Cancel'))
]);

generateNavigation($tpl);
generatePage($tpl);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
EventAggregator::getInstance()->dispatch(
    Events::onResellerScriptEnd, ['templateEngine' => $tpl]
);
$tpl->prnt();

unsetMessages();
