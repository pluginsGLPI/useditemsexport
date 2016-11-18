<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2015-2016 Teclib'.

 http://glpi-project.org

 based on GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2014 by the INDEPNET Development Team.

 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// Plugin version
define("PLUGIN_USEDITEMEXPORT_VERSION", "1.0.0");
// Minimal GLPI version, inclusive
define("PLUGIN_USEDITEMEXPORT_MIN_GLPI", "0.90");
// Maximum GLPI version, exclusive
define("PLUGIN_USEDITEMEXPORT_MAX_GLPI", "9.2");

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
                                 array('addtabon' => 'Profile'));
      }

       if (isset($_SESSION['plugins']['useditemsexport']['config'])) {

          $useditemsexport_config = $_SESSION['plugins']['useditemsexport']['config'];

         if (Session::haveRightsOr('plugin_useditemsexport_export', array(READ, CREATE, PURGE))
               && $useditemsexport_config['is_active']) {
            
            Plugin::registerClass('PluginUseditemsexportExport', 
                                    array('addtabon' => 'User'));
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

   return array (
      'name' => __('Used items export', 'useditemsexport'),
      'version' => PLUGIN_USEDITEMEXPORT_VERSION,
      'oldname' => '',
      'license' => 'GPLv2+',
      'author'  => "TECLIB",
      'homepage'=>'https://github.com/pluginsGLPI/useditemsexport',
      'minGlpiVersion' => PLUGIN_USEDITEMEXPORT_MIN_GLPI,
   );
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_useditemsexport_check_prerequisites() {

   if (version_compare(GLPI_VERSION, PLUGIN_USEDITEMEXPORT_MIN_GLPI,'lt')
      || version_compare(GLPI_VERSION, PLUGIN_USEDITEMEXPORT_MAX_GLPI,'ge')
   ) {
      echo sprintf(
         __('This plugin requires GLPi >= %1$s and < %2$s'),
         PLUGIN_USEDITEMEXPORT_MIN_GLPI,
         PLUGIN_USEDITEMEXPORT_MAX_GLPI
      );
      return false;
   }

   $autoload = dirname(__DIR__) . '/useditemsexport/vendor/autoload.php';
   if (!file_exists($autoload)) {
      _e('Run "composer install --no-dev" in the plugin tree', 'useditemsexport');
      return false;
   }
   return true;
}

/**
 * Check configuration process
 * OPTIONNAL, but recommanded
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_useditemsexport_check_config($verbose=false) {
   if (true) { // Your configuration check
      return true;
   }

   if ($verbose) {
      _e('Installed / not configured', 'useditemsexport');
   }
   return false;
}
