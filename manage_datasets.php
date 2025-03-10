<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/db_form.php');

// Add namespace reference at the top
use mod_hippotrack\image_manager;

// 📌 Parameter Validation
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
$PAGE->set_url('/mod/hippotrack/manage_datasets.php', array('id' => $cmid));
$PAGE->set_title("Gestion des ensembles de données");
$PAGE->set_heading("Gestion des ensembles de données");

// 📌 Action Parameters
$editing = optional_param('edit', 0, PARAM_INT); // Edit an entry
$deleting = optional_param('delete', 0, PARAM_INT); // Delete an entry
$showform = optional_param('addnew', 0, PARAM_BOOL); // Add a new entry

// 📌 Handle Deletion
if ($deleting && confirm_sesskey()) {
    try {
        // Delete images 
        $image_manager_anterieure = new image_manager('vue_anterieure');
        $image_manager_laterale = new image_manager('vue_laterale');

        $dataset = $DB->get_record('hippotrack_datasets', array('id' => $deleting), '*', MUST_EXIST);

        $image_manager_anterieure->delete_image($dataset->id, $dataset->vue_anterieure);
        $image_manager_laterale->delete_image($dataset->id, $dataset->vue_laterale);

        $DB->delete_records('hippotrack_datasets', array('id' => $deleting));
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Entrée supprimée avec succès.",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        redirect(
            new moodle_url('/mod/hippotrack/manage_datasets.php', array('cmid' => $cmid)),
            "Une erreur est survenue lors de la suppression de l'entrée.",
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}


// 📌 Helper Function to Render Datasets Table
function render_datasets_table($datasets, $context, $cmid, $OUTPUT)
{
    global $CFG;

    if (empty($datasets)) {
        return html_writer::tag('p', "Aucune donnée disponible.", array('class' => 'alert alert-warning'));
    }

    // Initialize image managers with system context (matches installation context)
    $image_manager_anterieure = new image_manager(
        'vue_anterieure'
    );

    $image_manager_laterale = new image_manager(
        'vue_laterale'
    );

    $table_html = html_writer::start_tag('table', array('class' => 'table table-striped'));
    $table_html .= html_writer::start_tag('thead') . html_writer::start_tag('tr');
    $table_html .= html_writer::tag('th', 'Nom')
        . html_writer::tag('th', 'Sigle')
        . html_writer::tag('th', 'Rotation')
        . html_writer::tag('th', 'Inclinaison')
        . html_writer::tag('th', 'Vue Antérieure')
        . html_writer::tag('th', 'Vue Latérale')
        . html_writer::tag('th', 'Actions');
    $table_html .= html_writer::end_tag('tr') . html_writer::end_tag('thead');

    $table_html .= html_writer::start_tag('tbody');


    foreach ($datasets as $dataset) {
        // Get actual filenames from database record
        $anterieure_filename = $dataset->vue_anterieure;
        $laterale_filename = $dataset->vue_laterale;

        // Get image URLs using image manager
        $anterieure_url = $image_manager_anterieure->getImageUrl($dataset->id, $anterieure_filename);
        $laterale_url = $image_manager_laterale->getImageUrl($dataset->id, $laterale_filename);

        // Create image previews with lightbox functionality
        $anterieure_img = $anterieure_url
            ? html_writer::link(
                $anterieure_url,
                html_writer::empty_tag('img', array(
                    'src' => $anterieure_url,
                    'style' => 'max-width: 100px; height: auto;',
                    'class' => 'img-thumbnail',
                    'loading' => 'lazy'
                )),
                array('data-lightbox' => 'vue_anterieure')
            )
            : html_writer::tag('span', 'Image manquante', array('class' => 'text-muted'));

        $laterale_img = $laterale_url
            ? html_writer::link(
                $laterale_url,
                html_writer::empty_tag('img', array(
                    'src' => $laterale_url,
                    'style' => 'max-width: 100px; height: auto;',
                    'class' => 'img-thumbnail',
                    'loading' => 'lazy'
                )),
                array('data-lightbox' => 'vue_laterale')
            )
            : html_writer::tag('span', 'Image manquante', array('class' => 'text-muted'));

        $table_html .= html_writer::start_tag('tr');
        $table_html .= html_writer::tag('td', $dataset->name);
        $table_html .= html_writer::tag('td', $dataset->sigle);
        $table_html .= html_writer::tag('td', $dataset->rotation);
        $table_html .= html_writer::tag('td', $dataset->inclinaison);
        $table_html .= html_writer::tag('td', $anterieure_img);
        $table_html .= html_writer::tag('td', $laterale_img);

        // Actions
        $edit_url = new moodle_url(
            '/mod/hippotrack/db_form_submission.php',
            array('cmid' => $cmid, 'edit' => $dataset->id)
        );
        $delete_url = new moodle_url(
            '/mod/hippotrack/manage_datasets.php',
            array('cmid' => $cmid, 'delete' => $dataset->id, 'sesskey' => sesskey())
        );

        $actions = html_writer::div(
            $OUTPUT->single_button($edit_url, 'Modifier', 'get', ['class' => 'mr-1']) .
            $OUTPUT->single_button($delete_url, 'Supprimer', 'post'),
            'd-flex justify-content-around'
        );

        $table_html .= html_writer::tag('td', $actions);
        $table_html .= html_writer::end_tag('tr');
    }
    $table_html .= html_writer::end_tag('tbody') . html_writer::end_tag('table');

    return $table_html;
}

// 📌 Main Output
echo $OUTPUT->header();

if (!$showform && !$editing) {
    // Display datasets table
    echo html_writer::tag('h2', "Liste des ensembles de données");
    $datasets = $DB->get_records('hippotrack_datasets', array(), 'id ASC');
    echo render_datasets_table($datasets, $context, $cmid, $OUTPUT);

    // Add new entry button
    $addnew_url = new moodle_url('/mod/hippotrack/db_form_submission.php', array('cmid' => $cmid, 'addnew' => 1));
    echo $OUTPUT->single_button($addnew_url, "Ajouter une nouvelle entrée", "get");
} else {
    if ($editing) {
        redirect(new moodle_url('/mod/hippotrack/db_form_submission.php', array('cmid' => $cmid, 'edit' => $editing)));
    } else {
        redirect(new moodle_url('/mod/hippotrack/db_form_submission.php', array('cmid' => $cmid, 'addnew' => 1)));
    }
}
echo '<div class="hippotrack-license-notice">
 <img src="' . new moodle_url('/mod/hippotrack/pix/licence-cc-by-nc.png') . '" alt="CC BY-NC License">
 <br>
 FoetApp360\'s images © 2024 by Pierre-Yves Rabattu is licensed under CC BY-NC 4.0. 
 To view a copy of this license, visit 
 <a href="https://creativecommons.org/licenses/by-nc/4.0/" target="_blank">here</a>.
</div>';

echo $OUTPUT->footer();