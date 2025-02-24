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

$cmid = required_param('id', PARAM_INT);
$session_id = required_param('session_id', PARAM_INT);
$difficulty = optional_param('difficulty', '', PARAM_ALPHA);
$new_question = optional_param('new_question', 0, PARAM_INT);
$submitted = optional_param('submitted', 0, PARAM_INT);
$first_time = optional_param('first_time', 0, PARAM_INT);
$userid = $USER->id;

$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/hippotrack:attempt', $context);

$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/attempt.php', array('id' => $cmid));
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

if (empty($difficulty)) {
    $_SESSION['hippotrack_session_' . $session_id]['_time_start'] = time();
}

// 📌 Étape 1 : Sélection de la difficulté
if (empty($difficulty)) {
    echo html_writer::tag('h3', "Choisissez votre niveau de difficulté");

    $easy_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $cmid, 'session_id' => $session_id, 'difficulty' => 'easy'));
    $hard_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $cmid, 'session_id' => $session_id, 'difficulty' => 'hard'));

    echo html_writer::start_div('difficulty-selection');
    echo $OUTPUT->single_button($easy_url, 'Facile', 'get');
    echo $OUTPUT->single_button($hard_url, 'Difficile', 'get');
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

$possible_inputs = ($difficulty === 'easy') ? 
['name', 'sigle', 'partogramme', 'schema_simplifie', 'vue_anterieure', 'vue_laterale'] : 
['name', 'sigle', 'partogramme', 'schema_simplifie'];

