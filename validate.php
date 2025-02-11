<?php
require_once('../../config.php');
require_login();

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);

// Vérifier si la session contient des réponses
if (!isset($_SESSION['hippotrack_attempt_' . $attemptid]) || empty($_SESSION['hippotrack_attempt_' . $attemptid])) {
    throw new moodle_exception('noanswers', 'mod_hippotrack');
}

// Sauvegarde en base de données
foreach ($_SESSION['hippotrack_attempt_' . $attemptid] as $response) {
    $record = new stdClass();
    $record->id_attempt = $attemptid;
    $record->id_dataset = $response['id_dataset'];
    $record->sigle = $response['sigle'];
    $record->inclinaison = $response['inclinaison'];
    $record->rotation = $response['rotation'];
    $record->schema_simplifie = null; //TODO a mettre a jour
    $record->vue_anterieure = $response['vue_anterieure'];
    $record->vue_laterale = $response['vue_laterale'];

    $DB->insert_record('hippotrack_feedback', $record);
}

// Supprimer la session après enregistrement
unset($_SESSION['hippotrack_attempt_' . $attemptid]);

// Rediriger vers la page de fin de quiz
redirect(new moodle_url('/mod/hippotrack/view.php', ['id' => $id, 'attemptid' => $attemptid])); //TODO faire une page de fin
