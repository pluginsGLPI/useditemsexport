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

function plugin_init_useditemsexport() {
   global $PLUGIN_HOOKS,$CFG_GLPI;

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

function plugin_version_useditemsexport() {

   return array (
      'name' => __('Used items export', 'useditemsexport'),
      'version' => '1.0.0',
      'oldname' => '',
      'license' => 'GPLv2+',
      'author'  => "TECLIB",
      'homepage'=>'https://github.com/pluginsGLPI/useditemsexport',
      'minGlpiVersion' => '0.90',
   );
}

function plugin_useditemsexport_check_prerequisites() {

   if (version_compare(GLPI_VERSION,'0.90','lt') || version_compare(GLPI_VERSION,'9.2','ge')) {
      _e('This plugin requires GLPi >= 0.90 and < 9.2', 'useditemsexport');
      return false;
   }

   $autoload = dirname(__DIR__) . '/useditemsexport/vendor/autoload.php';
   if (!file_exists($autoload)) {
      _e('Run "composer install --no-dev" in the plugin tree', 'useditemsexport');
      return false;
   }
   return true;
}

function plugin_useditemsexport_check_config() {

   return true;
}
