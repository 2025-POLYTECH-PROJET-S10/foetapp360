<?php
require_once('../../config.php');
require_login();

global $DB, $USER;

$id = required_param('id', PARAM_INT); // Hippotrack instance id
$session_id = required_param('session_id', PARAM_INT);
$timefinish = time();

// Vérifier si la session contient des réponses
if (!isset($_SESSION['hippotrack_session_' . $session_id]) || empty($_SESSION['hippotrack_session_' . $session_id])) {
    throw new moodle_exception('noanswers', 'mod_hippotrack');
}

// Sauvegarde en base de données la session en cours
$session = new stdClass();
$session->id_hippotrack = $id;
$session->userid = $USER->id;
$session->timestart = $timefinish;
$session->timefinish = $timefinish;
$session->sumgrades = $_SESSION['hippotrack_session_' . $session_id . '_sumgrades'];
$session->questionsdone = $_SESSION['hippotrack_session_' . $session_id . '_questionsdone'];
$session->gradednotificationsenttime = $timefinish;
$session->difficulty = $_SESSION['hippotrack_session_' . $session_id . '_difficulty'];
$DB->insert_record('hippotrack_session', $session);

// Sauvegarde en base de données des tentatives
$attempt_number = 0;
foreach ($_SESSION['hippotrack_session_' . $session_id] as $response) {
    $record = new stdClass();
    $record->id_session = $session_id;
    $record->id_dataset = $response['id_dataset'];
    $record->attempt_number = $attempt_number;
    $record->name = $response['name'];
    $record->sigle = $response['sigle'];
    $record->partogram = $response['partogramme'];
    $record->schema_simplifie = $response['schema_simplifie']; //TODO a mettre a jour
    if (isset($response['vue_anterieure'])) {
        $record->vue_anterieure = $response['vue_anterieure'];
    } else {
        $record->vue_anterieure = "";
    }
    if (isset($response['vue_laterale'])) {
        $record->vue_anterieure = $response['vue_laterale'];
    } else {
        $record->vue_anterieure = "";
    }
    $DB->insert_record('hippotrack_attempt', $record);
    $attempt_number++;
}

// Supprimer la session après enregistrement
unset($_SESSION['hippotrack_session_' . $session_id]);
unset($_SESSION['hippotrack_session_' . $session_id . '_sumgrades']);
unset($_SESSION['hippotrack_session_' . $session_id . '_questionsdone']);
unset($_SESSION['hippotrack_session_' . $session_id . '_difficulty']);

// Rediriger vers la page de fin de quiz
$finish_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));
redirect($finish_url);
