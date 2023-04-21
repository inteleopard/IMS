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

use iMSCP\Assertion\ResellerCanViewCustomerStatisticsAssertion;
use iMSCP\Assertion\ResellerHasApsFeatureAssertion;
use iMSCP\Assertion\ResellerHasCircularFeatureAssertion;
use iMSCP\Assertion\ResellerHasDomainAliasesFeatureAssertion;
use iMSCP\Assertion\ResellerHasSupportFeatureAssertion;

return [
    'general'       => [
        'label' => tr('General'),
        'uri'   => '/reseller/index.php',
        'class' => 'general',
        'pages' => [
            'overview'        => [
                'label'       => tr('Overview'),
                'uri'         => '/reseller/index.php',
                'title_class' => 'general'
            ]
        ]
    ],
    'customers'     => [
        'label' => tr('Customers'),
        'uri'   => '/reseller/users.php',
        'class' => 'manage_users',
        'pages' => [
            'overview'          => [
                'label'       => tr('Overview'),
                'uri'         => '/reseller/users.php',
                'title_class' => 'users',
                'pages'       => [
                    'domain_detail' => [
                        'label'       => tr('Domain details'),
                        'uri'         => '/reseller/domain_details.php',
                        'title_class' => 'domains'
                    ],
                    'domain_edit'   => [
                        'label'       => tr('Edit domain'),
                        'uri'         => '/reseller/domain_edit.php',
                        'title_class' => 'domains'
                    ],
                    'customer_edit' => [
                        'label'       => tr('Edit customer'),
                        'uri'         => '/reseller/user_edit.php',
                        'title_class' => 'user_blue'
                    ]
                ]
            ],
            'add_customer'      => [
                'label'       => tr('Add customer'),
                'uri'         => '/reseller/user_add1.php',
                'title_class' => 'user',
                'pages'       => [
                    'add_customer_p2' => [
                        'label'       => tr('Add customer - Next step'),
                        'uri'         => '/reseller/user_add2.php',
                        'title_class' => 'user'
                    ],
                    'add_customer_p3' => [
                        'label'       => tr('Add customer - Next step'),
                        'uri'         => '/reseller/user_add3.php',
                        'title_class' => 'user'
                    ],
                    'add_customer_p4' => [
                        'label'       => tr('Add customer - Next step'),
                        'uri'         => '/reseller/user_add4.php',
                        'title_class' => 'user'
                    ]
                ]
            ],
            'manage_aliasses'   => [
                'label'       => tr('Domain aliases'),
                'uri'         => '/reseller/alias.php',
                'title_class' => 'domains',
                'resource'    => 'domain_aliases',
                'assertion'   => ResellerHasDomainAliasesFeatureAssertion::class,
                'pages'       => [
                    'add_alias'  => [
                        'label'       => tr('Add domain alias'),
                        'uri'         => '/reseller/alias_add.php',
                        'title_class' => 'domains'
                    ],
                    'edit_alias' => [
                        'label'       => tr('Edit domain alias'),
                        'uri'         => '/reseller/alias_edit.php',
                        'title_class' => 'domains'
                    ]
                ]
            ],
            'welcome_mail'      => [
                'label'       => tr('Welcome email'),
                'uri'         => '/reseller/settings_welcome_mail.php',
                'title_class' => 'email'
            ],
            'lostpassword_mail' => [
                'label'       => tr('Lost password email'),
                'uri'         => '/reseller/settings_lostpassword.php',
                'title_class' => 'email'
            ],
            'circular'          => [
                'label'       => tr('Circular'),
                'uri'         => '/reseller/circular.php',
                'title_class' => 'email',
                'resource'    => 'circular',
                'assertion'   => ResellerHasCircularFeatureAssertion::class
            ]
        ]
    ],
    'hosting_plans' => [
        'label' => tr('Hosting plans'),
        'uri'   => '/reseller/hosting_plan.php',
        'class' => 'hosting_plans',
        'pages' => [
            'overview'         => [
                'label'       => tr('Hosting plans'),
                'uri'         => '/reseller/hosting_plan.php',
                'title_class' => 'hosting_plans',
                'pages'       => [
                    'hosting_plan_edit' => [
                        'label'       => tr('Edit hosting plan'),
                        'uri'         => '/reseller/hosting_plan_edit.php',
                        'title_class' => 'hosting_plans',
                    ]
                ]
            ],
            'add_hosting_plan' => [
                'label'       => tr('Add hosting plan'),
                'uri'         => '/reseller/hosting_plan_add.php',
                'title_class' => 'hosting_plans',
                'class'       => 'hosting_plan_add'
            ]
        ]
    ],
    'statistics'    => [
        'label'     => tr('Statistics'),
        'uri'       => '/reseller/user_statistics.php',
        'class'     => 'statistics',
        'resource'  => 'user_statistics',
        'assertion' => ResellerCanViewCustomerStatisticsAssertion::class,
        'pages'     => [
            'user_statistics' => [
                'label'       => tr('User statistics'),
                'uri'         => '/reseller/user_statistics.php',
                'title_class' => 'stats',
                'pages'       => [
                    'user_statistics_details' => [
                        'label'       => tr('{USERNAME} user statistics'),
                        'uri'         => '/reseller/user_statistics_details.php',
                        'title_class' => 'stats'
                    ]
                ]
            ],
            'ip_usage'        => [
                'label'       => tr('IP assignments'),
                'uri'         => '/reseller/ip_usage.php',
                'title_class' => 'stats'
            ]
        ]
    ],
    'support'       => [
        'label'     => tr('Support'),
        'uri'       => '{SUPPORT_SYSTEM_PATH}',
        'target'    => '{SUPPORT_SYSTEM_TARGET}',
        'class'     => 'support',
        'resource'  => 'support',
        'assertion' => ResellerHasSupportFeatureAssertion::class,
        'pages'     => [
            'open_tickets'   => [
                'label'       => tr('Open tickets'),
                'uri'         => '/reseller/ticket_system.php',
                'title_class' => 'support',
                'pages'       => [
                    'view_ticket' => [
                        'label'       => tr('View ticket'),
                        'uri'         => '/reseller/ticket_view.php',
                        'title_class' => 'support'
                    ]
                ]
            ],
            'closed_tickets' => [
                'label'       => tr('Closed tickets'),
                'uri'         => '/reseller/ticket_closed.php',
                'title_class' => 'support'
            ],
            'new_ticket'     => [
                'label'       => tr('New ticket'),
                'uri'         => '/reseller/ticket_create.php',
                'title_class' => 'support'
            ]
        ]
    ],
    'profile'       => [
        'label' => tr('Profile'),
        'uri'   => '/reseller/profile.php',
        'class' => 'profile',
        'pages' => [
            'account_summary' => [
                'label'       => tr('Account summary'),
                'uri'         => '/reseller/profile.php',
                'title_class' => 'profile'
            ],
            'language'        => [
                'label'       => tr('Language'),
                'uri'         => '/reseller/language.php',
                'title_class' => 'multilanguage'
            ],
            'layout'          => [
                'label'       => tr('Layout'),
                'uri'         => '/reseller/layout.php',
                'title_class' => 'layout'
            ],
            'password'        => [
                'label'       => tr('Password'),
                'uri'         => '/reseller/password_update.php',
                'title_class' => 'profile'
            ],
            'personal_data'   => [
                'label'       => tr('Personal data'),
                'uri'         => '/reseller/personal_change.php',
                'title_class' => 'profile'
            ]
        ]
    ]
];
