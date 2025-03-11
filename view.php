<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $USER, $DB;

$id = required_param('id', PARAM_INT); // Course module ID

$cm = get_coursemodule_from_id('foetapp360', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$moduleinstance = $DB->get_record('foetapp360', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$is_teacher = has_capability('mod/foetapp360:manage', $context);
$is_student = has_capability('mod/foetapp360:attempt', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/foetapp360/view.php', array('id' => $id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/foetapp360/styles.css');

echo $OUTPUT->header();
echo html_writer::tag('h2', format_string($moduleinstance->name), array('class' => 'foetapp360-title'));

// 📌 Fonction pour vérifier si une page existe
function page_exists($page)
{
    global $CFG;
    return file_exists($CFG->dirroot . "/mod/foetapp360/$page");
}

// 📌 Interface enseignant
if ($is_teacher) {
    echo html_writer::start_div('foetapp360-teacher-options');

    // 📊 Voir les statistiques
    $stats_url = new moodle_url('/mod/foetapp360/stats.php', array('id' => $id));
    if (page_exists('stats.php')) {
        echo $OUTPUT->single_button($stats_url, '📊 Voir les statistiques', 'get');
    } else {
        echo html_writer::tag('button', '📊 Voir les statistiques (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }

    // 📂 Gérer les ensembles
    $manage_url = new moodle_url('/mod/foetapp360/manage_datasets.php', array('cmid' => $id));
    if (page_exists('manage_datasets.php')) {
        echo $OUTPUT->single_button($manage_url, '➕ Gérer les ensembles', 'get');
    } else {
        echo html_writer::tag('button', '➕ Gérer les ensembles (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }

    echo html_writer::end_div();
}

// 📌 Interface étudiant
if ($is_student) {
    echo html_writer::start_div('foetapp360-student-options');

    // 🔍 Vérification des essais
    //$existing_attempts = $DB->count_records('foetapp360_session', array('userid' => $USER->id, 'instanceid' => $moduleinstance->id));
    $history_url = new moodle_url('/mod/foetapp360/history.php', array('id' => $id));

    /*
    if (page_exists('history.php')) {
        echo $OUTPUT->single_button($history_url, '📜 Voir les anciennes tentatives', 'get');
    } else {
        echo html_writer::tag('button', '📜 Voir les anciennes tentatives (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }*/

    // 📊 Voir les statistiques
    $stats_url = new moodle_url('/mod/foetapp360/mystats.php', array('id' => $id));
    if (page_exists('stats.php')) {
        echo $OUTPUT->single_button($stats_url, '📊 Voir mes statistiques', 'get');
    } else {
        echo html_writer::tag('button', '📊 Voir les statistiques (Bientôt dispo)', array('disabled' => 'disabled', 'class' => 'btn btn-secondary'));
    }


   // 🆕 Improved session handling
    try {
        $session_id = foetapp360_start_new_session($moduleinstance->id, $USER->id);
        
        $attempt_url = new moodle_url('/mod/foetapp360/attempt.php', [
            'id' => $id,
            'session_id' => $session_id
        ]);
        
        if (page_exists('attempt.php')) {
            echo $OUTPUT->single_button($attempt_url, '🚀 Lancer une session d\'exercice', 'get');
        } else {
            echo html_writer::tag('button', '🚀 Lancer une session (Bientôt dispo)', 
                ['disabled' => 'disabled', 'class' => 'btn btn-secondary']);
        }
    } catch (dml_exception $e) {
        debugging('Failed to create session: '.$e->getMessage(), DEBUG_DEVELOPER);
        echo $OUTPUT->notification(get_string('sessionerror', 'foetapp360'));
    }

    echo html_writer::end_div();
}


echo $OUTPUT->footer();
