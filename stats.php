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

echo html_writer::start_tag('table', array('class' => 'table table-striped'));
echo html_writer::start_tag('thead');
echo html_writer::tag('tr',
    html_writer::tag('th', "Nom") .
    html_writer::tag('th', "Sigle") .
    html_writer::tag('th', get_string('attempts', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('attempts', 'mod_hippotrack') . " mode facile") .
    html_writer::tag('th', get_string('attempts', 'mod_hippotrack') . " mode difficile") .
    html_writer::tag('th', get_string('successrate', 'mod_hippotrack') . " mode facile") .
    html_writer::tag("th", get_string('successrate', 'mod_hippotrack') . " mode difficile")
);
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

$correct_datasets = $DB->get_records('hippotrack_datasets', null, '', '*');

foreach ($correct_datasets as $dataset) {
    $exostats = $stats_manager->get_exo_stats($hippotrack->id, $dataset->id);
    $easystats = $stats_manager->get_exo_stats_by_difficulty($hippotrack->id, $dataset->id, 'easy');
    $hardstats = $stats_manager->get_exo_stats_by_difficulty($hippotrack->id, $dataset->id, 'hard');
    // var_dump($exostats);
    $attempts = $exostats['total_attempts'] ?? 0;
    $easyattempts = $easystats['total_attempts'] ?? 0;
    $hardattempts = $hardstats['total_attempts'] ?? 0;

    $successrate = $exostats['success_rate'] ?? 0;
    $easysuccessrate = $easystats['success_rate'] ?? 0;
    $hardsuccessrate = $hardstats['success_rate'] ?? 0;

    debugging("hipp id :" . $hippotrack->id . " - ID dataset : " . $dataset->id);

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $dataset->name);
    echo html_writer::tag('td', $dataset->sigle);
    echo html_writer::tag('td', $attempts);
    echo html_writer::tag('td', $easyattempts);
    echo html_writer::tag('td', $hardattempts);
    echo html_writer::tag('td', $easysuccessrate . '%');
    echo html_writer::tag('td', $hardsuccessrate . '%');
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