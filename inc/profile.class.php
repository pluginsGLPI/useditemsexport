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

class PluginUseditemsexportProfile extends CommonDBTM
{
    // Necessary rights to edit the rights of this plugin
    public static $rightname = 'profile';

    /**
     * @see CommonGLPI::getTabNameForItem()
    **/
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('interface') != 'helpdesk') {
            return self::createTabEntry(PluginUseditemsexportConfig::getTypeName(), 0, $item::getType(), PluginUseditemsexportConfig::getIcon());
        }

        return '';
    }

    /**
     * @see CommonGLPI::displayTabContentForItem()
    **/
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            $ID   = $item->getID();
            $prof = new self();
            //In case there's no right for this profile, create it
            foreach (self::getAllRights() as $right) {
                self::addDefaultProfileInfos($ID, [$right['field'] => 0]);
            }
            $prof->showForm($ID);
        }

        return true;
    }

    /**
     * Describe all possible rights for the plugin
     * @return array
    **/
    public static function getAllRights()
    {
        $rights = [
            ['itemtype'  => 'PluginUseditemsexportExport',
                'label'  => PluginUseditemsexportExport::getTypeName(),
                'field'  => 'plugin_useditemsexport_export',
                'rights' => [CREATE => __('Create'),
                    READ            => __('Read'),
                    PURGE           => ['short' => __('Purge'),
                        'long'                  => _x('button', 'Delete permanently'),
                    ],
                ],
                'default' => 21,
            ],
        ];

        return $rights;
    }

    /**
     * addDefaultProfileInfos
     * @param $profiles_id
     * @param $rights
    **/
    public static function addDefaultProfileInfos($profiles_id, $rights)
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (
                !countElementsInTable(
                    'glpi_profilerights',
                    ['profiles_id' => $profiles_id, 'name' => $right],
                )
            ) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);
                //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    public function showForm($ID, array $options = [])
    {
        echo "<div class='firstbloc'>";
        if ($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) {
            $profile = new Profile();
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        $profile = new Profile();
        $profile->getFromDB($ID);
        if ($profile->getField('interface') == 'central') {
            $rights = $this->getAllRights();
            $profile->displayRightsChoiceMatrix($rights, ['canedit' => $canedit,
                'default_class'                                     => 'tab_bg_2',
                'title'                                             => __('General'),
            ]);
        }

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $ID]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }
        echo '</div>';
        return true;
    }

    /**
     * Install all necessary profile for the plugin
     *
     * @return boolean True if success
     */
    public static function install(Migration $migration)
    {
        foreach (self::getAllRights() as $right) {
            self::addDefaultProfileInfos(
                $_SESSION['glpiactiveprofile']['id'],
                [$right['field'] => $right['default']],
            );
        }

        return true;
    }

    /**
     * Uninstall previously installed profile of the plugin
     *
     * @return boolean True if success
     */
    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        foreach (self::getAllRights() as $right) {
            $DB->delete('glpi_profilerights', ['name' => $right['field']]);

            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }

        return true;
    }
}
