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

Session::haveRight('config', UPDATE);

Html::header(
    PluginUseditemsexportConfig::getTypeName(1),
    $_SERVER['PHP_SELF'],
    'plugins',
    'useditemsexport',
    'config',
);

if (!isset($_GET['id'])) {
    $_GET['id'] = 1;
}

$PluginUseditemsexportConfig = new PluginUseditemsexportConfig();

if (isset($_POST['update'])) {
    $PluginUseditemsexportConfig->check($_POST['id'], UPDATE);
    $PluginUseditemsexportConfig->update($_POST);
    Html::back();
}

$PluginUseditemsexportConfig->showForm($_GET['id']);

Html::footer();
