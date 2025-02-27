<?php
// Fichier : mod/hippotrack/stats.php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/hippotrack/classes/stats_manager.php');

global $DB, $PAGE, $OUTPUT, $USER;

// Récupération des paramètres
$cmid = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

// Récupération de l'instance hippotrack et vérification
$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$hippotrack = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
// $course = $DB->get_record('course', ['id' => $hippotrack->course], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('mod/hippotrack:viewstats', $context);

// Configuration de la page
$PAGE->set_url('/mod/hippotrack/stats.php', ['id' => $cmid]);
$PAGE->set_title(get_string('stats', 'mod_hippotrack'));
$PAGE->set_heading($course->fullname);

// Instanciation du gestionnaire de statistiques
$stats_manager = new \mod_hippotrack\stats_manager($DB);

// Récupération des statistiques globales
$globalstats = $stats_manager->get_global_stats($cmid);

// Récupération de la liste des étudiants pour le menu déroulant
$students = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.firstname, u.lastname
     FROM {user} u
     JOIN {hippotrack_session} s ON u.id = s.userid
     WHERE s.id_hippotrack = :hippotrackid",
    ['hippotrackid' => $cmid]
);

function get_user_fullname($user) {
    return $user->firstname .' '. $user->lastname;
}

// Affichage
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('stats', 'mod_hippotrack'));


/* -------------------------------------------------------------------------- */
/*                            STATISTIQUES GLOBALES                           */
/* -------------------------------------------------------------------------- */

// Statistiques globales
echo html_writer::start_tag('div', ['class' => 'global-stats']);
echo html_writer::tag('h3', get_string('globalstats', 'mod_hippotrack'));
echo html_writer::tag('p', 'Nombre total d’étudiants : ' . ($globalstats['total_students'] ?? 0));
echo html_writer::tag('p', 'Taux de réussite : ' . (($globalstats['success_rate']/$globalstats['total_attempts']) ?? 0)*100 . '%');
echo html_writer::end_tag('div');

/* -------------------------------------------------------------------------- */
/*                             DEBUT TABLEAU EXOS                             */
/* -------------------------------------------------------------------------- */
// Récupérer les jeux de données corrects
$correct_datasets = $DB->get_records('hippotrack_datasets', null, '', '*');

/* -------------------------------------------------------------------------- */
/*                           GRAPHIQUES MODE FACILE                           */
/* -------------------------------------------------------------------------- */

// Tableaux pour stocker les noms uniques de positions
$unique_positions = [];

// Identifier les noms uniques
foreach ($correct_datasets as $dataset) {
    if (!in_array($dataset->name, $unique_positions)) {
        $unique_positions[] = $dataset->name;
    }
}

// Récupérer les statistiques pour le mode facile
$stats_easy = $stats_manager->get_exo_stats_by_difficulty($hippotrack->id, 'easy');

// Pour chaque type d'inclinaison, créer un graphique
foreach (['bien', 'mal', 'peu'] as $inclinaison_type) {
    $inclinaison_label = ucfirst($inclinaison_type) . " Fléchi";
    echo html_writer::tag('h3', "Graphique des Tentatives - $inclinaison_label - Mode Facile");
    
    $chart = new \core\chart_bar();
    $success_data = [];
    $fail_data = [];
    
    // Récupérer les données pour chaque position
    foreach ($unique_positions as $position_name) {
        $attempts = isset($stats_easy[$inclinaison_type]['total_attempts']) ? 
                    $stats_easy[$inclinaison_type]['total_attempts'] : 0;
        $correct = isset($stats_easy[$inclinaison_type]['correct_attempts']) ? 
                  $stats_easy[$inclinaison_type]['correct_attempts'] : 0;
        
        $success_data[] = $correct;
        $fail_data[] = $attempts - $correct;
    }
    
    // Création des séries
    $series_success = new \core\chart_series("Succès", $success_data);
    $series_fail = new \core\chart_series("Échec", $fail_data);
    
    // Ajout des séries au graphique
    $chart->add_series($series_success);
    $chart->add_series($series_fail);
    $chart->set_labels($unique_positions);
    $chart->set_stacked(true);
    
    // Affichage du graphique
    echo $OUTPUT->render($chart);
}

/* -------------------------------------------------------------------------- */
/*                          GRAPHIQUE MODE DIFFICILE                          */
/* -------------------------------------------------------------------------- */

echo html_writer::tag('h2', "Graphique des Tentatives Mode Difficile");

// Création du graphique
$chart_hard = new \core\chart_bar();

// Tableaux de données pour le mode difficile
$labels_hard = [];
$hard_attempts_data = [];
$hard_success_data = [];
$hard_fail_data = [];

