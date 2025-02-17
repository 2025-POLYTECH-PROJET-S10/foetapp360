<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Code executed after the plugin's database scheme has been installed.
 *
 * @package     mod_hippotrack
 * @category    upgrade
 * @copyright   2025 Lionel Di Marco
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_hippotrack\image_manager;

/**
 * Function executed when the plugin is installed.
 */
function xmldb_hippotrack_install() {
    global $DB, $CFG;
    
    // Initialize image managers for both views
    $image_manager_anterieure = new image_manager(
        'vue_anterieure'
    );
    
    $image_manager_laterale = new image_manager(
        'vue_laterale'
    );

    // CSV file path
    $csv_file = $CFG->dirroot . '/mod/hippotrack/datasets.csv';

    if (!file_exists($csv_file)) {
        debugging('⚠️ Fichier CSV introuvable : ' . $csv_file, DEBUG_DEVELOPER);
        return true;
    }

    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        debugging('⚠️ Impossible d’ouvrir le fichier CSV.', DEBUG_DEVELOPER);
        return true;
    }

    $line_number = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $line_number++;
        if ($line_number == 1) continue;

        if (count($data) < 5) {
            debugging("⚠️ Ligne $line_number mal formatée dans le CSV.", DEBUG_DEVELOPER);
            continue;
        }

        // Data parsing
        $name = trim($data[0]);
        $sigle = trim($data[1]);
        $partogramme = trim($data[2]);
        $nom_vue_anterieure = trim($data[3]);
        $nom_vue_laterale = trim($data[4]);

        // Handle rotation/inclination
        if (strpos($partogramme, ';') !== false) {
            list($rotation, $inclinaison) = explode(';', $partogramme);
        } else {
            $rotation = 0;
            $inclinaison = 0;
        }

        // Create database record
        $record = new stdClass();
        $record->name = $name;
        $record->sigle = $sigle;
        $record->rotation = (int) $rotation;
        $record->inclinaison = (int) $inclinaison;
        $record->vue_anterieure = $nom_vue_anterieure;
        $record->vue_laterale = $nom_vue_laterale;


        // 📌 Insérer en base
        $new_id = $DB->insert_record('hippotrack_datasets', $record);

        // Upload anterior view image
        if (!empty($nom_vue_anterieure)) {
            $image_manager_anterieure->upload_pix_image($new_id,$nom_vue_anterieure);
        }

        // Upload lateral view image
        if (!empty($nom_vue_laterale)) {
            $image_manager_laterale->upload_pix_image($new_id,$nom_vue_laterale);
        }
    }

    fclose($handle);
    debugging('✅ Importation des données et images terminée avec succès.', DEBUG_DEVELOPER);
    return true;
}
