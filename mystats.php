<?php
// Fichier : mod/hippotrack/mystats.php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/hippotrack/classes/stats_manager.php');

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
$stats_manager = new \mod_hippotrack\stats_manager($DB);

// Récupération des données de l'étudiant
$studentstats = $stats_manager->get_student_stats($cmid, $USER->id);
$performancedata = $stats_manager->get_student_performance_data($USER->id, $cmid);

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


#################################################################################

echo "<h3>📊 Statistiques personnelles</h3>";

$sessions = $DB->get_records('hippotrack_session', ['id_hippotrack' => $cmid]);

$total_time = 0; // Initialize total time in seconds

foreach ($sessions as $session) {
    // Calculate duration of each session (in seconds)
    $session_duration = $session->timefinish - $session->timestart;
    $total_time += $session_duration; // Add session duration to total time
}

// Now, convert total time to hours and minutes
$hours = floor($total_time / 3600); // Calculate total hours
$minutes = floor(($total_time % 3600) / 60); // Calculate remaining minutes

echo "<p><strong>Temps total passé :</strong> {$hours}h{$minutes}</p>";


// ✅ 2. Get Number of Sessions by Difficulty


$easy_session = 0;
$hard_session = 0;
foreach ($sessions as $session) {
    if ($session->difficulty == 'easy') {
        $easy_session += 1;
    } else if ($session->difficulty == 'hard') {
        $hard_session += 1;
    }
}
echo "<p><strong>Sessions réalisées :</strong> {$easy_session} (Facile) | {$hard_session} (Difficile)</p>";



$sql = "SELECT a.id, a.attempt_number, a.is_correct
FROM {hippotrack_attempt} a
JOIN {hippotrack_session} s ON a.id_session = s.id
WHERE s.userid = :userid AND s.id_hippotrack = :hippotrackid
ORDER BY a.attempt_number ASC";

$params = ['userid' => $userid, 'hippotrackid' => $hippotrackid];










$sql_result = "SELECT a.id, a.attempt_number, a.is_correct
FROM {hippotrack_attempt} a
JOIN {hippotrack_session} s ON a.id_session = s.id
WHERE s.userid = :userid AND s.id_hippotrack = :hippotrackid
ORDER BY a.attempt_number ASC";

$params = ['userid' => $userid, 'hippotrackid' => $hippotrackid];
var_dump($sql_result);





// ✅ 3. Success Rate by Difficulty (Bar Chart)
$sql = "SELECT s.difficulty, AVG(a.is_correct) * 100 AS success_rate 
            FROM {hippotrack_attempt} a
            JOIN {hippotrack_session} s ON a.id_session = s.id
            WHERE s.userid = :userid AND s.id_hippotrack = :hippotrackid
            GROUP BY s.difficulty";
$params = ['userid' => $USER->id, 'hippotrackid' => $hippotrack->id];
$results = $DB->get_records_sql($sql, $params);

// Initialize success rates for easy and hard
$easy_success = 0;
$hard_success = 0;

foreach ($results as $result) {
    if ($result->difficulty == 'easy') {
        $easy_success = round($result->success_rate, 2);
    } else if ($result->difficulty == 'hard') {
        $hard_success = round($result->success_rate, 2);
    }
}

$chart = new \core\chart_bar();
$series = new \core\chart_series('Taux de réussite', [$easy_success, $hard_success]);
$chart->add_series($series);
$chart->set_labels(['Facile', 'Difficile']);

echo $OUTPUT->render($chart);




#################################################################################
echo html_writer::end_tag('div');

echo $OUTPUT->footer();