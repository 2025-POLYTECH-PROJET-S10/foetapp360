<?php
// Fichier : mod/hippotrack/mystats.php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/hippotrack/classes/stats_manager.php');

global $DB, $PAGE, $OUTPUT, $USER;

$cmid = required_param('id', PARAM_INT);
$studentid = optional_param('userid',0, PARAM_INT);


// Récupération des paramètres
$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

// Vérification des droits d'accès
require_login($course);
$context = context_course::instance($course->id);


// Check if the student ID is set
if (!$studentid){
    $studentid = $USER->id;
    var_dump($studentid);
} else if ($studentid != $USER->id) {
    // Check if the user is a teacher
    require_capability('mod/hippotrack:viewstats', $context);
}

// Récupération de l'instance hippotrack et vérification
$hippotrack = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);

// Configuration de la page
$PAGE->set_url('/mod/hippotrack/mystats.php', ['id' => $cmid]);
$PAGE->set_title(get_string('mystats', 'mod_hippotrack'));
$PAGE->set_heading($course->fullname);

// Instanciation du gestionnaire de statistiques
$stats_manager = new \mod_hippotrack\stats_manager($DB);

// Récupération des données de l'étudiant
$studentstats = $stats_manager->get_student_stats($hippotrack->id, $studentid);
$performancedata = $stats_manager->get_student_performance_data($studentid, $hippotrack->id);

// Affichage
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mystats', 'mod_hippotrack'));



echo "<h3>📊 Statistiques personnelles</h3>";

##########
//Time passed
$total_time = $stats_manager->get_student_time_passed($hippotrack->id, $studentid);
// Now, convert total time to hours and minutes
$hours = floor($total_time / 3600); // Calculate total hours
$minutes = floor(($total_time % 3600) / 60); // Calculate remaining minutes

echo "<p><strong>Temps total passé :</strong> {$hours}h{$minutes}</p>";
##########

//Difficulties ammount
$difficulties_amount = $stats_manager->get_student_difficulties_amount($hippotrack->id, $studentid);
list($easy_session, $hard_session) = $difficulties_amount;
echo "<p><strong>Sessions réalisées :</strong> {$easy_session} (Facile) | {$hard_session} (Difficile)</p>";

// Success Rate by Difficulty (Bar Chart)
list($easy_success, $hard_success) = $stats_manager->get_student_success_rate($hippotrack->id, $studentid);
$chart = new \core\chart_bar();
$series = new \core\chart_series('Taux de réussite (%)', [$easy_success, $hard_success]);
$chart->add_series($series);
$chart->set_labels(['Facile', 'Difficile']);

echo $OUTPUT->render($chart);
##########

//Success rate per difficulties
$success_rates = $stats_manager->get_success_rate_by_input($hippotrack->id, $studentid);

$labels = array_keys($success_rates);
$values = array_values($success_rates);

$chart = new \core\chart_bar();
$series = new \core\chart_series('Taux de réussite par type d’input (%)', $values);
$chart->add_series($series);
$chart->set_labels($labels);

echo $OUTPUT->render($chart);

##########

// Get success rates by representation type
$success_rates = $stats_manager->get_success_rate_by_representation($hippotrack->id, $studentid);

// Extract labels and values for the chart
$labels = array_keys(array_merge($success_rates['correct'], $success_rates['ok'], $success_rates['bad']));
$correct_values = [];
$ok_values = [];
$bad_values = [];

// Populate values while ensuring all labels exist in each category
foreach ($labels as $label) {
    $correct_values[] = $success_rates['correct'][$label] ?? 0;
    $ok_values[] = $success_rates['ok'][$label] ?? 0;
    $bad_values[] = $success_rates['bad'][$label] ?? 0;
}

// Render bar chart
$chart = new \core\chart_bar();
$chart->add_series(new \core\chart_series('Bien Fléchie (%)', $correct_values));
$chart->add_series(new \core\chart_series('Peu Fléchie (%)', $ok_values));
$chart->add_series(new \core\chart_series('Mal Fléchie (%)', $bad_values));
$chart->set_labels($labels);

echo $OUTPUT->render($chart);
##########

echo html_writer::end_tag('div');

echo $OUTPUT->footer();