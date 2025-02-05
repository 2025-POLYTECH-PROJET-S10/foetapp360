<?php

require_once('../../config.php'); // Inclut la configuration de Moodle
require_login(); // Vérifie que l'utilisateur est connecté

$userid = $USER->id; // Récupère l'ID de l'utilisateur actuel

$context = context_system::instance(); // Définition du contexte
require_capability('mod/hippotrack:view', $context); // Vérifie les permissions

$PAGE->set_url(new moodle_url('/mod/hippotrack/view_attempts.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('viewattempts', 'mod_hippotrack'));
$PAGE->set_heading(get_string('viewattempts', 'mod_hippotrack'));

echo $OUTPUT->header();

// Récupérer les tentatives de l'utilisateur
global $DB;
$attempts = $DB->get_records('hippotrack_attempt', ['userid' => $userid]);

if (!$attempts) {
    echo html_writer::div(get_string('noattempts', 'mod_hippotrack'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = ['ID', 'Attempt', 'State', 'Start Time', 'Finish Time', 'Grade', 'Exercises Done', 'Difficulty'];

    foreach ($attempts as $attempt) {
        $table->data[] = [
            $attempt->id,
            $attempt->attempt,
            $attempt->state,
            userdate($attempt->timestart),
            $attempt->timefinish ? userdate($attempt->timefinish) : '-',
            $attempt->sumgrades ?? '-',
            $attempt->exercicesdone ?? '-',
            $attempt->difficulty ?? '-'
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
