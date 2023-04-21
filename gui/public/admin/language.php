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
use iMSCP\TemplateEngine;

require 'imscp-lib.php';

check_login('admin');
EventAggregator::getInstance()->dispatch(Events::onAdminScriptStart);

$tpl = new TemplateEngine();
$tpl->define_dynamic([
    'layout'              => 'shared/layouts/ui.tpl',
    'page'                => 'admin/language.tpl',
    'page_message'        => 'layout',
    'languages_available' => 'page',
    'def_language'        => 'languages_available'
]);

$adminCurrentLanguage = $_SESSION['user_def_lang'];

if (!empty($_POST)) {
    $adminNewLanguage = clean_input($_POST['def_language']);

    if (!in_array($adminNewLanguage, i18n_getAvailableLanguages(true), true)) {
        showBadRequestErrorPage();
    }

    if ($adminCurrentLanguage != $adminNewLanguage) {
        exec_query('UPDATE user_gui_props SET lang = ? WHERE user_id = ?', [$adminNewLanguage, $_SESSION['user_id']]);
        $_SESSION['user_def_lang'] = $adminNewLanguage;
        set_page_message(tr('Language has been updated.'), 'success');
    } else {
        set_page_message(tr('Nothing has been changed.'), 'info');
    }

    redirectTo('language.php');
}

$tpl->assign([
    'TR_PAGE_TITLE'      => tr('Admin / Profile / Language'),
    'TR_LANGUAGE'        => tr('Language'),
    'TR_CHOOSE_LANGUAGE' => tr('Choose your language'),
    'TR_UPDATE'          => tr('Update')
]);

generateNavigation($tpl);
generateLanguagesList($tpl, $adminCurrentLanguage);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
EventAggregator::getInstance()->dispatch(Events::onAdminScriptEnd, ['templateEngine' => $tpl]);
$tpl->prnt();

unsetMessages();
