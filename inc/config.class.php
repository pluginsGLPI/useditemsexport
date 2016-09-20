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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginUseditemsexportConfig extends CommonDBTM {

   static $rightname = 'config';

   /**
    * Display name of itemtype
    *
    * @return value name of this itemtype
    **/
   static function getTypeName($nb=0) {

      return __('General setup of useditemsexport', 'useditemsexport');
   }

   /**
    * Print the config form
    *
    * @param $ID        Integer : ID of the item
    * @param $options   array
    *
    * @return Nothing (display)
   **/
   function showForm($ID, $options=array()) {

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Active')."</td>";
      echo "<td>";
      Dropdown::showYesNo("is_active",$this->fields["is_active"]);
      echo "</td></tr>";

      $this->showFormButtons($options);
   }

   /**
    * Load configuration plugin in GLPi Session
    *
    * @return nothing
    */
   static function loadInSession() {
      $config = new self();
      $config->getFromDB(1);
      unset($config->fields['id']);
      $_SESSION['plugins']['useditemsexport']['config'] = $config->fields;
   }

   /**
    * Install all necessary table for the plugin
    *
    * @return boolean True if success
    */
   static function install(Migration $migration) {
      global $DB;

      $table = getTableForItemType(__CLASS__);

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `is_active` TINYINT(1) NOT NULL DEFAULT '0',
               PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
            $DB->query($query) or die ($DB->error());

         $query = "INSERT INTO `$table` (id, is_active) VALUES (1, 0)";
         $DB->query($query) or die ($DB->error());
      }
   }

   /**
    * Uninstall previously installed table of the plugin
    *
    * @return boolean True if success
    */
   static function uninstall() {
      global $DB;

      $table = getTableForItemType(__CLASS__);

      $query = "DROP TABLE IF EXISTS  `".$table."`";
      $DB->query($query) or die ($DB->error());
   }

}