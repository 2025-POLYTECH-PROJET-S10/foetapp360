<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information
 *
 * @package    mod_easyvote
 * @copyright  2016 Cyberlearn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/sessionlib.php');

$id = required_param('id', PARAM_INT);
$difficulty = optional_param('difficulty', '', PARAM_ALPHA);
$new_question = optional_param('new_question', 0, PARAM_INT);
$submitted = optional_param('submitted', 0, PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:attempt', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/attempt.php', array('id' => $id));
$PAGE->set_title("Session d'entraînement");
$PAGE->set_heading("Session d'entraînement");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['validate'])) {
    foreach ($_POST as $key => $value) {
        // Check if the key contains 'rotation_' to match the field for the selected image
        if (strpos($key, 'rotation_') === 0) {
            $field = str_replace('rotation_', '', $key); // Extract the field name
            $rotation = intval($value);
            $inclinaison = optional_param("inclinaison_$field", 0, PARAM_INT);

            // Debugging
            error_log("Processing: $field | Rotation: $rotation | Inclinaison: $inclinaison");


        }
    }
}







echo $OUTPUT->header();

// 📌 Étape 1 : Sélection de la difficulté
if (empty($difficulty)) {
    echo html_writer::tag('h3', "Choisissez votre niveau de difficulté");

    $easy_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id, 'difficulty' => 'easy'));
    $hard_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id, 'difficulty' => 'hard'));

    echo html_writer::start_div('difficulty-selection');
    echo $OUTPUT->single_button($easy_url, 'Facile', 'get');
    echo $OUTPUT->single_button($hard_url, 'Difficile', 'get');
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// 📌 Vérifier s'il existe déjà une session en cours pour l'utilisateur
$session = $DB->get_record('hippotrack_training_sessions', array(
    'userid' => $userid,
    'instanceid' => $instance->id,
    'difficulty' => $difficulty,
    'finished' => 0
));

// 📌 🔥 CORRECTION : Définir toujours $possible_inputs avant toute logique
$possible_inputs = ($difficulty === 'easy') ?
    ['name', 'sigle', 'partogramme', 'simplified_schematic', 'vue_anterieure', 'vue_laterale'] :
    ['name', 'sigle', 'partogramme', 'simplified_schematic'];

// 📌 S'assurer qu'on a bien une question dès la première session
if (!$session || $new_question == 1) {
    $random_entry = $DB->get_record_sql("SELECT * FROM {hippotrack_datasets} ORDER BY RAND() LIMIT 1");
    $possible_inputs = ($difficulty === 'easy') ?
        ['name', 'sigle', 'partogramme', 'simplified_schematic', 'vue_anterieure', 'vue_laterale'] :
        ['name', 'sigle', 'partogramme', 'simplified_schematic'];

    $random_input = $possible_inputs[array_rand($possible_inputs)];


    if (!$session) {
        // 📌 Nouvelle session -> On génère une question dès le départ
        $session = new stdClass();
        $session->userid = $userid;
        $session->instanceid = $instance->id;
        $session->difficulty = $difficulty;
        $session->questionid = $random_entry->id;
        $session->input_type = $random_input;
        $session->timecreated = time();
        $session->finished = 0;

        $session->id = $DB->insert_record('hippotrack_training_sessions', $session);
    } else {
        // 📌 Mise à jour pour une nouvelle question
        $session->questionid = $random_entry->id;
        $session->input_type = $random_input;
        $session->timecreated = time();
        $DB->update_record('hippotrack_training_sessions', $session);
    }
}

// 📌 Récupérer la question de la session actuelle
$random_entry = $DB->get_record('hippotrack_datasets', array('id' => $session->questionid));
$random_input = $session->input_type;
$random_input_label = ucfirst(str_replace('_', ' ', $random_input));
$pre_filled_value = $random_entry->$random_input;


echo html_writer::tag('h3', "Trouvez les bonnes correspondances pour :");

