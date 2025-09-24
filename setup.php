<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExport plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of UsedItemsExport.
 *
 * UsedItemsExport is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * UsedItemsExport is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UsedItemsExport. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @author    François Legastelois
 * @copyright Copyright (C) 2016-2022 by UsedItemsExport plugin team.
 * @license   AGPLv3 https://www.gnu.org/licenses/agpl-3.0.html
 * @link      https://github.com/pluginsGLPI/useditemsexport
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;

use function Safe\define;

// Plugin version
define('PLUGIN_USEDITEMSEXPORT_VERSION', '2.6.0');
define('PLUGIN_USEDITEMSEXPORT_MIN_GLPI', '11.0.0');
define('PLUGIN_USEDITEMSEXPORT_MAX_GLPI', '11.0.99');

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_useditemsexport()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $plugin = new Plugin();

    $PLUGIN_HOOKS['csrf_compliant']['useditemsexport'] = true;

    if (Session::getLoginUserID() && $plugin->isActivated('useditemsexport')) {
        PluginUseditemsexportConfig::loadInSession();

        if (Session::haveRight('config', UPDATE)) {
            $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['useditemsexport'] = '../../front/config.form.php?forcetab=PluginUseditemsexportConfig$1';
            Plugin::registerClass(PluginUseditemsexportConfig::class, ['addtabon' => [Config::class]]);
        }

        if (Session::haveRight('profile', UPDATE)) {
            Plugin::registerClass(PluginUseditemsexportProfile::class, ['addtabon' => Profile::class]);
        }

        if (isset($_SESSION['plugins']['useditemsexport']['config'])) {
            $useditemsexport_config = $_SESSION['plugins']['useditemsexport']['config'];

            if (
                Session::haveRightsOr('plugin_useditemsexport_export', [READ, CREATE, PURGE])
                && $useditemsexport_config['is_active']
            ) {
                Plugin::registerClass(PluginUseditemsexportExport::class, ['addtabon' => User::class]);
            }
        }
    }
}


function plugin_useditemsexport_check_prerequisites()
{
    if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
        echo "Run composer install --no-dev in the plugin directory<br>";
        return false;
    }
    return true;
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_useditemsexport()
{
    return  [
        'name'         => __s('Used items export', 'useditemsexport'),
        'version'      => PLUGIN_USEDITEMSEXPORT_VERSION,
        'oldname'      => '',
        'license'      => 'GPLv2+',
        'author'       => 'TECLIB',
        'homepage'     => 'https://github.com/pluginsGLPI/useditemsexport',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_USEDITEMSEXPORT_MIN_GLPI,
                'max' => PLUGIN_USEDITEMSEXPORT_MAX_GLPI,
            ],
        ],
    ];
}
