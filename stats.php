<?php
// Fichier : mod/hippotrack/stats.php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/hippotrack/classes/StatsManager.php');

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
$statsmanager = new \mod_hippotrack\StatsManager($DB);

// Récupération des statistiques globales
$globalstats = $statsmanager->get_global_stats($cmid);

// Récupération de la liste des étudiants pour le menu déroulant
$students = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.firstname, u.lastname
     FROM {user} u
     JOIN {hippotrack_session} s ON u.id = s.userid
     WHERE s.id_hippotrack = :hippotrackid",
    ['hippotrackid' => $cmid]
);

// Affichage
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('stats', 'mod_hippotrack'));

// Statistiques globales
echo html_writer::start_tag('div', ['class' => 'global-stats']);
echo html_writer::tag('h3', get_string('globalstats', 'mod_hippotrack'));
echo html_writer::tag('p', 'Nombre total d’étudiants : ' . ($globalstats['total_students'] ?? 0));
echo html_writer::tag('p', 'Taux de réussite : ' . (($globalstats['success_rate']/$globalstats['total_attempts']) ?? 0)*100 . '%');
echo html_writer::end_tag('div');

// Menu déroulant pour sélectionner un étudiant
$options = [0 => get_string('selectstudent', 'mod_hippotrack')];
foreach ($students as $student) {
    $options[$student->id] = fullname($student);
}
echo html_writer::select($options, 'userid', $userid, false, [
    'id' => 'student_selector',
    'onchange' => 'location.href="?id=' . $cmid . '&userid="+this.value;'
]);

// Statistiques spécifiques à un étudiant
if ($userid) {
    $studentstats = $statsmanager->get_student_stats($cmid, $userid);
    $performancedata = $statsmanager->get_student_performance_data($userid, $cmid);

    echo html_writer::start_tag('div', ['class' => 'student-stats']);
    echo html_writer::tag('h3', get_string('studentstats', 'mod_hippotrack') . ' : ' . fullname($students[$userid]));
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
        $success[] = $attempt->success;
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