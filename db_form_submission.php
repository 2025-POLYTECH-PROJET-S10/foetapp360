<?php

use mod_hippotrack\image_manager;
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/db_form.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = $USER->id;

// 📌 Retrieve Course Module and Context
$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// 📌 Access Control
require_login($course, true, $cm);
require_capability('mod/hippotrack:manage', $context);

// 📌 Page Configuration
$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/db_form_submission.php', array('id' => $cmid));
$PAGE->set_title("Ajout/Modification des données");
$PAGE->set_heading("Ajout/Modification des données");

$editing = optional_param('edit',0, PARAM_INT); // Edit an entry
$showform = optional_param('addnew', 0, PARAM_BOOL); // Add a new entry

global $DB;


// 📌 Create Form
if ($editing){
    $nexturl = new moodle_url('/mod/hippotrack/db_form_submission.php', array('cmid' => $cmid, 'edit' => $editing));
} else {
    $nexturl = new moodle_url('/mod/hippotrack/db_form_submission.php', array('cmid' => $cmid, 'addnew' => $showform));
}

$mform = new manage_datasets_form($action= $nexturl);


if ($mform->is_cancelled()) {
    // Cancel operation
    redirect(new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)), "Opération annulée.");
    // throw new moodle_exception("Operation cancelled");
} else if ($data = $mform->get_data()) {
    try {
        // Create Image Managers
        $image_manager_anterieure = new image_manager('vue_anterieure');
        $image_manager_laterale = new image_manager('vue_laterale');

        // Get the names for the images
        $vue_anterieure = $mform->get_new_filename('vue_anterieure');
        $vue_laterale = $mform->get_new_filename('vue_laterale');

        
        if ($editing) {
            // Update existing entry
            $dataset = $DB->get_record('hippotrack_datasets', array('id' => $editing), '*', MUST_EXIST);
            // Set existing data to form
            $mform->set_data($dataset);
            $dataset->name = $data->name;
            $dataset->sigle = $data->sigle;
            $dataset->rotation = $data->rotation;
            $dataset->inclinaison = $data->inclinaison;
            
            // Upload new images
            if (!empty($vue_anterieure)) {
                $image_manager_anterieure->updateImageFromForm($editing, $mform, 'vue_anterieure');
                $dataset->vue_anterieure = $mform->get_new_filename('vue_anterieure');
            }
            if (!empty($vue_laterale)) {
                $image_manager_laterale->updateImageFromForm($editing, $mform, 'vue_laterale');
                $dataset->vue_laterale = $mform->get_new_filename('vue_laterale');
            }
            
            $DB->update_record('hippotrack_datasets', $dataset);
        } else {
            // Insert new entry
            $record = new stdClass();
            $record->name = $data->name;
            $record->sigle = $data->sigle;
            $record->rotation = $data->rotation;
            $record->inclinaison = $data->inclinaison;
            $record->vue_anterieure = $mform->get_new_filename('vue_anterieure');
            $record->vue_laterale = $mform->get_new_filename('vue_laterale');
            $newid = $DB->insert_record('hippotrack_datasets', $record);
            
            // Upload new images
            if (!empty($vue_anterieure)) {
                $image_manager_anterieure->addImageFromForm($newid, $mform, 'vue_anterieure');
            }
            if (!empty($vue_laterale)) {
                $image_manager_laterale->addImageFromForm($newid, $mform,'vue_laterale');
            }

        }
        
        // Redirect with success message
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Entrée enregistrée avec succès.",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Une erreur est survenue lors de l'enregistrement de l'entrée.",
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
} else {

    echo $OUTPUT->header();

    // Display form for adding/editing
    echo html_writer::tag('h2', $editing ? "Modifier l'entrée" : "Ajouter une nouvelle entrée");

    if ($editing){
        // Load existing entry
        $dataset = $DB->get_record('hippotrack_datasets', array('id' => $editing), '*', MUST_EXIST);
        $mform->set_data($dataset);
        $mform->display();
    } else {
        // Display form for adding new entry
        $mform->display();
    }

    echo $OUTPUT->footer();
}

