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
 * Contain the form to manage datasets.
 *
 * @package     mod_foetapp360
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class manage_datasets_form extends moodleform {


    
    public function definition() {
        $mform = $this->_form;
        
        $maxbytes = 5242880; // 5MB

        // Add text fields
        $mform->addElement('text', 'name', 'Nom');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', 'Nom requis', 'required');

        $mform->addElement('text', 'sigle', 'Sigle');
        $mform->setType('sigle', PARAM_TEXT);
        $mform->addRule('sigle', 'Sigle requis', 'required');

        $mform->addElement('text', 'rotation', 'Rotation');
        $mform->setType('rotation', PARAM_INT);
        $mform->addRule('rotation', 'Rotation requise', 'required');

        $mform->addElement('text', 'inclinaison', 'Inclinaison');
        $mform->setType('inclinaison', PARAM_INT);
        $mform->addRule('inclinaison', 'Inclinaison requise', 'required');

        $mform->addElement(
            'filepicker',
            'vue_anterieure',
            'Vue Antérieure',
            null,
            [
                'maxbytes' => $maxbytes,
                'accepted_types' => 'image',
            ]
        );
        $mform->addRule('vue_anterieure', 'Vue Antérieure requise', 'required');

        $mform->addElement(
            'filepicker',
            'vue_laterale',
            'Vue Latérale',
            null,
            [
                'maxbytes' => $maxbytes,
                'accepted_types' => 'image',
            ]
        );
        $mform->addRule('vue_laterale', 'Vue Latérale requise', 'required');

        // Add hidden field for dataset_id (used for editing)
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Add submit and cancel buttons
        $this->add_action_buttons(true, "Enregistrer");
    }


    /**
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * Server side rules do not work for uploaded files, implement serverside rules here if needed.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {
        global $CFG, $DB;
        $errors = parent::validation($data, $files);

        // Get the datas
        $name = $data["name"];
        $sigle = $data["sigle"];
        $rotation = $data["rotation"];
        $inclinaison = $data["inclinaison"];

        // Ensure that $name contains at least one letter (a-z or A-Z)
        // and allow any other character
        if (!preg_match('/^(?=.*[A-Za-z]).+$/', $name)) {
            $errors["name"] = "Le nom doit contenir au moins une lettre.";
        }

        // Similarly, ensure that $sigle contains at least one letter
        if (!preg_match('/^(?=.*[A-Za-z]).+$/', $sigle)) {
            $errors["sigle"] = "Le sigle doit contenir au moins une lettre.";
        }

        // Check if rotation and inclinaison are integers
        if (!preg_match('/^\d+$/', $rotation) || $rotation < 0 || $rotation > 360) {
            $errors["rotation"] = "La rotation doit être un entier entre 0 et 360.";
        }

        if (!preg_match('/^-?\d+(\.\d+)?$/', $inclinaison) || $inclinaison > 1 || $inclinaison < -1) {
            $errors["inclinaison"] = "L'inclinaison doit être un entier entre -1 et 1.";
        }
        return $errors;
    }
}