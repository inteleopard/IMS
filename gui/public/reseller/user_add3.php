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
 * PhpIncludeInspection
 */

use iMSCP\Database\DatabaseMySQL;
use iMSCP\Event\EventAggregator;
use iMSCP\Event\Events;
use iMSCP\Exception\Exception;
use iMSCP\PhpEditor;
use iMSCP\Registry;
use iMSCP\TemplateEngine;

/**
 * Get data from previous step
 *
 * @return bool
 */
function getPreviousStepData()
{
    global $adminName, $hpId, $dmnName, $dmnExpire, $dmnUrlForward,
           $dmnTypeForward, $dmnHostForward, $dmnWildcardAlias;

    $dmnExpire = $_SESSION['dmn_expire'];
    $dmnUrlForward = $_SESSION['dmn_url_forward'];
    $dmnTypeForward = $_SESSION['dmn_type_forward'];
    $dmnHostForward = $_SESSION['dmn_host_forward'];
    $dmnWildcardAlias = $_SESSION['dmn_wildcard_alias'];

    if (isset($_SESSION['step_one'])) {
        $stepTwo = $_SESSION['dmn_name'] . ';' . $_SESSION['dmn_tpl'];
        $hpId = $_SESSION['dmn_tpl'];
        unset($_SESSION['dmn_name']);
        unset($_SESSION['dmn_tpl']);
        unset($_SESSION['chtpl']);
        unset($_SESSION['step_one']);
    } elseif (isset($_SESSION['step_two_data'])) {
        $stepTwo = $_SESSION['step_two_data'];
        unset($_SESSION['step_two_data']);
    } elseif (isset($_SESSION['local_data'])) {
        $stepTwo = $_SESSION['local_data'];
        unset($_SESSION['local_data']);
    } else {
        $stepTwo = "'';0";
    }

    list($dmnName, $hpId) = explode(';', $stepTwo);
    $adminName = $dmnName;

    if (!isValidDomainName($dmnName) || $hpId == '') {
        return false;
    }

    return true;
}

/**
 * Add customer user
 *
 * @param Zend_Form $form
 * @return void
 */
