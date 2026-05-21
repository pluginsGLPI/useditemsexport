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
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Asset\AssetDefinitionManager;
use Safe\DateTime;

use function Safe\file_get_contents;
use function Safe\file_put_contents;

class PluginUseditemsexportExport extends CommonDBTM
{
    public static $rightname = 'plugin_useditemsexport_export';

    public static function getTypeName($nb = 0)
    {
        return __s('Used items export', 'useditemsexport');
    }

    /**
     * @see CommonGLPI::getTabNameForItem()
    **/
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof User) {
            $nb = 0;
            if ($_SESSION['glpishow_count_on_tabs']) {
                $nb = self::countForItem($item);
            }
            if (Session::haveRightsOr('plugin_useditemsexport_export', [READ, CREATE, PURGE])) {
                return self::createTabEntry(self::getTypeName(), $nb, $item::getType(), PluginUseditemsexportConfig::getIcon());
            }
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof User && Session::haveRightsOr('plugin_useditemsexport_export', [READ, CREATE, PURGE])) {
            $PluginUseditemsexportExport = new self();
            $PluginUseditemsexportExport->showForUser($item);
        }

        return true;
    }

    /**
     * @param $item    CommonDBTM object
    **/
    public static function countForItem(CommonDBTM $item)
    {
        return countElementsInTable(getTableForItemType(self::class), ['users_id' => $item->getID()]);
    }

    /**
     * Get all generated export for user.
     *
     * @param $users_id user ID
     *
     * @return array of exports
    **/
    public static function getAllForUser($users_id)
    {
        /** @var DBmysql $DB */
        global $DB;

        $exports = [];

        $it = $DB->request([
            'FROM'  => getTableForItemType(self::class),
            'WHERE' => ['users_id' => $users_id],
        ]);
        foreach ($it as $data) {
            $exports[$data['id']] = $data;
        }

        return $exports;
    }

    /**
     * @param CommonDBTM $item
     * @param array $options
     * @return void
     */
    public function showForUser($item, $options = [])
    {
        /**
         * @var array $CFG_GLPI
         */
        global $CFG_GLPI;

        $users_id = $item->getField('id');

        $exports = self::getAllForUser($users_id);
        $rand    = mt_rand();

        $canpurge  = self::canPurge();
        $cancreate = self::canCreate();

        if ($cancreate) {
            TemplateRenderer::getInstance()->display(
                '@useditemsexport/export.html.twig',
                [
                    'action'  => $CFG_GLPI['root_doc'] . '/plugins/useditemsexport/front/export.form.php',
                    'users_id'    => $users_id,
                ],
            );
        }

        $entries = [];
        foreach ($exports as $row) {

            $user = new User();
            $user->getFromDB($row['authors_id']);

            $doc = new Document();
            $doc->getFromDB($row['documents_id']);

            $entries[] = [
                'itemtype' => self::class,
                'id' => $row['id'],
                'ref' => $row['refnumber'],
                'date_mod' => Html::convDateTime($row['date_mod']),
                'users_id' => $user->getLink(),
                'doc' =>  $doc->getDownloadLink(),
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'is_tab' => true,
            'nofilter' => true,
            'columns' => [
                'ref' => __s('Reference number of export', 'useditemsexport'),
                'date_mod' => __s('Date of export', 'useditemsexport'),
                'users_id' => __s('Author of export', 'useditemsexport'),
                'doc' => __s('Export document', 'useditemsexport'),
            ],
            'formatters' => [
                'ref' => 'raw_html',
                'date_mod' => 'raw_html',
                'users_id' => 'raw_html',
                'doc' => 'raw_html',
            ],
            'entries' => $entries,
            'total_number' => count($entries),
            'filtered_number' => count($entries),
            'showmassiveactions' => $cancreate || $canpurge,
            'massiveactionparams' => [
                'num_displayed' => min($_SESSION['glpilist_limit'], count($entries)),
                'container'     => 'mass' . static::class . $rand,
            ],
        ]);
    }

    /**
     * Resolve a foreign key field value to a display name.
     *
     * @param string $field    The field name (e.g. 'locations_id')
     * @param mixed  $value    The raw value from the DB
     * @return string  The resolved display name, or the raw value as string
     */
    private static function resolveFieldValue($field, $value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Map of common FK fields to their GLPI itemtypes
        $fk_map = [
            'locations_id'       => 'Location',
            'states_id'          => 'State',
            'manufacturers_id'   => 'Manufacturer',
            'users_id_tech'      => 'User',
            'groups_id'          => 'Group',
            'groups_id_tech'     => 'Group',
        ];

        // Model fields (computermodels_id, monitormodels_id, etc.)
        if (preg_match('/^(\w+)models_id$/', $field)) {
            $model_class = str_replace('models_id', '', $field);
            $model_class = ucfirst($model_class) . 'Model';
            if (class_exists($model_class)) {
                $item = new $model_class();
                if ($item->getFromDB((int)$value)) {
                    return $item->fields['name'] ?? (string)$value;
                }
            }
            return (string)$value;
        }

        // Type fields (computertypes_id, monitortypes_id, etc.)
        if (preg_match('/^(\w+)types_id$/', $field)) {
            $type_class = str_replace('types_id', '', $field);
            $type_class = ucfirst($type_class) . 'Type';
            if (class_exists($type_class)) {
                $item = new $type_class();
                if ($item->getFromDB((int)$value)) {
                    return $item->fields['name'] ?? (string)$value;
                }
            }
            return (string)$value;
        }

        if (isset($fk_map[$field])) {
            $classname = $fk_map[$field];
            if (class_exists($classname)) {
                $item = new $classname();
                if ($item->getFromDB((int)$value)) {
                    return $item->fields['name'] ?? $item->getFriendlyName();
                }
            }
            return (string)$value;
        }

        return (string)$value;
    }

    /**
     * Generate PDF for user and add entry into DB
     *
     * @param $users_id user ID
     *
     * @return boolean
    **/
    public static function generatePDF($users_id)
    {
        $num       = self::getNextNum();
        $refnumber = self::getNextRefnumber();

        if (!isset($_SESSION['plugins']['useditemsexport']['config'])) {
            PluginUseditemsexportConfig::loadInSession();
        }
        $useditemsexport_config = $_SESSION['plugins']['useditemsexport']['config'];

        // ── Get entity address from the USER's entity (not active entity) ──
        $User = new User();
        $User->getFromDB($users_id);

        $user_entity_id = $User->fields['entities_id'] ?? $_SESSION['glpiactive_entity'];

        $entity = new Entity();
        $entity->getFromDB($user_entity_id);

        $entity_address = '<h3>' . ($entity->fields['name'] ?? '') . '</h3><br />';
        $entity_address .= ($entity->fields['address'] ?? '') . '<br />';
        $entity_address .= ($entity->fields['postcode'] ?? '') . ' - ' . ($entity->fields['town'] ?? '') . '<br />';
        $entity_address .= ($entity->fields['country'] ?? '') . '<br />';
        if (!empty($entity->fields['email'])) {
            $entity_address .= $entity->fields['email'] . '<br />';
        }
        if (!empty($entity->fields['phonenumber'])) {
            $entity_address .= $entity->fields['phonenumber'] . '<br />';
        }

        $Author = new User();
        $Author->getFromDB(Session::getLoginUserID());

        // ── Parse custom columns ──
        $custom_columns = PluginUseditemsexportConfig::parseCustomColumns(
            $useditemsexport_config['custom_columns'] ?? ''
        );

        // ── Build items array ──
        $items_for_twig = [];
        $allUsedItemsForUser = self::getAllUsedItemsForUser($users_id);
        $total_count = 0;

        foreach ($allUsedItemsForUser as $itemtype => $used_items) {
            $item_obj = getItemForItemtype($itemtype);
            foreach ($used_items as $item_datas) {
                $total_count++;
                $twig_item = [
                    'serial'      => $item_datas['serial'] ?? '',
                    'otherserial' => $item_datas['otherserial'] ?? '',
                    'name'        => $item_datas['name'] ?? '',
                    'type'        => $item_obj->getTypeName(1),
                ];

                // Add custom column values with FK resolution
                foreach ($custom_columns as $col) {
                    $field = $col['field'];
                    $raw_value = $item_datas[$field] ?? '';
                    $twig_item['custom_' . $field] = self::resolveFieldValue($field, $raw_value);
                }

                $items_for_twig[] = $twig_item;
            }
        }

        // ── Count active columns for width calculation ──
        $active_cols = 0;
        if ($useditemsexport_config['show_serial'] ?? 1) {
            $active_cols++;
        }
        if ($useditemsexport_config['show_otherserial'] ?? 1) {
            $active_cols++;
        }
        if ($useditemsexport_config['show_name'] ?? 1) {
            $active_cols++;
        }
        if ($useditemsexport_config['show_type'] ?? 1) {
            $active_cols++;
        }
        $active_cols += count($custom_columns);
        $col_width = $active_cols > 0 ? floor(100 / $active_cols) : 25;

        // ── Resolve label overrides (empty = use GLPI default) ──
        $labels = [
            'serial'      => !empty($useditemsexport_config['label_serial'])
                                ? $useditemsexport_config['label_serial']
                                : __s('Serial number'),
            'otherserial' => !empty($useditemsexport_config['label_otherserial'])
                                ? $useditemsexport_config['label_otherserial']
                                : __s('Inventory number'),
            'name'        => !empty($useditemsexport_config['label_name'])
                                ? $useditemsexport_config['label_name']
                                : __s('Name'),
            'type'        => !empty($useditemsexport_config['label_type'])
                                ? $useditemsexport_config['label_type']
                                : __s('Type'),
            'signature'   => !empty($useditemsexport_config['label_signature'])
                                ? $useditemsexport_config['label_signature']
                                : __s('Signature', 'useditemsexport'),
        ];

        // ── Document title (e.g. "Asset export ref") ──
        $document_title = !empty($useditemsexport_config['document_title'])
                            ? $useditemsexport_config['document_title']
                            : __s('Asset export ref', 'useditemsexport');

        // ── Logo: entity-specific first, then global fallback ──
        $logo_width = (int)($useditemsexport_config['logo_width'] ?? 0);
        $entityLogo = PluginUseditemsexportEntityconfig::getEntityLogo($user_entity_id);

        if ($entityLogo !== null) {
            // Entity-specific logo
            $logo_path = $entityLogo['path'];
            $logo_mime = $entityLogo['mime'];
        } else {
            // Global logo fallback
            $logo_filename = $useditemsexport_config['logo_filename'] ?? 'logo.png';
            $logo_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/' . $logo_filename;
            if (!file_exists($logo_path)) {
                $logo_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo.png';
            }
            $logo_ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
            $mime_map = [
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'svg'  => 'image/svg+xml',
            ];
            $logo_mime = $mime_map[$logo_ext] ?? 'image/png';
        }

        $logo_base64 = base64_encode(file_get_contents($logo_path));

        // ── Font family ──
        $font_family = $useditemsexport_config['font_family'] ?? 'dejavusans';

        $content = TemplateRenderer::getInstance()->render(
            '@useditemsexport/export_template.html.twig',
            [
                'logo_base64'        => $logo_base64,
                'logo_mime'          => $logo_mime,
                'logo_width'         => $logo_width,
                'entity_address'     => $entity_address,
                'refnumber'          => $refnumber,
                'document_title'     => $document_title,
                'items'              => $items_for_twig,
                'author_name'        => $Author->getFriendlyName(),
                'user_name'          => $User->getFriendlyName(),
                'config'             => $useditemsexport_config,
                'labels'             => $labels,
                'custom_columns'     => $custom_columns,
                'col_width'          => $col_width,
                'font_family'        => $font_family,
            ],
        );

        $pdf = new PluginUseditemsexportPDF([
            'orientation' => $useditemsexport_config['orientation'],
            'format'      => $useditemsexport_config['format'],
        ]);
        $pdf->setTotalCount($total_count);
        $pdf->WriteHTML($content);
        $contentPDF = $pdf->Output('', 'S');

        file_put_contents(GLPI_UPLOAD_DIR . '/' . $refnumber . '.pdf', $contentPDF);
        $documents_id = self::createDocument($refnumber);

        $export = new self();
        $export->add([
            'users_id'     => $users_id,
            'date_mod'     => date('Y-m-d H:i:s'),
            'num'          => $num,
            'refnumber'    => $refnumber,
            'authors_id'   => Session::getLoginUserID(),
            'documents_id' => $documents_id,
        ]);

        return true;
    }

    /**
     * Store Document into GLPi DB
     * @param string $refnumber
     * @return integer id of Document
     */
    public static function createDocument($refnumber)
    {
        $doc = new Document();

        $input                          = [];
        $input['entities_id']           = $_SESSION['glpiactive_entity'];
        $input['name']                  = __s('Used-Items-Export', 'useditemsexport') . '-' . $refnumber;
        $input['upload_file']           = $refnumber . '.pdf';
        $input['documentcategories_id'] = 0;
        $input['mime']                  = 'application/pdf';
        $input['date_mod']              = date('Y-m-d H:i:s');
        $input['users_id']              = Session::getLoginUserID();

        $doc->check(-1, CREATE, $input);
        $newdocid = $doc->add($input);

        return $newdocid;
    }

    /**
     * Get next num
     * @return integer
     */
    public static function getNextNum()
    {
        /** @var DBmysql $DB */
        global $DB;

        $result = $DB->request([
            'SELECT' => [new QueryExpression('MAX(' . $DB::quoteName('num') . ') AS ' . $DB::quoteName('num'))],
            'FROM'   => self::getTable(),
        ]);
        $nextNum = count($result) > 0 ? $result->current()['num'] : false;
        if (!$nextNum) {
            return 1;
        } else {
            $nextNum++;

            return $nextNum;
        }
    }

    /**
     * Compute next refnumber
     * @return string
     */
    public static function getNextRefnumber()
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($nextNum = self::getNextNum()) {
            $nextRefnumber = str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
            $date          = new DateTime();

            return $nextRefnumber . '-' . $date->format('Y');
        } else {
            return '';
        }
    }

    /**
     * Get all used items for user
     * @param integer $ID ID of user
     * @return array
     */
    public static function getAllUsedItemsForUser($ID)
    {
        /**
         * @var DBmysql $DB
         * @var array $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        $items = [];

        foreach ($CFG_GLPI['linkuser_types'] as $itemtype) {
            if (!($item = getItemForItemtype($itemtype))) {
                continue;
            }
            if ($item->canView()) {
                $itemtable = getTableForItemType($itemtype);
                $criteria  = [
                    'FROM'  => $itemtable,
                    'WHERE' => ['users_id' => $ID],
                ];

                if ($item->maybeTemplate()) {
                    $criteria['WHERE']['is_template'] = '0';
                }
                if ($item->maybeDeleted()) {
                    $criteria['WHERE']['is_deleted'] = '0';
                }
                $result = $DB->request($criteria);

                $type_name = $item->getTypeName();

                if (count($result) > 0) {
                    foreach ($result as $data) {
                        $items[$itemtype][] = $data;
                    }
                }
            }
        }

        // Consumables
        $consumables = $DB->request(
            [
                'SELECT' => ['name', 'otherserial'],
                'FROM'   => ConsumableItem::getTable(),
                'WHERE'  => [
                    'id' => new QuerySubQuery(
                        [
                            'SELECT' => 'consumableitems_id',
                            'FROM'   => Consumable::getTable(),
                            'WHERE'  => [
                                'itemtype' => User::class,
                                'items_id' => $ID,
                            ],
                        ],
                    ),
                ],
            ],
        );

        foreach ($consumables as $data) {
            $items['ConsumableItem'][] = $data;
        }

        // Custom Assets
        $definitions = AssetDefinitionManager::getInstance()->getDefinitions();
        foreach ($definitions as $definition) {
            $itemtype = $definition->getAssetClassName();
            $item = getItemForItemtype($itemtype);
            if ($item && $item->canView()) {
                $criteria  = [
                    'FROM'  => 'glpi_assets_assets',
                    'WHERE' => ['users_id' => $ID, 'assets_assetdefinitions_id' => $definition->getID()],
                ];
                $result = $DB->request($criteria);

                foreach ($result as $data) {
                    $items[$itemtype][] = $data;
                }
            }
        }

        return $items;
    }

    /**
     * Clean GLPi DB on export purge
     *
     * @return void
     */
    public function cleanDBonPurge()
    {
        $doc = new Document();
        $doc->getFromDB($this->fields['documents_id']);
        $doc->delete(['id' => $this->fields['documents_id']], true);
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
                  `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                  `users_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                  `date_mod` TIMESTAMP NULL DEFAULT NULL,
                  `num` SMALLINT NOT NULL DEFAULT 0,
                  `refnumber` VARCHAR(9) NOT NULL DEFAULT '0000-0000',
                  `authors_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                  `documents_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
               PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
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

        return true;
    }
}
