<?php
require_once('../../config.php');
require_once('addattempt_form.php');

global $DB, $USER;

$id = required_param('id', PARAM_INT); // ID de l'activité hippotrack

// Vérification des permissions
require_login();
$context = context_system::instance();
require_capability('mod/hippotrack:addattempt', $context);

// Création du formulaire
$mform = new addattempt_form();

// Si le formulaire est soumis et valide
if ($mform->is_submitted() && $mform->is_validated()) {
    $data = $mform->get_data();

    // Préparation des données à insérer
    $record = new stdClass();
    $record->id_hippotrack = $id;
    $record->userid = $data->userid;
    $record->attempt = $data->attempt;
    $record->state = $data->state;
    $record->timestart = time();
    $record->timefinish = 0;
    $record->sumgrades = $data->sumgrades ?: null;
    $record->exercicesdone = $data->exercicesdone ?: null;
    $record->difficulty = $data->difficulty ?: null;

    // Insertion dans la base de données
    $DB->insert_record('hippotrack_attempt', $record);

    // Redirection après l'insertion
    redirect(new moodle_url('/mod/hippotrack/view.php', array('id' => $id)), 'Tentative ajoutée avec succès!', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Affichage de la page
$PAGE->set_url('/mod/hippotrack/addattempt.php', array('id' => $id));
$PAGE->set_context($context);
$PAGE->set_title('Ajouter une tentative');
$PAGE->set_heading('Ajouter une tentative');

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