// 📌 Correction après validation
if ($submitted) {
    echo html_writer::tag('h3', "Correction :");
    $is_correct = true;
    $feedback = "Bravo ! Toutes les réponses sont correctes.";
    $dataset = $DB->get_record_sql("SELECT * FROM {hippotrack_datasets} WHERE id = :dataset_id", array('dataset_id' => $_POST['dataset_id']));

    foreach ($possible_inputs as $field) {
        if ($field === 'partogramme' || $field === 'schema_simplifie') {
            // 🔥 Correction spéciale pour partogramme et schéma simplifié (ils utilisent rotation + inclinaison)
            $student_inclinaison = required_param("inclinaison_$field", PARAM_RAW);
            $student_rotation = required_param("rotation_$field", PARAM_RAW);
            
            $correct_inclinaison = $dataset->inclinaison;
            $correct_rotation = $dataset->rotation;
        
            // Vérification des deux valeurs ensemble
            if ($student_inclinaison != $correct_inclinaison || $student_rotation != $correct_rotation) {
                $is_correct = false;
                $feedback = "Oops, certaines réponses sont incorrectes. Vérifiez et essayez encore !";
            }

            echo html_writer::tag('p', "<strong>$field :</strong> Votre inclinaison : $student_inclinaison | Rotation : $student_rotation <br> Réponse correcte : Inclinaison $correct_inclinaison | Rotation $correct_rotation");
        } else {
            // 🔥 Cas normal (name, sigle, vue_anterieure, vue_laterale)
            $student_answer = required_param($field, PARAM_RAW);
            $correct_answer = $dataset->$field;
        
            if ($student_answer != $correct_answer) {
                $is_correct = false;
                $feedback = "Oops, certaines réponses sont incorrectes. Vérifiez et essayez encore !";
            }

            echo html_writer::tag('p', "<strong>$field :</strong> Votre réponse : $student_answer | Réponse correcte : $correct_answer");
        }
    }
    echo html_writer::tag('p', $feedback, array('class' => $is_correct ? 'correct' : 'incorrect'));

    // 📌 Enregistrer les réponses de l'étudiant dans la base de données
    // Vérifier si une tentative existe déjà, sinon l'initialiser
    if (!isset($_SESSION['hippotrack_session_' . $session_id])) {
        $_SESSION['hippotrack_session_' . $session_id] = [];
    }

    // Incrémente le nombre de question
    if (!isset($_SESSION['hippotrack_session_' . $session_id]['_sumgrades'])) {
        // Si la variable n'existe pas, on l'initialise à 1
        if($is_correct){
            $_SESSION['hippotrack_session_' . $session_id]['_sumgrades'] = 1;
        }
        else{
            $_SESSION['hippotrack_session_' . $session_id]['_sumgrades'] = 0;
        }
    } else {
        if($is_correct){
            $_SESSION['hippotrack_session_' . $session_id]['_sumgrades']++;
        }
    }

    // Créer une nouvelle entrée avec les valeurs de base
    $new_attempt = [
        'id_dataset' => $dataset->id
    ];

    // Ajouter l’entrée à la session
    $_SESSION['hippotrack_session_' . $session_id]['attempts'][] = $new_attempt;

    // Récupérer l'index de la dernière entrée ajoutée
    $last_index = count($_SESSION['hippotrack_session_' . $session_id]['attempts']) - 1;

    // Compléter l'entrée avec les réponses de l'utilisateur
    foreach ($possible_inputs as $field) {
        if ($field === 'partogramme' || $field === 'schema_simplifie') {
            // 🔥 Cas spécial : Stocker la rotation et l'inclinaison pour partogramme et schéma
            $student_inclinaison = required_param("inclinaison_$field", PARAM_RAW);
            $student_rotation = required_param("rotation_$field", PARAM_RAW);
            $student_answer = "Inclinaison: $student_inclinaison, Rotation: $student_rotation";
        } else {
            $student_answer = required_param($field, PARAM_RAW); // Récupère la réponse
        }
        // Ajouter la réponse de l'utilisateur au dernier enregistrement
        $_SESSION['hippotrack_session_' . $session_id]['attempts'][$last_index][$field] = $student_answer;
    }

    // Sauvegarde la réponse actuelle
    $student_data = array_filter($_POST, function ($key) {
        return preg_match('/^(inclinaison|rotation)_(\d+)$/', $key);
    }, ARRAY_FILTER_USE_KEY);
    
    print_r($student_data);

    // 📌 Boutons "Nouvelle Question" et "Terminer"
    $new_question_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $cmid, 'session_id' => $session_id, 'difficulty' => $difficulty, 'new_question' => 1));
    $finish_url = new moodle_url('/mod/hippotrack/validate.php', array('id' => $cmid, 'session_id' => $session_id));

    echo $OUTPUT->single_button($new_question_url, 'Nouvelle Question', 'get');
    echo $OUTPUT->single_button($finish_url, 'Terminer', 'get');

    echo $OUTPUT->footer();
    exit;
} 
else {
    $random_dataset = $DB->get_record_sql("SELECT * FROM {hippotrack_datasets} ORDER BY RAND() LIMIT 1"); // TODO A regarder pk random un peu bizarre
    $random_input = $possible_inputs[array_rand($possible_inputs)]; // get random input from dataset

    // Enregistre la difficulté dans la session.
    if (!isset($_SESSION['hippotrack_session_' . $session_id]['_difficulty'])) {
        $_SESSION['hippotrack_session_' . $session_id]['_difficulty'] = $difficulty;
    }

    echo html_writer::tag('h3', "Trouvez les bonnes correspondances pour :");

    echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'attempt.php'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cmid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'session_id', 'value' => $session_id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'difficulty', 'value' => $difficulty));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'input', 'value' => $random_input));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'dataset_id', 'value' => $random_dataset->id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'submitted', 'value' => '1'));

    $PAGE->requires->js_call_amd('mod_hippotrack/attempt', 'init');

    foreach ($possible_inputs as $field) {
        $label = ucfirst(str_replace('_', ' ', $field));
        $is_given_input = ($field === $random_input);
        $readonly = $is_given_input ? 'readonly' : '';
    
        if ($field === 'partogramme' || $field === 'schema_simplifie') {
            echo html_writer::tag('h4', $label);
    
            $interior_image = ($field === 'partogramme') ? 'partogramme_interieur' : 'schema_simplifie_interieur';
            $background_image = ($field === 'partogramme') ? 'bassin' : 'null';
            $contour_class = ($field === 'partogramme') ? 'partogramme_contour' : 'schema_simplifie_contour';
    
            echo '<div class="rotation_hippotrack_container">';
            echo '<div class="hippotrack_container" data-schema-type="' . $field . '">';
    
            if ($background_image !== 'null') {
                echo '<img class="' . $background_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image . '.png') . '">';
            }
    
            echo '<img class="' . $contour_class . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $contour_class . '.png') . '">';
            echo '<img class="' . $interior_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $interior_image . '.png') . '">';
            echo '</div>';  // Close .hippotrack_container
    
            // Rotation & Inclination Sliders
            echo '<div class="hippotrack_sliders">';
            echo '<label for="rotate-slider">Rotation:</label>';
            echo '<input type="range" class="rotate-slider" name="rotation_' . $field . '" min="0" max="360" value="' . ($is_given_input ? $random_dataset->inclinaison : 0) . '"><br>';
    
            echo '<label for="move-axis-slider">Inclinaison:</label>';
            echo '<input type="range" class="move-axis-slider" name="inclinaison_' . $field . '" min="-50" max="50" value="' . ($is_given_input ? ($random_dataset->inclinaison * 50) : 0) . '"><br>';
            echo '</div>';

            echo '</div>';  // Close .rotation-hippotrack_container
        } elseif ($field === 'vue_anterieure' || $field === 'vue_laterale') {
            echo html_writer::tag('h4', $label);
    
            $prefix = ($field === 'vue_anterieure') ? 'bb_vue_ante_bf_' : 'bb_vue_lat_bf_';
            $image_path = new moodle_url('/mod/hippotrack/pix/' . ($is_given_input ? ($random_dataset->$random_input) : ($prefix . '1.png')));

            // **🆕 Select Background Image Based on $field**
            $background_image = ($field === 'vue_anterieure') ? 'bassin_anterieur.png' : 'bassin_laterale.png';

            echo '<div class="image_cycling_hippotrack_container" data-schema-type="' . $field . '" data-prefix="' . $prefix . '">';

            echo '<div class="hippotrack_container">';
            // 🆕 Dynamically set background image
            echo '<img class="hippotrack_background-image" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image) . '">';
            echo '<img class="hippotrack_attempt_cycling-image" src="' . $image_path . '">';
            echo '</div>';

            echo '<div class="hippotrack_container button-hippotrack_container">';
            echo '<button type="button" class="hippotrack_attempt_prev-btn">←</button>';
            echo '<button type="button" class="hippotrack_attempt_next-btn">→</button>';
            echo '<button type="button" class="hippotrack_attempt_toggle_btn">🔄 Toggle bf/mf</button>'; // Toggle button
            echo '<input type="hidden" class="hippotrack_attempt_selected_position" name="rotation_' . $field . '" value="' . ($is_given_input ? ($random_dataset->$random_input) : $prefix ) . '">';
            echo '</div>';

            echo '</div>';
        } else {
            echo html_writer::tag('label', $label, array('for' => $field));
            echo html_writer::empty_tag('input', array(
                'type' => 'text',
                'name' => $field,
                'id' => $field,
                'value' => $is_given_input ? $random_dataset->$random_input : '',
                'required' => true,
                $readonly => $readonly
            ));
            echo "<br>";
        }
    }

    // Incrémente le nombre de question
    if (!isset($_SESSION['hippotrack_session_' . $session_id]['_questionsdone'])) {
        // Si la variable n'existe pas, on l'initialise à 1
        $_SESSION['hippotrack_session_' . $session_id]['_questionsdone'] = 1;
    } else {
        // Si la variable existe, on l'incrémente de 1
        $_SESSION['hippotrack_session_' . $session_id]['_questionsdone']++;
    }

    // 📌 Hidden field to debug missing parameters
    echo '<input type="hidden" name="debug_submission" value="1">';

    // 📌 Bouton de validation
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Valider'));
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
