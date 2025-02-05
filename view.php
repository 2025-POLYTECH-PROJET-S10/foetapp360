<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of hippotrack.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$h = optional_param('h', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $h), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('hippotrack', $moduleinstance->id, $course->id, false, MUST_EXIST);
}



// if (!$cm = get_coursemodule_from_id('hippotrack', $id)) {
//     throw new moodle_exception('invalidcoursemodule');
// }

// if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
//     throw new moodle_exception('coursemisconf');
// }

// if (!$module = $DB->get_record('hippotrack', ['id' => $cm->instance])) {
//     throw new moodle_exception('DataBase for hippotrack not found');
// }

// TODO ajouter un check ici (voir dans quiz).
$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or ...
$q  = optional_param('q',  0, PARAM_INT); // Quiz ID.

// Check login and get instance
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

if (has_capability('moodle/site:config', $context)) {
    echo "Bienvenue, Administrateur !";

    // View attempts button
    $url = new moodle_url('/mod/hippotrack/viewattempts.php', array('id' => $id));
    echo $OUTPUT->single_button($url, 'Voir les tentatives', 'get');

    // TEST add attemtp
    $url = new moodle_url('/mod/hippotrack/addattempt.php', array('id' => $id));
    echo $OUTPUT->single_button($url, 'Ajouter une tentative', 'get');


} elseif (has_capability('mod/hippotrack:view', $context)) {
    echo "Bienvenue, Utilisateur !";

    // View attempts button
    $url = new moodle_url('/mod/hippotrack/viewattempts.php', array('id' => $id));
    echo $OUTPUT->single_button($url, 'Voir les tentatives', 'get');

    // TEST add attemtp
    $url = new moodle_url('/mod/hippotrack/addattempt.php', array('id' => $id));
    echo $OUTPUT->single_button($url, 'Ajouter une tentative', 'get');

} else {
    echo "Accès refusé.";
}

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/hippotrack:attempt', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();

$PAGE->set_url('/mod/hippotrack/view.php', array('id' => $id));

//Debut de l'affichage
echo $OUTPUT->header();


// Title Poll
$divTitle = '<div id=divTitle>';
$divTitle .= '<h2 id=namePoll>';
$divTitle .= get_string('pluginname', 'mod_hippotrack', $cm->name);
$divTitle .= '</h2>';
$divTitle .= '<div>';

echo $divTitle;



echo $OUTPUT->footer();