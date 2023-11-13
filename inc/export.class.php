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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginUseditemsexportExport extends CommonDBTM
{
    public static $rightname = 'plugin_useditemsexport_export';

    public static function getTypeName($nb = 0)
    {

        return __('Used items export', 'useditemsexport');
    }

   /**
    * @see CommonGLPI::getTabNameForItem()
   **/
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if ($item->getType() == 'User') {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(), self::countForItem($item));
            }
            return self::getTypeName();
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        if ($item->getType() == 'User') {
            if (Session::haveRightsOr('plugin_useditemsexport_export', [READ, CREATE, PURGE])) {
                $PluginUseditemsexportExport = new self();
                $PluginUseditemsexportExport->showForUser($item);
            } else {
                echo "<div align='center'><br><br><img src=\"" . $CFG_GLPI["root_doc"] .
                     "/pics/warning.png\" alt=\"warning\"><br><br>";
                echo "<b>" . __("Access denied") . "</b></div>";
            }
        }

        return true;
    }

   /**
    * @param $item    CommonDBTM object
   **/
    public static function countForItem(CommonDBTM $item)
    {
        return countElementsInTable(getTableForItemType(__CLASS__), ['users_id' => $item->getID()]);
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
            'FROM' => getTableForItemType(__CLASS__),
            'WHERE' => ['users_id' => $users_id]
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
        $users_id = $item->getField('id');

        $exports = self::getAllForUser($users_id);

        $canpurge = self::canPurge();
        $cancreate = self::canCreate();

        if ($cancreate) {
            $rand = mt_rand();

            echo "<form method='post' name='useditemsexport_form$rand' id='useditemsexport_form$rand'
                  action=\"" . Plugin::getWebDir('useditemsexport') . "/front/export.form.php\">";

            echo "<table class='tab_cadre_fixehov'>";
            echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Generate new export', 'useditemsexport');
               echo "&nbsp;&nbsp<input type='submit' name='generate' value=\"" . __('Create') . "\" class='submit'>";
               echo "<input type='hidden' name='users_id' value='$users_id'>";
            echo "</th></tr>";
            echo "</table>";

            Html::closeForm();
        }

        if ($canpurge && count($exports) > 0) {
            $rand = mt_rand();
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['item' => $item, 'container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr><th colspan='" . ($canpurge ? 5 : 4) . "'>"
                     . __('Used items export generated', 'useditemsexport') . "</th></tr><tr>";

        if (count($exports) == 0) {
            echo "<tr class='tab_bg_1'>";
            echo "<td class='center' colspan='" . ($canpurge ? 5 : 4) . "'>"
                     . __('No item to display') . "</td>";
            echo "</tr>";
        } else {
            if ($canpurge) {
                echo "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand) . "</th>";
            }
            echo "<th>" . __('Reference number of export', 'useditemsexport') . "</th>";
            echo "<th>" . __('Date of export', 'useditemsexport') . "</th>";
            echo "<th>" . __('Author of export', 'useditemsexport') . "</th>";
            echo "<th>" . __('Export document', 'useditemsexport') . "</th>";
            echo "</tr>";

            foreach ($exports as $data) {
                echo "<tr class='tab_bg_1'>";

                if ($canpurge) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
                    echo "</td>";
                }

                echo "<td class='center'>";
                echo $data["refnumber"];
                echo "</td>";

                echo "<td class='center'>";
                echo Html::convDateTime($data["date_mod"]);
                echo "</td>";

                $User = new User();
                $User->getFromDB($data['authors_id']);
                echo "<td class='center'>";
                echo $User->getLink();
                echo "</td>";

                $Doc = new Document();
                $Doc->getFromDB($data['documents_id']);
                echo "<td class='center'>";
                echo $Doc->getDownloadLink();
                echo "</td>";
                echo "</tr>";
            }
        }

        echo "</table>";
        if ($canpurge && count($exports) > 0) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }
    }


   /**
    * Generate PDF for user and add entry into DB
    *
    * @param $users_id user ID
    *
    * @return array of exports
   **/
    public static function generatePDF($users_id)
    {

        $num       = self::getNextNum();
        $refnumber = self::getNextRefnumber();

        if (isset($_SESSION['plugins']['useditemsexport']['config'])) {
            $useditemsexport_config = $_SESSION['plugins']['useditemsexport']['config'];
        }

       // Compile address from current_entity
        $entity = new Entity();
        $entity->getFromDB($_SESSION['glpiactive_entity']);
        $entity_address = '<h3>' . $entity->fields["name"] . '</h3><br />';
        $entity_address .= $entity->fields["address"] . '<br />';
        $entity_address .= $entity->fields["postcode"] . ' - ' . $entity->fields['town'] . '<br />';
        $entity_address .= $entity->fields["country"] . '<br />';

        if (isset($entity->fields["email"])) {
            $entity_address .= __('Email') . ' : ' . $entity->fields["email"] . '<br />';
        }

        if (isset($entity->fields["phonenumber"])) {
            $entity_address .= __('Phone') . ' : ' . $entity->fields["phonenumber"] . '<br />';
        }

       // Get User information
        $User = new User();
        $User->getFromDB($users_id);

       // Get Author information
        $Author = new User();
        $Author->getFromDB(Session::getLoginUserID());

       // Logo
        $logo = GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo.png';

        ob_start();
        ?>
      <style type="text/css">
         table { border: 1px solid #000000; width: 100%; font-size: 10pt; font-family: helvetica, arial, sans-serif; }
      </style>
      <page backtop="70mm" backleft="10mm" backright="10mm" backbottom="30mm">
         <page_header>
            <table>
               <tr>
                  <td style="height: 60mm; width: 40%; text-align: center"><img src="<?php echo $logo; ?>" /></td>
                  <td style="width: 60%; text-align: center;">
                  <?php echo $entity_address; ?>
                  </td>
               </tr>
            </table>
         </page_header>

         <table>
            <tr>
               <td style="border: 1px solid #000000; text-align: center; width: 100%; font-size: 15pt; height: 8mm;">
                  <?php echo __('Asset export ref : ', 'useditemsexport') . $refnumber; ?>
               </td>
            </tr>
         </table>

         <br><br><br><br><br>
         <table>
            <tr>
              <th style="width: 25%;">
                  <?php echo __('Serial number'); ?>
               </th>
               <th style="width: 25%;">
                  <?php echo __('Inventory number'); ?>
               </th>
               <th style="width: 25%;">
                  <?php echo __('Name'); ?>
               </th>
               <th style="width: 25%;">
                  <?php echo __('Type'); ?>
               </th>
            </tr>
            <?php

            $allUsedItemsForUser = self::getAllUsedItemsForUser($users_id);

            foreach ($allUsedItemsForUser as $itemtype => $used_items) {
                $item = getItemForItemtype($itemtype);

                foreach ($used_items as $item_datas) {
                    ?>
            <tr>
               <td style="width: 25%;">
                    <?php
                    if (isset($item_datas['serial'])) {
                        echo $item_datas['serial'];
                    } ?>
               </td>
               <td style="width: 25%;">
                    <?php
                    if (isset($item_datas['otherserial'])) {
                        echo $item_datas['otherserial'];
                    } ?>
               </td>
               <td style="width: 25%;">
                    <?php echo $item_datas['name']; ?>
               </td>
               <td style="width: 25%;">
                    <?php echo $item->getTypeName(1); ?>
               </td>
            </tr>
                    <?php
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
                  <?php echo __('Signature', 'useditemsexport'); ?> : <br><br><br><br><br>
               </td>
               <td style="border: 1px solid #000000; width: 50%; vertical-align: top;">
                  <?php echo __('Signature', 'useditemsexport'); ?> : <br><br><br><br><br>
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

       // Generate PDF
        $pdf = new GLPIPDF([
            'orientation' => $useditemsexport_config['orientation'],
            'format'      => $useditemsexport_config['format'],
        ]);
        $pdf->WriteHTML($content);
        $contentPDF = $pdf->Output('', 'S');

      // Store PDF in GLPi upload dir and create document
        file_put_contents(GLPI_UPLOAD_DIR . '/' . $refnumber . '.pdf', $contentPDF);
        $documents_id = self::createDocument($refnumber);

      // Add log for last generated PDF
        $export = new self();

        $input = [];
        $input['users_id']     = $users_id;
        $input['date_mod']     = date("Y-m-d H:i:s");
        $input['num']          = $num;
        $input['refnumber']    = $refnumber;
        $input['authors_id']   = Session::getLoginUserID();
        $input['documents_id'] = $documents_id;

        if ($export->add($input)) {
            return true;
        }

        return false;
    }

   /**
    * Store Document into GLPi DB
    * @param refnumber
    * @return integer id of Document
    */
    public static function createDocument($refnumber)
    {

        $doc = new Document();

        $input                          = [];
        $input["entities_id"]           = $_SESSION['glpiactive_entity'];
        $input["name"]                  = __('Used-Items-Export', 'useditemsexport') . '-' . $refnumber;
        $input["upload_file"]           = $refnumber . '.pdf';
        $input["documentcategories_id"] = 0;
        $input["mime"]                  = "application/pdf";
        $input["date_mod"]              = date("Y-m-d H:i:s");
        $input["users_id"]              = Session::getLoginUserID();

        $doc->check(-1, CREATE, $input);
        $newdocid = $doc->add($input);

        return $newdocid;
    }

   /**
    * Get next num
    * @param nothing
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
        $nextNum = count($result) ? $result->current()['num'] : false;
        if (!$nextNum) {
            return 1;
        } else {
            $nextNum++;
            return $nextNum;
        }

        return false;
    }

   /**
    * Compute next refnumber
    * @param nothing
    * @return string
    */
    public static function getNextRefnumber()
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($nextNum = self::getNextNum()) {
            $nextRefnumber = str_pad($nextNum, 4, "0", STR_PAD_LEFT);
            $date = new DateTime();
            return $nextRefnumber . '-' . $date->format('Y');
        } else {
            return false;
        }
    }

   /**
    * Get all used items for user
    * @param ID of user
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
                $criteria = [
                    'FROM' => $itemtable,
                    'WHERE' => ['users_id' => $ID]
                ];

                if ($item->maybeTemplate()) {
                    $criteria['WHERE']['is_template'] = '0';
                }
                if ($item->maybeDeleted()) {
                    $criteria['WHERE']['is_deleted'] = '0';
                }
                $result    = $DB->request($criteria);

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
                                'items_id' => $ID
                            ],
                        ]
                    )
                ],
            ]
        );

        foreach ($consumables as $data) {
            $items['ConsumableItem'][] = $data;
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

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = getTableForItemType(__CLASS__);

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
            $DB->query($query) or die($DB->error());
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

        $query = "DROP TABLE IF EXISTS  `" . $table . "`";
        $DB->query($query) or die($DB->error());

        return true;
    }
}
