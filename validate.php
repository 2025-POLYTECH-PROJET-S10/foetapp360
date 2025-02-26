<?php
require_once('../../config.php');
require_login();

global $DB, $USER;

$id = required_param('id', PARAM_INT); // Hippotrack instance id
$session_id = required_param('session_id', PARAM_INT);
$timefinish = time();

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$hippotrack = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);

// Vérifier si la session contient des réponses
if (!isset($_SESSION['hippotrack_session_' . $session_id]) || empty($_SESSION['hippotrack_session_' . $session_id])) {
    throw new moodle_exception('noanswers', 'mod_hippotrack');
}

// Sauvegarde en base de données la session en cours
$session = new stdClass();
$session->id_hippotrack = $hippotrack->id;
$session->userid = $USER->id;
$session->timestart = $_SESSION['hippotrack_session_' . $session_id]['_time_start'];
$session->timefinish = $timefinish;
$session->sumgrades = $_SESSION['hippotrack_session_' . $session_id]['_sumgrades'];
$session->questionsdone = $_SESSION['hippotrack_session_' . $session_id]['_questionsdone'];
$session->difficulty = $_SESSION['hippotrack_session_' . $session_id]['_difficulty'];
$DB->insert_record('hippotrack_session', $session);

// Sauvegarde en base de données des tentatives
$attempt_number = 0;
foreach ($_SESSION['hippotrack_session_' . $session_id]['attempts'] as $response) {
    $record = new stdClass();
    $record->id_session = $session_id;
    $record->id_dataset = $response['id_dataset'];
    $record->attempt_number = $attempt_number;
    $record->name = $response['name'];
    $record->sigle = $response['sigle'];
    $record->partogram = $response['partogramme'];
    $record->schema_simplifie = $response['schema_simplifie'];
    if (isset($response['vue_anterieure'])) {
        $record->vue_anterieure = $response['vue_anterieure'];
    } else {
        $record->vue_anterieure = "";
    }
    if (isset($response['vue_laterale'])) {
        $record->vue_laterale = $response['vue_laterale'];
    } else {
        $record->vue_laterale = "";
    }
    $record->given_input = $response['given_input'];
    $record->is_correct = $response['is_correct'];
    $DB->insert_record('hippotrack_attempt', $record);
    $attempt_number++;
}

// Supprimer la session après enregistrement
unset($_SESSION['hippotrack_session_' . $session_id]);

// Rediriger vers la page de fin de quiz
$finish_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));
redirect($finish_url);
