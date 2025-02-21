<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:viewstats', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/stats.php', array('id' => $id));
$PAGE->set_title("Statistiques des exercices");
$PAGE->set_heading("Statistiques des exercices");

echo $OUTPUT->header();

// 🔍 Check if user is a teacher/admin
$is_teacher = has_capability('mod/hippotrack:manage', $context);

// 📌 SQL Query: Different for Teachers vs. Students
if ($is_teacher) {
    // 🎓 Teacher: View Stats for All Students
    $stats = $DB->get_records_sql("
        SELECT a.input_type, COUNT(a.id) as attempts, 
               SUM(a.is_correct) as correct, 
               ROUND(AVG(s.timespent), 2) as avg_time
        FROM {hippotrack_attempt} a
        JOIN {hippotrack_sessions} s ON a.id_session = s.id
        WHERE s.instanceid = ?
        GROUP BY a.input_type
        ORDER BY attempts DESC", array($instance->id));
} else {
    // 🧑‍🎓 Student: View Only Their Own Stats
    $stats = $DB->get_records_sql("
        SELECT a.input_type, COUNT(a.id) as attempts, 
               SUM(a.is_correct) as correct, 
               ROUND(AVG(s.timespent), 2) as avg_time
        FROM {hippotrack_attempt} a
        JOIN {hippotrack_training_sessions} s ON a.sessionid = s.id
        WHERE s.instanceid = ? AND s.userid = ?
        GROUP BY a.input_type
        ORDER BY attempts DESC", array($instance->id, $userid));
}

// 🎯 Display Role-Specific Titles
if ($is_teacher) {
    echo html_writer::tag('h2', "Statistiques globales des exercices");
} else {
    echo html_writer::tag('h2', "Vos statistiques personnelles");
}

// 📌 Display Table of Statistics
echo html_writer::start_tag('table', array('class' => 'table table-bordered', 'style' => 'width:100%; text-align:center;'));
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Type d\'Input');
echo html_writer::tag('th', 'Nombre d\'essais');
echo html_writer::tag('th', 'Réponses correctes');
echo html_writer::tag('th', 'Taux de réussite (%)');
echo html_writer::tag('th', 'Temps moyen (s)');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($stats as $stat) {
    $success_rate = ($stat->attempts > 0) ? round(($stat->correct / $stat->attempts) * 100, 2) : 0;
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', ucfirst(str_replace('_', ' ', $stat->input_type)));
    echo html_writer::tag('td', $stat->attempts);
    echo html_writer::tag('td', $stat->correct);
    echo html_writer::tag('td', "$success_rate%");
    echo html_writer::tag('td', $stat->avg_time);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// 🔙 Back Button
$back_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));
echo $OUTPUT->single_button($back_url, 'Retour', 'get');

echo $OUTPUT->footer();