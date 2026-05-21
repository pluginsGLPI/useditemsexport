<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExport plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * Per-entity configuration: logo upload and width override.
 * Adds a "Used items export" tab to the Entity form.
 */

use Glpi\Application\View\TemplateRenderer;

class PluginUseditemsexportEntityconfig extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __s('Used items export', 'useditemsexport');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Entity && Session::haveRight('config', UPDATE)) {
            return self::createTabEntry(self::getTypeName(), 0, $item::getType(), 'ti ti-clipboard-list');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Entity) {
            $config = new self();
            $config->showForEntity($item);
        }
        return true;
    }

    /**
     * Show per-entity config form.
     *
     * @param Entity $entity
     */
    public function showForEntity(Entity $entity)
    {
        $entities_id = $entity->getID();

        // Try to load existing config for this entity
        $exists = $this->getFromDBByCrit(['entities_id' => $entities_id]);

        // Build logo preview
        $logo_filename = $this->fields['logo_filename'] ?? '';
        $logo_exists = false;
        $logo_base64 = '';
        $logo_mime = 'image/png';

        if (!empty($logo_filename)) {
            $logo_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/' . $logo_filename;
            $logo_exists = file_exists($logo_path);
            if ($logo_exists) {
                $logo_base64 = base64_encode(file_get_contents($logo_path));
                $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
                $mime_map = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
                $logo_mime = $mime_map[$ext] ?? 'image/png';
            }
        }

        TemplateRenderer::getInstance()->display(
            '@useditemsexport/entityconfig.html.twig',
            [
                'action'        => Plugin::getWebDir('useditemsexport') . '/front/entityconfig.form.php',
                'item'          => $this,
                'entities_id'   => $entities_id,
                'has_config'    => $exists,
                'logo_exists'   => $logo_exists,
                'logo_base64'   => $logo_base64,
                'logo_mime'     => $logo_mime,
                'logo_filename' => $logo_filename,
            ],
        );
    }

    /**
     * Get entity-specific logo info, or null if not set.
     *
     * @param int $entities_id
     * @return array|null ['path' => string, 'mime' => string, 'width' => int] or null
     */
    public static function getEntityLogo($entities_id)
    {
        $config = new self();
        if (!$config->getFromDBByCrit(['entities_id' => $entities_id])) {
            return null;
        }

        $logo_filename = $config->fields['logo_filename'] ?? '';
        if (empty($logo_filename)) {
            return null;
        }

        $logo_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/' . $logo_filename;
        if (!file_exists($logo_path)) {
            return null;
        }

        $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
        $mime_map = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];

        return [
            'path'  => $logo_path,
            'mime'  => $mime_map[$ext] ?? 'image/png',
            'width' => (int)($config->fields['logo_width'] ?? 0),
        ];
    }

    /**
     * Handle logo file upload for entity.
     *
     * @param int $entities_id
     */
    public static function handleLogoUpload($entities_id)
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

        $ext_map = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
        ];
        $ext = $ext_map[$mime] ?? 'png';
        $target_filename = 'logo_entity_' . (int)$entities_id . '.' . $ext;
        $target_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/' . $target_filename;

        // Remove old entity logo files
        foreach (glob(GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo_entity_' . (int)$entities_id . '.*') as $old) {
            @unlink($old);
        }

        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $target_path)) {
            // Update or create entity config
            $config = new self();
            $exists = $config->getFromDBByCrit(['entities_id' => (int)$entities_id]);

            if ($exists) {
                $config->update([
                    'id'            => $config->getID(),
                    'logo_filename' => $target_filename,
                ]);
            } else {
                $config->add([
                    'entities_id'   => (int)$entities_id,
                    'logo_filename' => $target_filename,
                    'logo_width'    => 0,
                ]);
            }

            Session::addMessageAfterRedirect(
                __s('Entity logo uploaded successfully.', 'useditemsexport'),
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
     * Install table.
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
                     `entities_id` int {$default_key_sign} NOT NULL DEFAULT 0,
                     `logo_filename` VARCHAR(255) NOT NULL DEFAULT '',
                     `logo_width` INT NOT NULL DEFAULT 0,
               PRIMARY KEY  (`id`),
               UNIQUE KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }

        return true;
    }

    /**
     * Uninstall table.
     */
    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $table = getTableForItemType(self::class);
        $query = 'DROP TABLE IF EXISTS `' . $table . '`';
        $DB->doQuery($query);

        // Clean entity logo files
        foreach (glob(GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo_entity_*') as $file) {
            @unlink($file);
        }

        return true;
    }
}
