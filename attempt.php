<?php
require_once('../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT); // ID du module hippotrack
$questionnum = required_param('q', PARAM_INT); // Numéro de la question affichée
$current_dataset_id = required_param('d', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT); // ID de la tentative en cours

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

$current_dataset = $DB->get_record('hippotrack_datasets', ['id' => $current_dataset_id], '*', MUST_EXIST);

// Configuration de la page
$PAGE->set_url('/mod/hippotrack/attempt.php', ['id' => $id, 'q' => $questionnum, 'attemptid' => $attemptid]);
$PAGE->set_title('Tentative - Question ' . $questionnum);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Affichage de la question actuelle
echo '<div class="quiz-content">';
echo '<h2>Question ' . $questionnum . '</h2>';
echo '<h2>' . format_string($current_dataset->name) . '</h2>';
echo '<p><strong>Sigle :</strong> ' . format_string($current_dataset->sigle) . '</p>';
echo '<p><strong>Rotation :</strong> ' . format_string($current_dataset->rotation) . '°</p>';
echo '<p><strong>Inclinaison :</strong> ' . ($current_dataset->inclinaison == 1 ? 'Bien fléchi' : 'Mal fléchi') . '</p>';

if (!empty($current_dataset->schema_simplifie)) {
    echo '<p><strong>Schéma simplifié :</strong></p>';
    echo '<img src="' . $current_dataset->schema_simplifie . '" alt="Schéma simplifié" style="max-width: 100%; height: auto;"/>';
}

// Bouton pour passer à la question suivante
echo '<form method="post" action="correction.php">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<input type="hidden" name="attemptid" value="' . $attemptid . '">';
echo '<input type="hidden" name="d" value="' . $current_dataset_id . '">';
echo '<input type="hidden" name="q" value="' . $questionnum . '">';
echo '<input type="text" name="answer" placeholder="TA REPONSE CONARD C MTN OU DANS 100 ANS!!!!">';
echo '<button type="submit">Valider</button>';
echo '</form>';
echo '</div>';

echo $OUTPUT->footer();
?>
