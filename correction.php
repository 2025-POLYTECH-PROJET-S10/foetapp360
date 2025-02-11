<?php
require_once('../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT); // ID du module hippotrack
$questionnum = required_param('q', PARAM_INT); // Numéro de la question affichée
$current_dataset_id = required_param('d', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT); // ID de la tentative en cours
$answer = required_param('answer', PARAM_TEXT); // Answer to the previous question

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

// Récupérer le dataset depuis la base de données
$current_dataset = $DB->get_record('hippotrack_datasets', array('id' => $current_dataset_id));

// Vérifier si le dataset existe
if (!$current_dataset) {
    throw new moodle_exception('missing_dataset', 'mod_hippotrack', '', null, 'Dataset introuvable avec cet ID.');
}

$PAGE->set_cm($cm, $course);
$context = context_module::instance($cm->id);
$PAGE->set_context($context);
require_capability('mod/hippotrack:attempt', $context);

// Vérifie si la session contient déjà des réponses
if (!isset($_SESSION['hippotrack_attempt_' . $attemptid])) {
    $_SESSION['hippotrack_attempt_' . $attemptid] = [];
}

// Sauvegarde la réponse actuelle
if ($answer !== null) {
    $_SESSION['hippotrack_attempt_' . $attemptid][] = [
        'id_dataset' => $current_dataset_id,
        'sigle' => "TEST",
        'rotation' => 360,
        'inclinaison' => 1,
        'vue_anterieure' => "SHAMROCK >>> MIDHAWK",
        'vue_laterale' => $answer
    ];
}

// Configuration de la page
$PAGE->set_url('/mod/hippotrack/attempt.php', ['id' => $id, 'q' => $questionnum, 'attemptid' => $attemptid]);
$PAGE->set_title('Tentative - Question ' . $questionnum);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

echo '<pre>';
var_dump($_SESSION['hippotrack_attempt_' . $attemptid]);  // Affiche une structure détaillée du tableau
echo '</pre>';


// Affichage de la question actuelle
echo '<div class="quiz-content">';
echo '<h2>Correction : Question ' . $questionnum . '</h2>';
echo '<h2>' . format_string($current_dataset->name) . '</h2>';
echo '<p><strong>Sigle :</strong> ' . format_string($current_dataset->sigle) . '</p>';
echo '<p><strong>Rotation :</strong> ' . format_string($current_dataset->rotation) . '°</p>';
echo '<p><strong>Inclinaison :</strong> ' . ($current_dataset->inclinaison == 1 ? 'Bien fléchi' : 'Mal fléchi') . '</p>';

if (!empty($current_dataset->schema_simplifie)) {
    echo '<p><strong>Schéma simplifié :</strong></p>';
    echo '<img src="' . $current_dataset->schema_simplifie . '" alt="Schéma simplifié" style="max-width: 100%; height: auto;"/>';
}

// Bouton pour passer à la question suivante
echo '<form method="post" action="nextquestion.php">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<input type="hidden" name="attemptid" value="' . $attemptid . '">';
echo '<input type="hidden" name="q" value="' . $questionnum . '">';
echo '<button type="submit">Question suivante</button>';
echo '</form>';

// Bouton pour terminer
echo '<form method="post" action="validate.php">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<input type="hidden" name="attemptid" value="' . $attemptid . '">';
echo '<button type="submit">Terminer la session</button>';
echo '</form>';

echo '</div>';

echo $OUTPUT->footer();
?>
