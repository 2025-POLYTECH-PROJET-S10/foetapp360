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
require_once('locallib.php');

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

// Check login and get instance
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/hippotrack:attempt', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();

// Moodle page configuration
$PAGE->set_url('/mod/hippotrack/view.php', array('id' => $id));
$PAGE->set_title($moduleinstance->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

//Debut de l'affichage
echo $OUTPUT->header();
echo '<div id="divTitle"><h2 id="namePoll">' . format_string($moduleinstance->name) . '</h2></div>';

// Affichage du contenu
if (has_capability('moodle/site:config', $context)) {
    echo "<p>Bienvenue, Administrateur !</p>";
} elseif (has_capability('mod/hippotrack:view', $context)) {
    echo "<p>Bienvenue, Utilisateur !</p>";
} else {
    echo "<p>Accès refusé.</p>";
}

// Affichage des boutons
if (has_capability('mod/hippotrack:view', $context)) {
    $viewattempts_url = new moodle_url('/mod/hippotrack/viewattempts.php', array('id' => $id));
    echo $OUTPUT->single_button($viewattempts_url, 'Voir les tentatives', 'get');

    // Générer ou récupérer un attemptid si nécessaire (par exemple, on peut initialiser à 0 si c'est la première tentative)
    $attemptid = 0; // ou une valeur récupérée à partir de la base de données si une tentative existe
    $current_dataset = hippotrack_get_random_dataset();
    
    $addattempt_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id, 'q' => 1, 'd' => $current_dataset->id,'attemptid' => $attemptid));

    echo $OUTPUT->single_button($addattempt_url, "Commencer l'entrainement", 'get');
}

//Fin de l'affichage
echo $OUTPUT->footer();