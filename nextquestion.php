<?php
require_once('../../config.php');
require_once('locallib.php');
require_login();

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT); // ID du module hippotrack
$attemptid = required_param('attemptid', PARAM_INT); // ID de la tentative en cours
// Enregistrer la progression de la question dans la session ou la base de données
$questionnum = optional_param('q', 1, PARAM_INT); // On prend le numéro de question passé dans l'URL ou 1 si absent

// Récupération du contexte et des informations du cours
$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
if (!$cm) {
    throw new moodle_exception('invalidcoursemodule', 'error');
}

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
if (!$course) {
    throw new moodle_exception('invalidcourse', 'error');
}
if ($cm->course != $course->id) {
    throw new coding_exception("Mismatch between course ID in cm and actual course record.");
}

$PAGE->set_cm($cm, $course);
$context = context_module::instance($cm->id);
$PAGE->set_context($context);
require_capability('mod/hippotrack:attempt', $context);

$questionnum = $questionnum + 1; // Incrémenter le numéro de la question
$current_dataset = hippotrack_get_random_dataset();

// Rediriger vers la question suivante
redirect(new moodle_url('/mod/hippotrack/attempt.php', [
    'id' => $id,
    'q' => $questionnum, // La question suivante
    'd' => $current_dataset->id,
    'attemptid' => $attemptid
]));
?>
