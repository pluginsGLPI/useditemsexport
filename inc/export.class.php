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
use Safe\DateTime;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\ob_get_clean;
use function Safe\ob_start;

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

        // Get default one
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
     * Generate PDF for user and add entry into DB
     *
     * @param $users_id user ID
     *
     * @return boolean
    **/
public static function generatePDF($users_id)
{
    $num        = self::getNextNum();
    $refnumber  = self::getNextRefnumber();

    if (!isset($_SESSION['plugins']['useditemsexport']['config'])) {
        PluginUseditemsexportConfig::loadInSession();
    }

    $useditemsexport_config = $_SESSION['plugins']['useditemsexport']['config'];

    // Entity address
    $entity = new Entity();
    $entity->getFromDB($_SESSION['glpiactive_entity']);
    $entity_address = '<h3>' . $entity->fields['name'] . '</h3><br />' .
                      $entity->fields['address'] . '<br />' .
                      $entity->fields['postcode'] . ' - ' . $entity->fields['town'] . '<br />' .
                      $entity->fields['country'] . '<br />';
    if (!empty($entity->fields['email'])) {
        $entity_address .= __('Email') . ' : ' . $entity->fields['email'] . '<br />';
    }
    if (!empty($entity->fields['phonenumber'])) {
        $entity_address .= __('Phone') . ' : ' . $entity->fields['phonenumber'] . '<br />';
    }

    // User and Author
    $User   = new User();
    $User->getFromDB($users_id);
    $Author = new User();
    $Author->getFromDB(Session::getLoginUserID());

    // Logo
    $logo_base64 = base64_encode(file_get_contents(GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo.png'));

    // HTML content
    ob_start();
    ?>
    <style type="text/css">
        table {
            border: 1px solid #000000;
            width: 100%;
            font-size: 10pt;
            font-family: helvetica, arial, sans-serif;
        }
    </style>
    <page backtop="70mm" backleft="10mm" backright="10mm" backbottom="30mm">
    <page_header>
        <table>
            <tr>
                <td style="height: 60mm; width: 40%; text-align: center">
                    <img src="data:image/png;base64,<?php echo $logo_base64; ?>" />
                </td>
                <td style="width: 60%; text-align: center;">
                    <?php echo $entity_address; ?>
                </td>
            </tr>
        </table>
    </page_header>

    <table>
        <tr>
            <td style="border: 1px solid #000000; text-align: center; width: 100%; font-size: 15pt; height: 8mm;">
                <?php echo __s('Asset export ref : ', 'useditemsexport') . $refnumber; ?>
            </td>
        </tr>
    </table>
    <br><br><br><br><br>

    <table>
        <tr>
            <th style="width: 20%;"><?php echo __('Serial number'); ?></th>
            <th style="width: 20%;"><?php echo __('Inventory number'); ?></th>
            <th style="width: 30%;"><?php echo __('Name'); ?></th>
            <th style="width: 30%;"><?php echo __('Type'); ?></th>
        </tr>
        <?php
        $allUsedItemsForUser = self::getAllUsedItemsForUser($users_id);

        foreach ($allUsedItemsForUser as $itemtype => $used_items) {
            $item = getItemForItemtype($itemtype);
            $typeName = is_object($item) && method_exists($item, 'getTypeName')
                ? $item->getTypeName(1)
                : $itemtype;

            foreach ($used_items as $item_datas) {
                echo '<tr>';
                echo '<td>' . ($item_datas['serial'] ?? '') . '</td>';
                echo '<td>' . ($item_datas['otherserial'] ?? '') . '</td>';
                echo '<td>' . ($item_datas['name'] ?? '') . '</td>';
                echo '<td>' . $typeName . '</td>';
                echo '</tr>';
            }
        }
        ?>
    </table>

    <br><br><br><br><br>
    <table style="border-collapse: collapse;">
        <tr>
            <td style="width: 50%; border-bottom: 1px solid #000000;">
                <strong><?php echo $Author->getFriendlyName(); ?> :</strong>
            </td>
            <td style="width: 50%; border-bottom: 1px solid #000000">
                <strong><?php echo $User->getFriendlyName(); ?> :</strong>
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #000000; width: 50%; vertical-align: top">
                <?php echo __s('Signature', 'useditemsexport'); ?> : <br><br><br><br><br>
            </td>
            <td style="border: 1px solid #000000; width: 50%; vertical-align: top;">
                <?php echo __s('Signature', 'useditemsexport'); ?> : <br><br><br><br><br>
            </td>
        </tr>
    </table>

    <page_footer>
        <div style="width: 100%; text-align: center; font-size: 8pt">
            - <?php echo $useditemsexport_config['footer_text']; ?> -
        </div>
    </page_footer>
    </page>
    <?php
    $content = ob_get_clean();

    // PDF erzeugen
    $pdf = new GLPIPDF([
        'orientation' => $useditemsexport_config['orientation'],
        'format'      => $useditemsexport_config['format'],
    ]);
    $pdf->WriteHTML($content);
    $contentPDF = $pdf->Output('', 'S');

    // Temporäre Datei speichern
    $tmpfile = GLPI_TMP_DIR . '/' . $refnumber . '.pdf';
    file_put_contents($tmpfile, $contentPDF);

    // Dokument in GLPI anlegen
    $doc = new Document();
    $input = [
        'name'                   => $refnumber . '.pdf',
        'entities_id'            => $_SESSION['glpiactive_entity'],
        'is_recursive'           => 0,
        'documentcategories_id'  => 0,
        '_filename'              => [
            'name'     => $refnumber . '.pdf',
            'tmp_name' => $tmpfile,
            'type'     => 'application/pdf',
            'error'    => 0,
            'size'     => filesize($tmpfile),
        ],
    ];
    $documents_id = $doc->add($input);

    // Export-Log speichern
    $export = new self();
    $input = [
        'users_id'      => $users_id,
        'date_mod'      => date('Y-m-d H:i:s'),
        'num'           => $num,
        'refnumber'     => $refnumber,
        'authors_id'    => Session::getLoginUserID(),
        'documents_id'  => $documents_id,
    ];

    return (bool) $export->add($input);
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
            'SELECT' => [new \Glpi\DBAL\QueryExpression('MAX(' . $DB::quoteName('num') . ') AS ' . $DB::quoteName('num'))],
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
    global $DB, $CFG_GLPI;

    $items = [];

    // 1. Standard-Assettypen aus GLPI-Konfiguration
    $types = isset($CFG_GLPI['linkuser_types']) ? $CFG_GLPI['linkuser_types'] : [];
    $types = array_unique($types);

    foreach ($types as $itemtype) {
        $item = getItemForItemtype($itemtype);
        if (!$item || !method_exists($item, 'canView')) {
            Toolbox::logDebug("Skipped unrecognized itemtype: $itemtype");
            continue;
        }

        $itemtable = getTableForItemType($itemtype);

        if (!$DB->fieldExists($itemtable, 'users_id')) {
            Toolbox::logDebug("Skipped itemtype without users_id field: $itemtype");
            continue;
        }

        if ($item->canView()) {
            $criteria = [
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

            foreach ($result as $data) {
                $items[$itemtype][] = [
                    'name'         => $data['name']         ?? '',
                    'serial'       => $data['serial']       ?? '',
                    'otherserial'  => $data['otherserial']  ?? '',
                ];
            }
        }
    }

    // 2. Verbrauchsmaterialien (Consumables)
    $consumables = $DB->request([
        'SELECT' => ['name', 'otherserial'],
        'FROM'   => ConsumableItem::getTable(),
        'WHERE'  => [
            'id' => new \Glpi\DBAL\QuerySubQuery([
                'SELECT' => 'consumableitems_id',
                'FROM'   => Consumable::getTable(),
                'WHERE'  => [
                    'itemtype' => User::class,
                    'items_id' => $ID,
                ],
            ]),
        ],
    ]);

    foreach ($consumables as $data) {
        $items['ConsumableItem'][] = [
            'name'         => $data['name']         ?? '',
            'serial'       => '',
            'otherserial'  => $data['otherserial']  ?? '',
        ];
    }

    // 3. Custom Assets aus GLPI 11 Asset Definitions
    $definitions = $DB->request([
        'SELECT' => ['id', 'system_name'],
        'FROM'   => 'glpi_assets_assetdefinitions',
    ]);

    foreach ($definitions as $def) {
        $assets = $DB->request([
            'SELECT' => ['name', 'serial', 'otherserial'],
            'FROM'   => 'glpi_assets_assets',
            'WHERE'  => [
                'assets_assetdefinitions_id' => $def['id'],
                'users_id'                   => $ID,
            ],
        ]);

        foreach ($assets as $data) {
            $items[$def['system_name']][] = [
                'name'         => $data['system_name']         ?? '',
                'serial'       => $data['serial']       ?? '',
                'otherserial'  => $data['otherserial']  ?? '',
            ];
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
        // Clean Document GLPi
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
