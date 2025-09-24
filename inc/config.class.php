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

use Glpi\Application\View\TemplateRenderer;

use function Safe\copy;
use function Safe\mkdir;

class PluginUseditemsexportConfig extends CommonDBTM
{
    public static $rightname = 'config';

    /**
     * Display name of itemtype
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return __s('Used items export', 'useditemsexport');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case "Config":
                return self::createTabEntry(self::getTypeName(), 0, $item::getType(), self::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $config = new self();
        switch ($item->getType()) {
            case "Config":
                $config->showConfigForm();
        }

        return true;
    }


    public function showConfigForm()
    {
        $this->getFromDB(1);

        TemplateRenderer::getInstance()->display(
            '@useditemsexport/config.html.twig',
            [
                'action'  => Toolbox::getItemTypeFormURL(__CLASS__),
                'item'    => $this,
            ],
        );

        return true;
    }

    /**
     * Show dropdown Orientation (Landscape / Portrait)
     * @param string $value (current preselected value)
     * @return void (display dropdown)
     */
    public function dropdownOrientation($value)
    {
        Dropdown::showFromArray(
            'orientation',
            ['L'    => __s('Landscape', 'useditemsexport'),
                'P' => __s('Portrait', 'useditemsexport'),
            ],
            ['value' => $value],
        );
    }

    /**
     * Show dropdown Format (A4, A3, etc...)
     * @param string $value (current preselected value)
     * @return void (display dropdown)
     */
    public function dropdownFormat($value)
    {
        Dropdown::showFromArray(
            'format',
            ['A3'    => __s('A3'),
                'A4' => __s('A4'),
                'A5' => __s('A5'),
            ],
            ['value' => $value],
        );
    }

    /**
     * Load configuration plugin in GLPi Session
     *
     * @return void
     */
    public static function loadInSession()
    {
        $config = new self();
        $config->getFromDB(1);
        unset($config->fields['id']);
        $_SESSION['plugins']['useditemsexport']['config'] = $config->fields;
    }

    /**
     * Install all necessary tables for the plugin
     *
     * @return boolean True if success
     */
    public static function install(Migration $migration)
    {
        /** @var DBmysql $DB */
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        $table = getTableForItemType(__CLASS__);

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `footer_text` VARCHAR(255) DEFAULT '',
                     `is_active` TINYINT NOT NULL DEFAULT 1,
                     `orientation` VARCHAR(1) NOT NULL DEFAULT 'P',
                     `format` VARCHAR(2) NOT NULL DEFAULT 'A4',
               PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);

            $DB->insert($table, ['id' => 1]);
        }
        $migration->dropField($table, 'language'); // useless field removed in 2.5.1

        $migration->displayMessage('Create useditemsexport dir');
        if (!is_dir(GLPI_PLUGIN_DOC_DIR . '/useditemsexport')) {
            mkdir(GLPI_PLUGIN_DOC_DIR . '/useditemsexport');
        }

        $migration->displayMessage('Copy default logo from GLPi core');
        if (!file_exists(GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo.png')) {
            copy(
                GLPI_ROOT . '/public/pics/logos/logo-GLPI-250-black.png',
                GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo.png',
            );
        }

        return true;
    }

    /**
     * Uninstall previously installed tables of the plugin
     *
     * @return boolean True if success
     */
    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $table = getTableForItemType(__CLASS__);

        $query = 'DROP TABLE IF EXISTS  `' . $table . '`';
        $DB->doQuery($query);

        if (is_dir(GLPI_PLUGIN_DOC_DIR . '/useditemsexport')) {
            Toolbox::deleteDir(GLPI_PLUGIN_DOC_DIR . '/useditemsexport');
        }

        return true;
    }


    public static function getIcon()
    {
        // Generic icon that is not visible, but still takes up space to allow proper alignment in lists
        return "ti ti-clipboard-list";
    }

}
