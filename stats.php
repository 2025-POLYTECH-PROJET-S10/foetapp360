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

function get_user_fullname($user)
{
    return $user->firstname . ' ' . $user->lastname;
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
echo html_writer::tag('p', 'Taux de réussite : ' . (($globalstats['success_rate'] / $globalstats['total_attempts']) ?? 0) * 100 . '%');
echo html_writer::end_tag('div');

/* -------------------------------------------------------------------------- */
/*                             DEBUT TABLEAU EXOS                             */
/* -------------------------------------------------------------------------- */

echo html_writer::start_tag('table', array('class' => 'table table-striped'));
echo html_writer::start_tag('thead');
echo html_writer::tag(
    'tr',
    html_writer::tag('th', "Nom") .
    html_writer::tag('th', "Sigle") .
    html_writer::tag('th', get_string('attempts', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('successrate', 'mod_hippotrack'))
);
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

$correct_datasets = $DB->get_records('hippotrack_datasets', null, '', '*');

foreach ($correct_datasets as $dataset) {
    $exostats = $stats_manager->get_exo_stats($cm->instance, $dataset->id);
    $attempts = $exostats['total_attempts'] ?? 0;
    $successrate = $exostats['success_rate'] ?? 0;

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $dataset->name);
    echo html_writer::tag('td', $dataset->sigle);
    echo html_writer::tag('td', $attempts);
    echo html_writer::tag('td', $successrate . '%');
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');


/* -------------------------------------------------------------------------- */
/*                             FIN TABLEAU EXOS                               */
/* -------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------- */
/*                       DEBUT TABLEAU ÉTUDIANTS                              */
/* -------------------------------------------------------------------------- */

// Tableau récapitulatif de tous les étudiants avec le nombre d'attempts et leur taux de réussite
echo html_writer::start_tag('table', array('class' => 'table table-striped'));
echo html_writer::start_tag('thead');
echo html_writer::tag(
    'tr',
    html_writer::tag('th', get_string('student', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('attempts', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('successrate', 'mod_hippotrack')) .
    html_writer::tag('th', '')
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($students as $student) {
    // Récupération des statistiques de l'étudiant
    $studentstats = $stats_manager->get_student_stats($cmid, $student->id);
    $attempts = count($studentstats);

    // Récupération des données de performance pour calculer le taux de réussite
    $performance = $stats_manager->get_student_performance_data($student->id, $cmid);
    $totalAttempts = count($performance);
    $successful = 0;
    foreach ($performance as $attempt) {
        if ($attempt->is_correct) {
            $successful++;
        }
    }
    $rate = $totalAttempts > 0 ? round(($successful / $totalAttempts) * 100, 2) : 0;

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



//#######################################   ######################

echo "<h3>📊 Statistiques globales</h3>";

// ✅ 1. Success Rate by Difficulty (Bar Chart)
$sql = "SELECT s.difficulty, AVG(a.is_correct) * 100 AS success_rate 
            FROM {hippotrack_attempt} a
            JOIN {hippotrack_session} s ON a.id_session = s.id
            WHERE s.id_hippotrack = :hippotrackid
            GROUP BY s.difficulty";
$params = ['hippotrackid' => $hippotrack->id];
$results = $DB->get_records_sql($sql, $params);
$easy_success = round($results['Easy']->success_rate ?? 0, 2);
$hard_success = round($results['Hard']->success_rate ?? 0, 2);

$chart = new \core\chart_bar();
$series = new \core\chart_series('Taux de réussite', [$easy_success, $hard_success]);
$chart->add_series($series);
$chart->set_labels(['Facile', 'Difficile']);

echo $OUTPUT->render($chart);

// ✅ 2. Success Rate by Visual Representation (Bar Chart)
$sql = "SELECT d.name, AVG(a.is_correct) * 100 AS success_rate 
            FROM {hippotrack_attempt} a
            JOIN {hippotrack_datasets} d ON a.id_dataset = d.id
            JOIN {hippotrack_session} s ON a.id_session = s.id
            WHERE s.id_hippotrack = :hippotrackid
            GROUP BY d.name";
$results = $DB->get_records_sql($sql, $params);

$labels = [];
$data = [];
foreach ($results as $row) {
    $labels[] = $row->name;
    $data[] = round($row->success_rate, 2);
}

$chart = new \core\chart_bar();
$series = new \core\chart_series('Taux de réussite par représentation', $data);
$chart->add_series($series);
$chart->set_labels($labels);

echo $OUTPUT->render($chart);

// ✅ 3. Success Rate by Input Type (Bar Chart)
$sql = "SELECT a.given_input, AVG(a.is_correct) * 100 AS success_rate 
            FROM {hippotrack_attempt} a
            JOIN {hippotrack_session} s ON a.id_session = s.id
            WHERE s.id_hippotrack = :hippotrackid
            GROUP BY a.given_input";
$results = $DB->get_records_sql($sql, $params);

$labels = [];
$data = [];
foreach ($results as $row) {
    $labels[] = $row->given_input;
    $data[] = round($row->success_rate, 2);
}

$chart = new \core\chart_bar();
$series = new \core\chart_series('Taux de réussite par input', $data);
$chart->add_series($series);
$chart->set_labels($labels);

echo $OUTPUT->render($chart);





//#############################################################






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