$hardstats = $stats_manager->get_exo_stats_by_difficulty($hippotrack->id, 'hard');
var_dump($hardstats);

// Récupération des données
foreach ($correct_datasets as $dataset) {

    // Labels
    $labels_hard[] = $dataset->name;

    // Calcul des tentatives et du taux de succès
    $hard_attempts = $hardstats['total_attempts'] ?? 0;
    $hard_success = ($hardstats['success_rate'] ?? 0);
    $hard_fail = $hard_attempts - $hard_success;

    $hard_attempts_data[] = $hard_attempts;
    $hard_success_data[] = $hard_success;
    $hard_fail_data[] = $hard_fail;
}

// Création des séries pour le mode difficile
$series_hard_success = new \core\chart_series("Succès", $hard_success_data);
$series_hard_fail = new \core\chart_series("Échec", $hard_fail_data);

// Ajout des séries au graphique
$chart_hard->add_series($series_hard_success);
$chart_hard->add_series($series_hard_fail);
$chart_hard->set_labels($labels_hard);
$chart_hard->set_stacked(true); // Empilement pour visualiser le pourcentage

// Affichage du graphique
echo $OUTPUT->render($chart_hard);


/* -------------------------------------------------------------------------- */
/*                             FIN TABLEAU EXOS                               */
/* -------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------- */
/*                       DEBUT TABLEAU ÉTUDIANTS                              */
/* -------------------------------------------------------------------------- */

// Tableau récapitulatif de tous les étudiants avec le nombre d'attempts et leur taux de réussite
echo html_writer::start_tag('table', array('class' => 'table table-striped'));
echo html_writer::start_tag('thead');
echo html_writer::tag('tr',
    html_writer::tag('th', get_string('student', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('attempts', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('successrate', 'mod_hippotrack')) .
    html_writer::tag('th', '')
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($students as $student) {
    // Récupération des statistiques de l'étudiant
    $studentstats = $stats_manager->get_student_stats( $hippotrack->id, $student->id);
    $attempts = $studentstats['total_attempts'] ?? 0;
    
    var_dump($studentstats);
    // Récupération des données de performance pour calculer le taux de réussite
    if ($attempts > 0) {
        $rate = ($studentstats['success_total'] ?? 0) / $attempts * 100;
    } else {
        $rate = 0;
    }

    // Création d'un bouton pour afficher les statistiques complètes de l'étudiant
    $url = new moodle_url('/mod/hippotrack/stats.php', ['id' => $cmid, 'userid' => $student->id]);
    $button = html_writer::link($url, get_string('showstats', 'mod_hippotrack'), ['class' => 'btn btn-primary']);

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', get_user_fullname($student));
    echo html_writer::tag('td', $attempts);
    echo html_writer::tag('td', $rate . '%');
    echo html_writer::tag('td', $button);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

/* -------------------------------------------------------------------------- */
/*                            FIN TABLEAU ÉTUDIANTS                           */
/* -------------------------------------------------------------------------- */

// ! TO BE REMOVED
// Statistiques spécifiques à un étudiant
if ($userid) {
    $studentstats = $stats_manager->get_student_stats($cmid, $userid);
    $performancedata = $stats_manager->get_student_performance_data($userid, $cmid);

    echo html_writer::start_tag('div', ['class' => 'student-stats']);
    echo html_writer::tag('h3', get_string('studentstats', 'mod_hippotrack') . ' : ' . get_user_fullname($students[$userid]));
    echo '<ul>';
    foreach ($studentstats as $session) {
        echo html_writer::tag('li', 'Session #' . $session->id . ' - Note : ' . $session->sumgrades . ' - Questions : ' . $session->questionsdone);
    }
    echo '</ul>';

    // Préparation des données pour le graphique
    $labels = [];
    $success = [];
    foreach ($performancedata as $attempt) {
        $labels[] = $attempt->attempt_number;
        $success[] = $attempt->is_correct;
    }

    // Graphique avec Chart.js
    echo '<canvas id="performanceChart" width="400" height="200"></canvas>';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script>
        var ctx = document.getElementById("performanceChart").getContext("2d");
        new Chart(ctx, {
            type: "line",
            data: {
                labels: ' . json_encode($labels) . ',
                datasets: [{
                    label: "' . get_string('success', 'mod_hippotrack') . '",
                    data: ' . json_encode($success) . ',
                    borderColor: "rgba(75, 192, 192, 1)",
                    fill: false
                }]
            },
            options: {
                scales: { y: { beginAtZero: true, max: 1 } }
            }
        });
    </script>';
    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();