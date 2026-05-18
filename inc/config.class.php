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

        // Build current logo URL for preview
        $logo_filename = $this->fields['logo_filename'] ?? 'logo.png';
        $logo_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/' . $logo_filename;
        $logo_exists = file_exists($logo_path);

        TemplateRenderer::getInstance()->display(
            '@useditemsexport/config.html.twig',
            [
                'action'       => Toolbox::getItemTypeFormURL(self::class),
                'item'         => $this,
                'logo_exists'  => $logo_exists,
                'logo_filename' => $logo_filename,
            ],
        );

        return true;
    }

    /**
     * Handle logo file upload.
     * Call this from the front controller after $_FILES is available.
     *
     * @return void
     */
    public static function handleLogoUpload()
    {
        if (
            !isset($_FILES['logo_file'])
            || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK
            || $_FILES['logo_file']['size'] === 0
        ) {
            return;
        }

        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['logo_file']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_types, true)) {
            Session::addMessageAfterRedirect(
                __s('Invalid logo file type. Allowed: PNG, JPG, GIF, SVG.', 'useditemsexport'),
                false,
                ERROR,
            );
            return;
        }

        // Determine extension from mime
        $ext_map = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
        ];
        $ext = $ext_map[$mime] ?? 'png';
        $target_filename = 'logo.' . $ext;
        $target_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/' . $target_filename;

        // Remove old logo files
        foreach (glob(GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo.*') as $old) {
            @unlink($old);
        }

        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $target_path)) {
            // Update config with new filename
            $config = new self();
            $config->update([
                'id'            => 1,
                'logo_filename' => $target_filename,
            ]);
            Session::addMessageAfterRedirect(
                __s('Logo uploaded successfully.', 'useditemsexport'),
                true,
            );
        } else {
            Session::addMessageAfterRedirect(
                __s('Failed to save logo file.', 'useditemsexport'),
                false,
                ERROR,
            );
        }
    }

    /**
     * Parse custom_columns field from stored text format.
     * Each line: field_name|Column Label
     *
     * @param string $raw Raw text from DB
     * @return array Array of ['field' => string, 'label' => string]
     */
    public static function parseCustomColumns($raw)
    {
        $columns = [];
        if (empty($raw)) {
            return $columns;
        }

        $lines = explode("\n", trim($raw));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $columns[] = [
                    'field' => trim($parts[0]),
                    'label' => trim($parts[1]),
                ];
            }
        }

        return $columns;
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

        $table = getTableForItemType(self::class);

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `footer_text` VARCHAR(255) DEFAULT '',
                     `is_active` TINYINT NOT NULL DEFAULT 1,
                     `orientation` VARCHAR(1) NOT NULL DEFAULT 'P',
                     `format` VARCHAR(2) NOT NULL DEFAULT 'A4',
                     `logo_filename` VARCHAR(255) NOT NULL DEFAULT 'logo.png',
                     `logo_width` INT NOT NULL DEFAULT 0,
                     `show_logo` TINYINT NOT NULL DEFAULT 1,
                     `show_entity_address` TINYINT NOT NULL DEFAULT 1,
                     `show_signature` TINYINT NOT NULL DEFAULT 1,
                     `show_serial` TINYINT NOT NULL DEFAULT 1,
                     `show_otherserial` TINYINT NOT NULL DEFAULT 1,
                     `show_name` TINYINT NOT NULL DEFAULT 1,
                     `show_type` TINYINT NOT NULL DEFAULT 1,
                     `document_title` VARCHAR(255) NOT NULL DEFAULT 'Asset export ref',
                     `label_serial` VARCHAR(255) NOT NULL DEFAULT '',
                     `label_otherserial` VARCHAR(255) NOT NULL DEFAULT '',
                     `label_name` VARCHAR(255) NOT NULL DEFAULT '',
                     `label_type` VARCHAR(255) NOT NULL DEFAULT '',
                     `label_signature` VARCHAR(255) NOT NULL DEFAULT '',
                     `header_text` TEXT DEFAULT NULL,
                     `disclaimer_text` TEXT DEFAULT NULL,
                     `custom_columns` TEXT DEFAULT NULL,
                     `font_family` VARCHAR(100) NOT NULL DEFAULT 'dejavusans',
               PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);

            $DB->insert($table, ['id' => 1]);
        }
        $migration->dropField($table, 'language'); // useless field removed in 2.5.1

        // --- Migration: add new configurable report fields ---
        $migration->addField($table, 'logo_filename', 'string', ['value' => 'logo.png']);
        $migration->addField($table, 'logo_width', 'integer', ['value' => 0]);
        $migration->addField($table, 'show_logo', 'bool', ['value' => 1]);
        $migration->addField($table, 'show_entity_address', 'bool', ['value' => 1]);
        $migration->addField($table, 'show_signature', 'bool', ['value' => 1]);
        $migration->addField($table, 'show_serial', 'bool', ['value' => 1]);
        $migration->addField($table, 'show_otherserial', 'bool', ['value' => 1]);
        $migration->addField($table, 'show_name', 'bool', ['value' => 1]);
        $migration->addField($table, 'show_type', 'bool', ['value' => 1]);
        $migration->addField($table, 'document_title', 'string', ['value' => 'Asset export ref']);
        $migration->addField($table, 'label_serial', 'string', ['value' => '']);
        $migration->addField($table, 'label_otherserial', 'string', ['value' => '']);
        $migration->addField($table, 'label_name', 'string', ['value' => '']);
        $migration->addField($table, 'label_type', 'string', ['value' => '']);
        $migration->addField($table, 'label_signature', 'string', ['value' => '']);
        $migration->addField($table, 'header_text', 'text', ['value' => null]);
        $migration->addField($table, 'disclaimer_text', 'text', ['value' => null]);
        $migration->addField($table, 'custom_columns', 'text', ['value' => null]);
        $migration->addField($table, 'font_family', 'string', ['value' => 'dejavusans']);
        $migration->migrationOneTable($table);

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

        $table = getTableForItemType(self::class);

        $query = 'DROP TABLE IF EXISTS  `' . $table . '`';
        $DB->doQuery($query);

        if (is_dir(GLPI_PLUGIN_DOC_DIR . '/useditemsexport')) {
            Toolbox::deleteDir(GLPI_PLUGIN_DOC_DIR . '/useditemsexport');
        }

        return true;
    }


    public static function getIcon()
    {
        return "ti ti-clipboard-list";
    }
}
