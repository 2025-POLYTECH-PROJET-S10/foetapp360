<?php
require_once('../../config.php');
require_login();

global $DB, $USER;

$STRING_FOR_HARD_DIFFICULTY = "HARD_DIFFICULTY";

$id = required_param('id', PARAM_INT); // Foetapp360 instance id
$session_id = required_param('session_id', PARAM_INT);
$timefinish = time();

$cm = get_coursemodule_from_id('foetapp360', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$foetapp360 = $DB->get_record('foetapp360', array('id' => $cm->instance), '*', MUST_EXIST);

// Vérifier si la session contient des réponses
if (!isset($_SESSION['foetapp360_session_' . $session_id]) || empty($_SESSION['foetapp360_session_' . $session_id])) {
    throw new \moodle_exception('noanswers', 'mod_foetapp360');
}

// Sauvegarde en base de données la session en cours
$session = $DB->get_record('foetapp360_session', array('id' => $session_id));
// $session->id_foetapp360 = $foetapp360->id;
// $session->userid = $USER->id;
$session->timestart = $_SESSION['foetapp360_session_' . $session_id]['_time_start'];
$session->timefinish = $timefinish;
$session->sumgrades = $_SESSION['foetapp360_session_' . $session_id]['_sumgrades'];
$session->questionsdone = $_SESSION['foetapp360_session_' . $session_id]['_questionsdone'];
$session->difficulty = $_SESSION['foetapp360_session_' . $session_id]['_difficulty'];
$DB->update_record('foetapp360_session', $session); // J'ai changer la façon de faire pour la sauvegarde de la session
// Avant c'était un insert maintenant c'est un update, car on a déjà un enregistrement de session

// Supprimer la session après enregistrement
unset($_SESSION['foetapp360_session_' . $session_id]);

// Rediriger vers la page de fin de quiz
$finish_url = new moodle_url('/mod/foetapp360/view.php', array('id' => $id));
redirect($finish_url);
