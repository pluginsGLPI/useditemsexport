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

class PluginUseditemsexportProfile extends CommonDBTM {

   // Necassary rights to edit the rights of this plugin
   static $rightname = "profile";

   /**
    * @see CommonGLPI::getTabNameForItem()
   **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType()=='Profile' && $item->getField('interface')!='helpdesk') {
            return PluginUseditemsexportExport::getTypeName();
      }
      return '';
   }

   /**
    * @see CommonGLPI::displayTabContentForItem()
   **/
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI;

      if ($item->getType()=='Profile') {
         $ID = $item->getID();
         $prof = new self();
         //In case there's no right for this profile, create it
         foreach (self::getAllRights() as $right) {
            self::addDefaultProfileInfos($ID, array($right['field'] => 0));
         }
         $prof->showForm($ID);
      }
      return true;
   }

   /**
    * Describe all prossible rights for the plugin
    * @return array
   **/
   static function getAllRights() {

      $rights = array(
          array('itemtype'  => 'PluginUseditemsexportExport',
                'label'     => PluginUseditemsexportExport::getTypeName(),
                'field'     => 'plugin_useditemsexport_export',
                'rights'    =>  array(CREATE  => __('Create'),
                                      READ    => __('Read'),
                                      PURGE   => array('short' => __('Purge'),
                                      'long' => _x('button', 'Delete permanently'))),
                'default'   => 21));
      return $rights;
   }

   /**
    * addDefaultProfileInfos
    * @param $profiles_id
    * @param $rights
   **/
   static function addDefaultProfileInfos($profiles_id, $rights) {
      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if (!countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'")) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);
            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }


   /**
   * Show profile form
   *
   * @param $items_id integer id of the profile
   * @param $target value url of target
   *
   * @return nothing
   **/
   function showForm($profiles_id=0, $openform=TRUE, $closeform=TRUE) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='".$profile->getFormURL()."'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);
      if ($profile->getField('interface') == 'central') {
         $rights = $this->getAllRights();
         $profile->displayRightsChoiceMatrix($rights, array('canedit'       => $canedit,
                                                         'default_class' => 'tab_bg_2',
                                                         'title'         => __('General')));
      }
      
      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', array('value' => $profiles_id));
         echo Html::submit(_sx('button', 'Save'), array('name' => 'update'));
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }

   /**
    * Install all necessary profile for the plugin
    *
    * @return boolean True if success
    */
   static function install(Migration $migration) {
      
      foreach (self::getAllRights() as $right) {
         self::addDefaultProfileInfos($_SESSION['glpiactiveprofile']['id'], 
                                       array($right['field'] => $right['default']));
      }
   }

   /**
    * Uninstall previously installed profile of the plugin
    *
    * @return boolean True if success
    */
   static function uninstall() {
      global $DB;

      foreach (self::getAllRights() as $right) {
         $query = "DELETE FROM `glpi_profilerights` 
                   WHERE `name` = '".$right['field']."'";
         $DB->query($query);

         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }

}