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

use iMSCP\Event\EventAggregator;
use iMSCP\Event\Events;
use iMSCP\Registry;
use iMSCP\TemplateEngine;
use iMSCP\Update\VersionUpdate;

/**
 * Generate page
 *
 * @param TemplateEngine $tpl
 * @return void
 */
function admin_generatePage(TemplateEngine $tpl)
{
    $cfg = Registry::get('config');

    if (!isset($cfg['CHECK_FOR_UPDATES']) || !$cfg['CHECK_FOR_UPDATES']) {
        set_page_message(tr('i-MSCP version update checking is disabled'), 'static_warning');
    } else {
        $updateVersion = VersionUpdate::getInstance();

        if ($updateVersion->isAvailableUpdate()) {
            if (($updateInfo = $updateVersion->getUpdateInfo())) {
                $date = new DateTime($updateInfo['published_at']);
                $tpl->assign(
                    [
                        'TR_UPDATE_INFO'         => tr('Update info'),
                        'TR_RELEASE_VERSION'     => tr('Release version'),
                        'RELEASE_VERSION'        => tohtml($updateInfo['tag_name']),
                        'TR_RELEASE_DATE'        => tr('Release date'),
                        'RELEASE_DATE'           => tohtml($date->format($cfg['DATE_FORMAT'])),
                        'TR_RELEASE_DESCRIPTION' => tr('Release description'),
                        'RELEASE_DESCRIPTION'    => tohtml($updateInfo['body']),
                        'TR_DOWNLOAD_LINKS'      => tr('Download links'),
                        'TR_DOWNLOAD_ZIP'        => tr('Download ZIP'),
                        'TR_DOWNLOAD_TAR'        => tr('Download TAR'),
                        'TARBALL_URL'            => tohtml($updateInfo['tarball_url']),
                        'ZIPBALL_URL'            => tohtml($updateInfo['zipball_url'])
                    ]
                );
                return;
            } else {
                set_page_message($updateVersion->getError(), 'error');
            }
        } elseif ($updateVersion->getError()) {
            set_page_message($updateVersion, 'error');
        } else {
            set_page_message(tr('No update available'), 'static_info');
        }
    }

    $tpl->assign('UPDATE_INFO', '');
}

require 'imscp-lib.php';

check_login('admin');
EventAggregator::getInstance()->dispatch(Events::onAdminScriptStart);

stripos(Registry::get('config')['Version'], 'git') === false
&& Registry::get('config')['CHECK_FOR_UPDATES'] or showBadRequestErrorPage();

$tpl = new TemplateEngine();
$tpl->define_dynamic(
    [
        'layout'       => 'shared/layouts/ui.tpl',
        'page'         => 'admin/imscp_updates.tpl',
        'page_message' => 'layout',
        'update_info'  => 'page'
    ]
);
$tpl->assign('TR_PAGE_TITLE', tohtml(tr('Admin / System Tools / i-MSCP Updates')));

generateNavigation($tpl);
admin_generatePage($tpl);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
EventAggregator::getInstance()->dispatch(Events::onAdminScriptEnd, ['templateEngine' => $tpl]);
$tpl->prnt();

unsetMessages();