// 📌 Correction après validation
if ($submitted) {
    echo html_writer::tag('h3', "Correction :");
    $is_correct = true;
    $feedback = "Bravo ! Toutes les réponses sont correctes.";

    foreach ($possible_inputs as $field) {
        if ($field === 'partogramme' || $field === 'simplified_schematic') {
            // 🔥 Correction spéciale pour partogramme et schéma simplifié (ils utilisent rotation + inclinaison)
            $student_inclinaison = required_param("inclinaison_$field", PARAM_RAW);
            $student_rotation = required_param("rotation_$field", PARAM_RAW);

            $correct_inclinaison = $random_entry->inclinaison;
            $correct_rotation = $random_entry->rotation;

            // Vérification des deux valeurs ensemble
            if ($student_inclinaison != $correct_inclinaison || $student_rotation != $correct_rotation) {
                $is_correct = false;
                $feedback = "Oops, certaines réponses sont incorrectes. Vérifiez et essayez encore !";
            }

            echo html_writer::tag('p', "<strong>$field :</strong> Votre inclinaison : $student_inclinaison | Rotation : $student_rotation <br> Réponse correcte : Inclinaison $correct_inclinaison | Rotation $correct_rotation");
        } else {
            // 🔥 Cas normal (name, sigle, vue_anterieure, vue_laterale)
            $student_answer = required_param($field, PARAM_RAW);
            $correct_answer = $random_entry->$field;

            // Debugging: Log the values
            echo ("Field: $field");
            echo ("Student Answer: $student_answer");
            echo ("Correct Answer: $correct_answer");

            if ($student_answer != $correct_answer) {
                $is_correct = false;
                $feedback = "Oops, certaines réponses sont incorrectes. Vérifiez et essayez encore !";
            }

            echo html_writer::tag('p', "<strong>$field :</strong> Votre réponse : $student_answer | Réponse correcte : $correct_answer");
        }





    }

    echo html_writer::tag('p', $feedback, array('class' => $is_correct ? 'correct' : 'incorrect'));

    // 📌 Enregistrer les réponses de l'étudiant dans la base de données
    foreach ($possible_inputs as $field) {
        $attempt = new stdClass();
        $attempt->sessionid = $session->id;
        $attempt->datasetid = $random_entry->id;
        $attempt->input_type = $field;
        $attempt->timeanswered = time();

        if ($field === 'partogramme' || $field === 'simplified_schematic') {
            // 🔥 Cas spécial : Stocker la rotation et l'inclinaison pour partogramme et schéma
            $student_inclinaison = required_param("inclinaison_$field", PARAM_RAW);
            $student_rotation = required_param("rotation_$field", PARAM_RAW);
            $correct_inclinaison = $random_entry->inclinaison;
            $correct_rotation = $random_entry->rotation;

            $attempt->student_response = "Inclinaison: $student_inclinaison, Rotation: $student_rotation";
            $attempt->is_correct = ($student_inclinaison == $correct_inclinaison && $student_rotation == $correct_rotation) ? 1 : 0;
        } else {
            // 🔥 Cas normal (name, sigle, vue_anterieure, vue_laterale)
            $student_answer = required_param($field, PARAM_RAW);
            $correct_answer = $random_entry->$field;

            $attempt->student_response = $student_answer;
            $attempt->is_correct = ($student_answer == $correct_answer) ? 1 : 0;
        }

        // 📌 Insérer la tentative en base
        $DB->insert_record('hippotrack_attempts', $attempt);
    }


    // 📌 Boutons "Nouvelle Question" et "Terminer"
    $new_question_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $id, 'difficulty' => $difficulty, 'new_question' => 1));
    $finish_url = new moodle_url('/mod/hippotrack/view.php', array('id' => $id));

    echo $OUTPUT->single_button($new_question_url, 'Nouvelle Question', 'get');
    echo $OUTPUT->single_button($finish_url, 'Terminer', 'get');

    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'attempt.php?id=' . $id . '&difficulty=' . $difficulty . '&submitted=1'));
