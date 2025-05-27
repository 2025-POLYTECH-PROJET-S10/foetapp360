<?php
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/foetapp360/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID

$cm = get_coursemodule_from_id('foetapp360', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$foetapp360 = $DB->get_record('foetapp360', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Check if user has teacher capabilities
require_capability('mod/foetapp360:manage', $context);

$PAGE->set_url('/mod/foetapp360/credits.php', array('id' => $cm->id));
$PAGE->set_title(format_string($foetapp360->name) . ' - Crédits');
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Crédits du plugin FoetApp360');

echo html_writer::start_div('credits-container', array('style' => 'background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;'));

echo html_writer::tag('h3', 'Paternité');

echo html_writer::tag('h4', 'Idéation');
echo html_writer::tag('p', 'Lionel Di Marco ; Lucile Vadcard');

echo html_writer::tag('h4', 'Code');
echo html_writer::tag('p', 'Brice Vittet ; Ali Fawaz ; Romain Hocquet ; Alexandre Moua');

echo html_writer::tag('h4', 'Graphismes');
echo html_writer::tag('p', 'Pierre-Yves Rabattu');

echo html_writer::tag('h3', 'Contact');
echo html_writer::tag('p', 'Lionel Di Marco : ' . 
    html_writer::link('mailto:ldimarco@chu-grenoble.fr', 'ldimarco@chu-grenoble.fr'));

echo html_writer::end_div();

echo html_writer::link(new moodle_url('/mod/foetapp360/view.php', array('id' => $cm->id)), 
    'Retour à l\'activité', array('class' => 'btn btn-secondary'));

echo $OUTPUT->footer();
?>
