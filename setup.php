<?php
/**
 * --------------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of useditemsexport.
 *
 * useditemsexport is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * useditemsexport is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * --------------------------------------------------------------------------
 * @author    François Legastelois
 * @copyright Copyright (C) 2015-2018 by Teclib' and contributors.
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/useditemsexport
 * @link      https://pluginsglpi.github.io/useditemsexport/
 * -------------------------------------------------------------------------
 */

// Plugin version
define("PLUGIN_USEDITEMEXPORT_VERSION", "2.0.0");
// Minimal GLPI version, inclusive
define("PLUGIN_USEDITEMEXPORT_MIN_GLPI", "9.2");
// Maximum GLPI version, exclusive
define("PLUGIN_USEDITEMEXPORT_MAX_GLPI", "9.3");

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_useditemsexport() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $plugin = new Plugin();

   $PLUGIN_HOOKS['csrf_compliant']['useditemsexport'] = true;

   if (Session::getLoginUserID() && $plugin->isActivated('useditemsexport')) {

      PluginUseditemsexportConfig::loadInSession();

      if (Session::haveRight('config', UPDATE)) {
         $PLUGIN_HOOKS['config_page']['useditemsexport'] = 'front/config.form.php';
      }

      if (Session::haveRight('profile', UPDATE)) {
         Plugin::registerClass('PluginUseditemsexportProfile',
                                 ['addtabon' => 'Profile']);
      }

      if (isset($_SESSION['plugins']['useditemsexport']['config'])) {

         $useditemsexport_config = $_SESSION['plugins']['useditemsexport']['config'];

         if (Session::haveRightsOr('plugin_useditemsexport_export', [READ, CREATE, PURGE])
              && $useditemsexport_config['is_active']) {

            Plugin::registerClass('PluginUseditemsexportExport',
                                    ['addtabon' => 'User']);
         }
      }
   }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_useditemsexport() {

   return  [
      'name' => __('Used items export', 'useditemsexport'),
      'version' => PLUGIN_USEDITEMEXPORT_VERSION,
      'oldname' => '',
      'license' => 'GPLv2+',
      'author'  => "TECLIB",
      'homepage'=>'https://github.com/pluginsGLPI/useditemsexport',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_USEDITEMEXPORT_MIN_GLPI,
            'max' => PLUGIN_USEDITEMEXPORT_MAX_GLPI,
            'dev' => true
         ]
      ]
   ];
}

/**
 * Check pre-requisites before install
 *
 * @return boolean
 */
function plugin_useditemsexport_check_prerequisites() {
   return true;
}

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_useditemsexport_check_config($verbose = false) {
   return true;
}