$PAGE->requires->js_call_amd('mod_hippotrack/attempt', 'init');

foreach ($possible_inputs as $field) {
    $label = ucfirst(str_replace('_', ' ', $field));
    $is_given_input = ($field === $random_input);
    $readonly = $is_given_input ? 'readonly' : '';

    if ($field === 'partogramme' || $field === 'simplified_schematic') {
        echo html_writer::tag('h4', $label);

        $interior_image = ($field === 'partogramme') ? 'partogramme_interior' : 'simplified_schematic_interior';
        $background_image = ($field === 'partogramme') ? 'bassin' : 'null';
        $contour_class = ($field === 'partogramme') ? 'partogramme_contour' : 'simplified_schematic_contour';

        echo '<div class="rotation-container">';
        echo '<div class="container" data-schema-type="' . $field . '">';

        if ($background_image !== 'null') {
            echo '<img class="' . $background_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image . '.png') . '">';
        }

        echo '<img class="' . $contour_class . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $contour_class . '.png') . '">';
        echo '<img class="' . $interior_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $interior_image . '.png') . '">';
        echo '</div>';  // Close .container

        // Rotation & Inclination Sliders
        echo '<label for="rotate-slider">Rotation:</label>';
        echo '<input type="range" class="rotate-slider" name="rotation_' . $field . '" min="0" max="360" value="0"><br>';

        echo '<label for="move-axis-slider">Inclinaison:</label>';
        echo '<input type="range" class="move-axis-slider" name="inclinaison_' . $field . '" min="-50" max="50" value="0"><br>';

        echo '</div>';  // Close .rotation-container
    } elseif ($field === 'vue_anterieure' || $field === 'vue_laterale') {
        echo html_writer::tag('h4', $label);

        $prefix = ($field === 'vue_anterieure') ? 'bb_vue_ante_bf_' : 'bb_vue_lat_bf_';
        $image_path = new moodle_url('/mod/hippotrack/pix/' . $prefix . '1.png');

        // **🆕 Select Background Image Based on $field**
        $background_image = ($field === 'vue_anterieure') ? 'bassin_anterieur.png' : 'bassin_laterale.png';

        echo '<div class="image-cycling-container" data-schema-type="' . $field . '" data-prefix="' . $prefix . '">';

        echo '<div class="container">';
        // 🆕 Dynamically set background image
        echo '<img class="background-image" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image) . '">';
        echo '<img class="cycling-image" src="' . new moodle_url('/mod/hippotrack/pix/' . $prefix . '1.png') . '" data-current="1">';
        echo '</div>';

        echo '<div class="container button-container">';
        echo '<button type="button" class="prev-btn">←</button>';
        echo '<button type="button" class="next-btn">→</button>';
        echo '<button type="button" class="toggle-btn">🔄 Toggle bf/mf</button>'; // Toggle button
        echo '<input type="hidden" class="selected-position" name="rotation_' . $field . '" value="' . $prefix . '_1">';
        echo '</div>';

        echo '</div>';







        // Hidden input to store selected position
        echo '<input type="hidden" name="' . $field . '" class="selected-position" value="1">';
    } else {
        echo html_writer::tag('label', $label, array('for' => $field));
        echo html_writer::empty_tag('input', array(
            'type' => 'text',
            'name' => $field,
            'id' => $field,
            'value' => $is_given_input ? $pre_filled_value : '',
            'required' => true,
            $readonly => $readonly
        ));
        echo "<br>";
    }
}


// 📌 Hidden field to debug missing parameters
echo '<input type="hidden" name="debug_submission" value="1">';

// 📌 Bouton de validation
echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Valider'));

echo html_writer::end_tag('form');


echo $OUTPUT->footer();
