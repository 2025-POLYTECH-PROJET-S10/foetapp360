<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/db_form.php');

// Add namespace reference at the top
use mod_foetapp360\image_manager;

// 📌 Parameter Validation
$cmid = required_param('cmid', PARAM_INT);
$userid = $USER->id;

// 📌 Retrieve Course Module and Context
$cm = get_coursemodule_from_id('foetapp360', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// 📌 Access Control
require_login($course, true, $cm);
require_capability('mod/foetapp360:manage', $context);

// 📌 Page Configuration
$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/foetapp360/manage_datasets.php', array('id' => $cmid));
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

        $dataset = $DB->get_record('foetapp360_datasets', array('id' => $deleting), '*', MUST_EXIST);

        $image_manager_anterieure->delete_image($dataset->id, $dataset->vue_anterieure);
        $image_manager_laterale->delete_image($dataset->id, $dataset->vue_laterale);

        $DB->delete_records('foetapp360_datasets', array('id' => $deleting));
        redirect(
            new moodle_url('/mod/foetapp360/manage_datasets.php', array('cmid' => $cmid)),
            "Entrée supprimée avec succès.",
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        redirect(
            new moodle_url('/mod/foetapp360/manage_datasets.php', array('cmid' => $cmid)),
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
            '/mod/foetapp360/db_form_submission.php',
            array('cmid' => $cmid, 'edit' => $dataset->id)
        );
        $delete_url = new moodle_url(
            '/mod/foetapp360/manage_datasets.php',
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



echo '<div class="dataset-management">
        <p>Dans cette interface, vous pouvez <strong>gérer les ensembles de données</strong> qui seront utilisés par les étudiants. Vous avez la possibilité <strong>d\'ajouter, modifier ou supprimer</strong> des ensembles.</p>
        
        <p>Chaque ensemble comprend plusieurs éléments :</p>

        <ul>
            <li><strong>Nom</strong> : L\'étudiant devra indiquer le nom de la variété de présentation la position, sans avoir à respecter les majuscules ou les tirets.</li>
            <li><strong>Sigle</strong> : le sigle représentant la variété de présentation.</li>
            <li><strong>Rotation</strong> : Correspond à l’angle d’orientation du fœtus, utilisé dans le <strong>partogramme</strong> et le <strong>schéma simplifié</strong>.</li>
        </ul>

        <ul>
            <li>L’angle <strong>0° Occipito-Sacrée (OS)</strong>.</li>
            <li>Les rotations sont définies en <strong>sens horaire</strong> :
                <ul>
                    <li><strong>Occipito-Iliaque Droite Postérieure (OIDP) = 45°</strong></li>
                    <li><strong>Occipito-Iliaque Droite Transverse (OIDT) = 90°</strong></li>
                </ul>
            </li>
            <li>Les étudiants devront mettre la <strong>bonne rotation du fœtus en déplaçant un curseur</strong>.</li>
            <li>Une <strong>tolérance de 5°</strong> est accordée pour les axes perpendiculaires (<strong>OS, OP, OIDT, OIGT</strong>). Sinon, l\'angle pris en compte sera la <strong>diagonale la plus proche</strong>.</li>
        </ul>

        <ul>
            <li><strong>Inclinaison</strong> : Valeur comprise entre <strong>-1 et 1</strong> :
                <ul>
                    <li><strong>1 = Bien fléchi</strong></li>
                    <li><strong>-1 = Mal fléchi</strong></li>
                    <li><strong>Tout le reste = Peu fléchi</strong></li>
                    <li>Une tolérance de <strong>5 degrés</strong> est appliquée pour la classification.</li>
                </ul>
            </li>
        </ul>

        <ul>
            <li><strong>Images associées :</strong>
                <ul>
                    <li><strong>Vue antérieure</strong></li>
                    <li><strong>Vue latérale</strong></li>
                </ul>
            </li>
        </ul>

        <p>Les étudiants recevront un <strong>feedback personnalisé</strong> pour les <strong>16 positions initiales</strong> afin de les guider dans leur apprentissage.</p>
      </div>';




echo '<div class="foetapp360-license-notice">
 <img src="' . new moodle_url('/mod/foetapp360/pix/licence-cc-by-nc.png') . '" alt="CC BY-NC License">
 <br>
 FoetApp360\'s images © 2024 by Pierre-Yves Rabattu is licensed under CC BY-NC 4.0. 
 To view a copy of this license, visit 
 <a href="https://creativecommons.org/licenses/by-nc/4.0/" target="_blank">here</a>.
</div>';


if (!$showform && !$editing) {
    // Display datasets table
    echo html_writer::tag('h2', "Liste des ensembles de données");
    $datasets = $DB->get_records('foetapp360_datasets', array(), 'id ASC');
    echo render_datasets_table($datasets, $context, $cmid, $OUTPUT);

    // Add new entry button
    $addnew_url = new moodle_url('/mod/foetapp360/db_form_submission.php', array('cmid' => $cmid, 'addnew' => 1));
    echo $OUTPUT->single_button($addnew_url, "Ajouter une nouvelle entrée", "get");
} else {
    if ($editing) {
        redirect(new moodle_url('/mod/foetapp360/db_form_submission.php', array('cmid' => $cmid, 'edit' => $editing)));
    } else {
        redirect(new moodle_url('/mod/foetapp360/db_form_submission.php', array('cmid' => $cmid, 'addnew' => 1)));
    }
}
echo $OUTPUT->footer();