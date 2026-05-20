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

/**
 * Custom PDF class that overrides GLPIPDF's default footer.
 * Renders footer text with proper UTF-8 font support (DejaVu Sans).
 */
class PluginUseditemsexportPDF extends GLPIPDF
{
    public function Footer()
    {
        if (!isset($_SESSION['plugins']['useditemsexport']['config'])) {
            PluginUseditemsexportConfig::loadInSession();
        }
        $config = $_SESSION['plugins']['useditemsexport']['config'];
        $footer_text = $config['footer_text'] ?? '';
        $font_family = $config['font_family'] ?? 'dejavusans';

        if (!empty($footer_text)) {
            $this->SetY(-15);
            $this->SetFont($font_family, '', 8);
            $this->Cell(0, 10, '- ' . $footer_text . ' -', 0, 0, 'C', false, '', 0, false, 'T', 'M');
        }
    }
}
