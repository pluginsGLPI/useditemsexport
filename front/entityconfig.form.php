<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExport plugin for GLPI
 * -------------------------------------------------------------------------
 */

include(__DIR__ . '/../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    $entities_id = (int)($_POST['entities_id'] ?? 0);

    // Handle logo removal
    if (!empty($_POST['remove_logo'])) {
        $config = new PluginUseditemsexportEntityconfig();
        if ($config->getFromDBByCrit(['entities_id' => $entities_id])) {
            // Remove logo files
            foreach (glob(GLPI_PLUGIN_DOC_DIR . '/useditemsexport/logo_entity_' . $entities_id . '.*') as $old) {
                @unlink($old);
            }
            $config->update([
                'id'            => $config->getID(),
                'logo_filename' => '',
                'logo_width'    => 0,
            ]);
            Session::addMessageAfterRedirect(
                __s('Entity logo removed.', 'useditemsexport'),
                true,
            );
        }
        Html::back();
    }

    // Handle logo upload
    PluginUseditemsexportEntityconfig::handleLogoUpload($entities_id);

    // Update logo_width
    $config = new PluginUseditemsexportEntityconfig();
    $exists = $config->getFromDBByCrit(['entities_id' => $entities_id]);

    if ($exists) {
        $config->update([
            'id'         => $config->getID(),
            'logo_width' => (int)($_POST['logo_width'] ?? 0),
        ]);
    } else if (!empty($_FILES['logo_file']['name'])) {
        // Entity config was just created by handleLogoUpload, update width
        $config->getFromDBByCrit(['entities_id' => $entities_id]);
        if ($config->getID()) {
            $config->update([
                'id'         => $config->getID(),
                'logo_width' => (int)($_POST['logo_width'] ?? 0),
            ]);
        }
    }

    Html::back();
}

Html::back();