function addCustomer(Zend_Form $form)
{
    global $hpId, $dmnName, $dmnExpire, $dmnUrlForward, $dmnTypeForward,
           $dmnHostForward, $dmnWildcardAlias, $domainIp, $adminName;

    if (!isset($_POST['domain_ip'])) {
        showBadRequestErrorPage();
    }

    if (!$form->isValid($_POST)) {
        foreach ($form->getMessages() as $fieldname => $msgsStack) {
            set_page_message(reset($msgsStack), 'error');
        }

        return;
    }

    $domainIp = intval($_POST['domain_ip']);
    $stmt = exec_query(
        'SELECT reseller_ips FROM reseller_props WHERE reseller_id = ?',
        [$_SESSION['user_id']]
    );
    if (!$stmt->rowCount()) {
        throw new Exception(sprintf(
            'Could not find IPs for reseller with ID %s', $_SESSION['user_id']
        ));
    }

    $resellerIps = $stmt->fetchRow();
    $resellerIps = explode(';', rtrim($resellerIps['reseller_ips'], ';'));
    if (!in_array($domainIp, $resellerIps)) {
        showBadRequestErrorPage();
    }

    $cfg = Registry::get('config');

    if (isset($_SESSION['ch_hpprops'])) {
        $props = $_SESSION['ch_hpprops'];
        unset($_SESSION['ch_hpprops']);
    } else {
        $stmt = exec_query(
            'SELECT props FROM hosting_plans WHERE reseller_id = ? AND id = ?',
            [$_SESSION['user_id'], $hpId]
        );
        $data = $stmt->fetchRow();
        $props = $data['props'];
    }

    list(
        $php, $cgi, $sub, $als, $mail, $ftp, $sql_db, $sql_user, $traff, $disk,
        $backup, $dns, $phpEditor, $phpiniAllowUrlFopen,
        $phpiniDisplayErrors, $phpiniDisableFunctions, $phpMailFunction,
        $phpiniPostMaxSize, $phpiniUploadMaxFileSize, $phpiniMaxExecutionTime,
        $phpiniMaxInputTime, $phpiniMemoryLimit, $extMailServer,
        $webFolderProtection, $mailQuota
        ) = explode(';', $props);

    $php = str_replace('_', '', $php);
    $cgi = str_replace('_', '', $cgi);
    $backup = str_replace('_', '', $backup);
    $dns = str_replace('_', '', $dns);
    $extMailServer = str_replace('_', '', $extMailServer);
    $webFolderProtection = str_replace('_', '', $webFolderProtection);
    $db = DatabaseMySQL::getInstance();

    try {
        $db->beginTransaction();

        exec_query(
            "
                INSERT INTO admin (
                    admin_name, admin_pass, admin_type, domain_created,
                    created_by, fname, lname, firm, zip, city, state, country,
                    email, phone, fax, street1, street2, gender, admin_status
                ) VALUES (
                    ?, ?, ?, unix_timestamp(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, 'toadd'
                )
            ",
            [
                $adminName, iMSCP\Crypt::apr1MD5($form->getValue('admin_pass')),
                'user', $_SESSION['user_id'], $form->getValue('fname'),
                $form->getValue('lname'), $form->getValue('firm'),
                $form->getValue('zip'), $form->getValue('city'),
                $form->getValue('state'), $form->getValue('country'),
                encode_idna($form->getValue('email')), $form->getValue('phone'),
                $form->getValue('fax'),
                $form->getValue('street1'), $form->getValue('street2'),
                $form->getValue('gender')
            ]
        );

        $adminId = $db->insertId();

        EventAggregator::getInstance()->dispatch(
            Events::onBeforeAddDomain,
            [
                'createdBy'     => $_SESSION['user_id'],
                'customerId'    => $adminId,
                'customerEmail' => $form->getValue('email'),
                'domainName'    => $dmnName,
                'mountPoint'    => '/',
                'documentRoot'  => '/htdocs',
                'forwardUrl'    => $dmnUrlForward,
                'forwardType'   => $dmnTypeForward,
                'forwardHost'   => $dmnHostForward,
                'wildcardAlias' => $dmnWildcardAlias
            ]
        );

        exec_query(
            '
                INSERT INTO domain (
                    domain_name, domain_admin_id, domain_created,
                    domain_expires, domain_mailacc_limit, domain_ftpacc_limit,
                    domain_traffic_limit, domain_sqld_limit, domain_sqlu_limit,
                    domain_status, domain_alias_limit, domain_subd_limit,
                    domain_ip_id, domain_disk_limit, domain_disk_usage,
                    domain_php, domain_cgi, allowbackup, domain_dns,
                    phpini_perm_system, phpini_perm_allow_url_fopen,
                    phpini_perm_display_errors, phpini_perm_disable_functions,
                    phpini_perm_mail_function, domain_external_mail,
                    web_folder_protection, mail_quota, url_forward, type_forward,
                    host_forward, wildcard_alias
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ',
            [
                $dmnName, $adminId, time(), $dmnExpire, $mail, $ftp, $traff,
                $sql_db, $sql_user, 'toadd', $als, $sub, $domainIp, $disk, 0,
                $php, $cgi, $backup, $dns, $phpEditor,
                $phpiniAllowUrlFopen, $phpiniDisplayErrors,
                $phpiniDisableFunctions, $phpMailFunction, $extMailServer,
                $webFolderProtection, $mailQuota, $dmnUrlForward,
                $dmnTypeForward, $dmnHostForward, $dmnWildcardAlias
            ]
        );

        $dmnId = $db->insertId();

        $phpini = PhpEditor::getInstance();
        // Load reseller PHP permissions
        $phpini->loadResellerPermissions($_SESSION['user_id']);
        // Load client default PHP permissions
        $phpini->loadClientPermissions();
        // Load domain default PHP configuration options
        $phpini->loadDomainIni();

        // Must be set before phpiniPostMaxSize
        $phpini->setDomainIni('phpiniMemoryLimit', $phpiniMemoryLimit);
        // Must be set before phpiniUploadMaxFileSize
        $phpini->setDomainIni('phpiniPostMaxSize', $phpiniPostMaxSize);
        $phpini->setDomainIni(
            'phpiniUploadMaxFileSize', $phpiniUploadMaxFileSize
        );
        $phpini->setDomainIni(
            'phpiniMaxExecutionTime', $phpiniMaxExecutionTime
        );
        $phpini->setDomainIni('phpiniMaxInputTime', $phpiniMaxInputTime);
        $phpini->saveDomainIni($adminId, $dmnId, 'dmn');

        if ($cfg['CREATE_DEFAULT_EMAIL_ADDRESSES']) {
            createDefaultMailAccounts(
                $dmnId, $form->getValue('email'), $dmnName
            );
        }

        send_add_user_auto_msg(
            $_SESSION['user_id'], $adminName, $form->getValue('admin_pass'),
            $form->getValue('email'), $form->getValue('fname'),
            $form->getValue('lname'), tr('Customer')
        );
        exec_query(
            '
                INSERT INTO user_gui_props (
                    user_id, lang, layout
                ) VALUES (?, ?, ?)
            ',
            [$adminId, $cfg['USER_INITIAL_LANG'], $cfg['USER_INITIAL_THEME']]
        );
        update_reseller_c_props($_SESSION['user_id']);

        EventAggregator::getInstance()->dispatch(
            Events::onAfterAddDomain,
            [
                'createdBy'     => $_SESSION['user_id'],
                'customerId'    => $adminId,
                'customerEmail' => $form->getValue('email'),
                'domainId'      => $dmnId,
                'domainName'    => $dmnName,
                'mountPoint'    => '/',
                'documentRoot'  => '/htdocs',
                'forwardUrl'    => $dmnUrlForward,
                'forwardType'   => $dmnTypeForward,
                'forwardHost'   => $dmnHostForward,
                'wildcardAlias' => $dmnWildcardAlias
            ]
        );

        $db->commit();
        send_request();
        write_log(
            sprintf(
                'A new customer (%s) has been created by: %s',
                $adminName, $_SESSION['user_logged']
            ),
            E_USER_NOTICE
        );
        set_page_message(
            tohtml(tr('Customer account successfully scheduled for creation.')),
            'success'
        );
        unsetMessages();
        redirectTo('users.php');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Generates page
 *
 * @param  TemplateEngine $tpl Template engine
 * @param Zend_Form $form
 * @return void
 */
function generatePage(TemplateEngine $tpl, Zend_Form $form)
{
    global $hpId, $dmnName, $domainIp;

    $form->setDefault('admin_name', $dmnName);
    /** @noinspection PhpUndefinedFieldInspection */
    $tpl->form = $form;

    reseller_generate_ip_list($tpl, $_SESSION['user_id'], $domainIp);
    $_SESSION['local_data'] = "$dmnName;$hpId";
}

require 'imscp-lib.php';

check_login('reseller');
EventAggregator::getInstance()->dispatch(Events::onResellerScriptStart);

if (!getPreviousStepData()) {
    set_page_message(
        tohtml(tr('Data were altered. Please try again.')), 'error'
    );
    unsetMessages();
    redirectTo('user_add1.php');
}

$form = getUserLoginDataForm(false, true)->addElements(
    getUserPersonalDataForm()->getElements()
);
$form->setDefault('gender', 'U');

if (isset($_POST['uaction'])
    && 'user_add3_nxt' == $_POST['uaction']
    && !isset($_SESSION['step_two_data'])
) {
    addCustomer($form);
} else {
    unset($_SESSION['step_two_data']);
}

$tpl = new TemplateEngine();
$tpl->define_dynamic([
    'layout'       => 'shared/layouts/ui.tpl',
    'page'         => 'reseller/user_add3.phtml',
    'page_message' => 'layout',
    'ip_entry'     => 'page'
]);
$tpl->assign('TR_PAGE_TITLE', tohtml(tr('Reseller / Customers / Add Customer - Next Step')));

generateNavigation($tpl);
generatePage($tpl, $form);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
EventAggregator::getInstance()->dispatch(
    Events::onResellerScriptEnd, ['templateEngine' => $tpl]
);
$tpl->prnt();