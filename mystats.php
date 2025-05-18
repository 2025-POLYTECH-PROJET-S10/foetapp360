<?php
// Fichier : mod/foetapp360/mystats.php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/foetapp360/classes/stats_manager.php');

global $DB, $PAGE, $OUTPUT, $USER;

$cmid = required_param('id', PARAM_INT);
$studentid = optional_param('userid',0, PARAM_INT);


// Récupération des paramètres
$cm = get_coursemodule_from_id('foetapp360', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

// Vérification des droits d'accès
require_login($course);
$context = context_course::instance($course->id);


// Check if the student ID is set
if (!$studentid){
    $studentid = $USER->id;
    // var_dump($studentid); // Commented out
} else if ($studentid != $USER->id) {
    // Check if the user is a teacher
    require_capability('mod/foetapp360:viewstats', $context);
}

// Récupération de l'instance foetapp360 et vérification
$foetapp360 = $DB->get_record('foetapp360', array('id' => $cm->instance), '*', MUST_EXIST);

// Configuration de la page
$PAGE->set_url('/mod/foetapp360/mystats.php', ['id' => $cmid]);
$PAGE->set_title(get_string('mystats', 'mod_foetapp360'));
$PAGE->set_heading($course->fullname);

// Instanciation du gestionnaire de statistiques
$stats_manager = new \mod_foetapp360\stats_manager($DB);

// Récupération des données de l'étudiant
$studentstats = $stats_manager->get_student_stats($foetapp360->id, $studentid);
$performancedata = $stats_manager->get_student_performance_data($studentid, $foetapp360->id);

// Calcul du sessions réalisées
$difficulties_amount = $stats_manager->get_student_difficulties_amount($foetapp360->id, $studentid);
list($easy_session, $hard_session) = $difficulties_amount;

// Affichage
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mystats', 'mod_foetapp360'));

// Check if there is any data, if studentstats is empty, it means user didn't take attemps
if (empty($studentstats) || ($easy_session + $hard_session <= 0 )) { // or if you have another specific metric to check, like total_attempts == 0 use && in if

      echo html_writer::start_tag('div', ['class' => 'no-data-message alert alert-info']);
      echo html_writer::tag('h4', get_string('nostatsavailabletitle', 'mod_foetapp360')); // Use a language string
      echo html_writer::tag('p', get_string('nostatsavailabletext', 'mod_foetapp360')); // Use a language string, optional.
      echo html_writer::end_tag('div');

      echo $OUTPUT->footer();
      exit;
}
else {
    // Only display if there's data 
    echo "<h3>📊 Statistiques personnelles</h3>";

    ##########
    //Time passed
    $total_time = $stats_manager->get_student_time_passed($foetapp360->id, $studentid);
    // Now, convert total time to hours and minutes
    $hours = floor($total_time / 3600); // Calculate total hours
    $minutes = floor(($total_time % 3600) / 60); // Calculate remaining minutes

    echo "<p><strong>Temps total passé :</strong> {$hours}h{$minutes}m</p>";
    ##########

    //Difficulties ammount
    // ! Le code qui était ici a été déplacé plus haut (afin de faire des vérifications avant d'afficher les statistiques)
    echo "<p><strong>Sessions réalisées :</strong> {$easy_session} (Facile) | {$hard_session} (Difficile)</p>";

    // Success Rate by Difficulty (Bar Chart)
    list($easy_success, $hard_success) = $stats_manager->get_student_success_rate($foetapp360->id, $studentid);
    $chart = new \core\chart_bar();
    $series = new \core\chart_series('Taux de réussite (%)', [$easy_success, $hard_success]);
    $chart->add_series($series);
    $chart->set_labels(['Facile', 'Difficile']);

    echo $OUTPUT->render($chart);
    ##########

    //Success rate per difficulties
    $success_rates = $stats_manager->get_success_rate_by_input($foetapp360->id, $studentid);

    $labels = array_keys($success_rates);
    $values = array_values($success_rates);

    $chart = new \core\chart_bar();
    $series = new \core\chart_series('Taux de réussite par type d’input (%)', $values);
    $chart->add_series($series);
    $chart->set_labels($labels);

    echo $OUTPUT->render($chart);

    ##########

    // Get success rates by representation type
    $success_rates = $stats_manager->get_success_rate_by_representation($foetapp360->id, $studentid);

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
}


echo $OUTPUT->footer();
