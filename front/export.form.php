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

include('../../../inc/includes.php');

Session::checkLoginUser();

$PluginUseditemsexportExport = new PluginUseditemsexportExport();

if (isset($_REQUEST['generate'])) {
    Session::checkRight('plugin_useditemsexport_export', CREATE);
    if ($PluginUseditemsexportExport::generatePDF($_POST['users_id'])) {
        Session::addMessageAfterRedirect(__s('PDF successfully generated.', 'useditemsexport'), true);
        Html::back();
    }
}

if (isset($_REQUEST['purgeitem'])) {
    Session::checkRight('plugin_useditemsexport_export', PURGE);
    foreach ($_POST['useditemsexport'] as $key => $val) {
        $input = ['id' => $key];
        if ($val == 1) {
            $PluginUseditemsexportExport->delete($input, true);
        }
    }
    Html::back();
}
