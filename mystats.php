<?php
// Fichier : mod/hippotrack/mystats.php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/hippotrack/classes/StatsManager.php');

global $DB, $PAGE, $OUTPUT, $USER;

// Récupération des paramètres
$cmid = required_param('id', PARAM_INT);

// Récupération de l'instance hippotrack et vérification
$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$hippotrack = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
require_login($course);

// Configuration de la page
$PAGE->set_url('/mod/hippotrack/mystats.php', ['id' => $cmid]);
$PAGE->set_title(get_string('mystats', 'mod_hippotrack'));
$PAGE->set_heading($course->fullname);

// Instanciation du gestionnaire de statistiques
$statsmanager = new \mod_hippotrack\StatsManager($DB);

// Récupération des données de l'étudiant
$studentstats = $statsmanager->get_student_stats($cmid, $USER->id);
$performancedata = $statsmanager->get_student_performance_data($USER->id, $cmid);

// Affichage
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mystats', 'mod_hippotrack'));

// Liste des sessions
echo html_writer::start_tag('div', ['class' => 'student-stats']);
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

echo $OUTPUT->footer();