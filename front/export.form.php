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

include ('../../../inc/includes.php');

$PluginUseditemsexportExport = new PluginUseditemsexportExport();

if (isset($_REQUEST['generate'])) {

   if ($PluginUseditemsexportExport::generatePDF($_POST['users_id'])) {
      Session::addMessageAfterRedirect(__('PDF successfully generated.', 'useditemsexport'), true);
      Html::back();
   }
}

if (isset($_REQUEST["purgeitem"])) {

   foreach ($_POST["useditemsexport"] as $key => $val) {
      $input = ['id' => $key];
      if ($val == 1) {
         $PluginUseditemsexportExport->delete($input, true);
      }
   }
   Html::back();
}
