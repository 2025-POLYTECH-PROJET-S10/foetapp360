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
require_once(__DIR__ . '/locallib.php');
use mod_hippotrack\image_manager;

$cmid = required_param('id', PARAM_INT);
$session_id = required_param('session_id', PARAM_INT);
$difficulty = optional_param('difficulty', '', PARAM_ALPHA);
$new_question = optional_param('new_question', 0, PARAM_INT);
$submitted = optional_param('submitted', 0, PARAM_INT);
$first_time = optional_param('first_time', 0, PARAM_INT);
$userid = $USER->id;
$TOLERANCE = 5;

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
$PAGE->requires->css("/mod/hippotrack/styles.css");

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
    $_SESSION['hippotrack_session_' . $session_id]['_time_start'] = time();
    echo '<div class="foetapp360-info">
        <p><strong>FoetApp360 est un outil interactif conçu pour vous aider à mieux comprendre les positions variétés de présentations fœtales</strong>. En vous entraînant ici, vous pourrez suivre <strong>vos statistiques personnelles</strong> pour identifier vos points forts et les notions à améliorer et recevrez des feedbacks après chaque exercice.</p>

        <p>Vous pouvez choisir entre <strong>deux modes</strong> d\'entraînement adaptés à votre niveau :</p>

        <ul>
            <li>🔹 <strong>Mode Facile</strong> : Vous devrez, à partir d’un des éléments donné, identifier l’ensemble des représentations d’une variété de présentation en vous aidant des représentations anatomiques les plus complètes.</li>

            <li>🔹 <strong>Mode Difficile</strong> : Vous devrez, à partir d’un des éléments donné, identifier l’ensemble des représentations d’une variété de présentation sans l’aide des représentations anatomiques les plus complètes.</li>
        </ul>

        <p>📌 <strong>Règles générales</strong> :</p>

        <ul>
            <li>· Pour le <strong>nom</strong>, vous <strong>n’êtes pas obligé</strong> de respecter les majuscules ou les tirets.</li>
            <li>· Pour le <strong>partogramme</strong> et le <strong>schéma simplifié</strong>, la <strong>rotation doit être précise</strong>, mais une tolérance de <strong>5°</strong> est appliquée sur les axes perpendiculaires.</li>
        </ul>
      </div>';

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

// GET toutes les vue antérieures
$image_manager_anterieur = new image_manager('vue_anterieure');
$sql = "SELECT vue_anterieure 
        FROM {hippotrack_datasets} 
        ORDER BY inclinaison ASC, rotation ASC";
$vue_anterieur_img_names = $DB->get_records_sql($sql);
$image_database_vue_anterieur = [];
foreach ($vue_anterieur_img_names as $img_name) {
    $filename = $img_name->vue_anterieure; // Récupération du nom de fichier correct
    $image_database_vue_anterieur[$filename] = ($image_manager_anterieur->getImageUrlByName($filename))->out(); // Append à $image_database
}
$nb_vue_anterieur = max(0, count($image_database_vue_anterieur) - 1);

// GET toutes les vue latérales
$image_manager_laterale = new image_manager('vue_laterale');
$sql = "SELECT vue_laterale 
        FROM {hippotrack_datasets} 
        ORDER BY inclinaison ASC, rotation ASC";
$vue_laterale_img_names = $DB->get_records_sql($sql);
$image_database_vue_laterale = [];
foreach ($vue_laterale_img_names as $img_name) {
    $filename = $img_name->vue_laterale; // Récupération du nom de fichier correct
    $image_database_vue_laterale[$filename] = ($image_manager_laterale->getImageUrlByName($filename))->out(); // Append à $image_database
}
$nb_vue_laterale = max(0, count($image_database_vue_laterale) - 1);

$image_database = [
    "vue_anterieure" => $image_database_vue_anterieur,
    "vue_laterale" => $image_database_vue_laterale
];

$PAGE->requires->js_call_amd('mod_hippotrack/attempt', 'init');

// Champ index pour images.
echo '<input type="hidden" name="max_vue_anterieur" data-values="' . $nb_vue_anterieur . '">';
echo '<input type="hidden" name="max_vue_laterale" data-values="' . $nb_vue_laterale . '">';
echo '<div id="image_database" data-values="' . htmlspecialchars(json_encode($image_database), ENT_QUOTES, 'UTF-8') . '"></div>';

// 📌 Correction après validation
if ($submitted) {
    echo html_writer::tag('h3', "Revue de l'exercice :");
    $is_correct = true;
    $dataset = $DB->get_record_sql("SELECT * FROM {hippotrack_datasets} WHERE id = :dataset_id", array('dataset_id' => $_POST['dataset_id']));

    // List Selector
    echo '<div class="hippotrack-tabs">';
    echo '<ul class="hippotrack-tab-list">';
    $is_first = true;
    foreach ($possible_inputs as $field) {
        echo '<li class="hippotrack-tab ' . ($is_first ? 'active' : '') . '" data-target="#' . $field . '_container">' . $field . '</li>';
        $is_first = false;
    }
    echo '</ul">';
    echo '</div>';

    foreach ($possible_inputs as $field) {
        $is_current_correct = true;
        $label = ucfirst(str_replace('_', ' ', $field));
        if ($field === 'partogramme' || $field === 'schema_simplifie') {
            // 🔥 Correction spéciale pour partogramme et schéma simplifié (ils utilisent rotation + inclinaison)
            $student_inclinaison_raw = required_param("inclinaison_$field", PARAM_RAW);
            $student_inclinaison = get_correct_inclinaison($student_inclinaison_raw);
            $student_rotation = required_param("rotation_$field", PARAM_RAW);

            $input_dataset = get_dataset_from_inclinaison_rotation($student_inclinaison, $student_rotation);
            if ($student_inclinaison != $dataset->inclinaison || get_correct_rotation($student_rotation) != $dataset->rotation) {
                $is_current_correct = false;
                $is_correct = false;
            }

            if ($input_dataset == null) {
                $input_dataset_name = get_dataset_name_from_inclinaison_rotation($student_inclinaison, $student_rotation);
            } else {
                $input_dataset_name = $input_dataset->name;
            }

            // Feedback
            $feedback = $DB->get_record_sql(
                "SELECT * FROM {hippotrack_feedback} 
                WHERE input_dataset = :input_dataset 
                AND expected_dataset = :expected_dataset
                AND input_inclinaison = :input_inclinaison
                AND expected_inclinaison = :expected_inclinaison",
                array(
                    'input_dataset' => $input_dataset_name,
                    'expected_dataset' => $dataset->name,
                    'input_inclinaison' => $student_inclinaison,
                    'expected_inclinaison' => $dataset->inclinaison
                )
            );
            $feedback_data = $DB->get_record_sql("SELECT * FROM {hippotrack_feedback_data} WHERE id = :id", array('id' => $feedback->id_feedback));

            // Affichage
            $interior_image = ($field === 'partogramme') ? 'partogramme_interieur' : 'schema_simplifie_interieur';
            $background_image = ($field === 'partogramme') ? 'null' : 'bassin';
            $contour_class = ($field === 'partogramme') ? 'partogramme_contour' : 'schema_simplifie_contour';

            echo '<div class="rotation_hippotrack_container attempt_container" id="' . $field . '_container">';
            echo html_writer::tag('h4', $label);
            echo html_writer::tag('p', ($is_current_correct ? ' La réponse est correcte. ✅' : ' La réponse est incorrecte. ❌'));
            echo html_writer::tag('p', $feedback_data->feedback);
            echo '<div class="hippotrack_container" data-schema-type="' . $field . '">';

            if ($background_image !== 'null') {
                echo '<img class="' . $background_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image . '.png') . '">';
            }

            echo '<img class="' . $contour_class . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $contour_class . '.png') . '">';
            echo '<img class="' . $interior_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $interior_image . '.png') . '">';
            echo '</div>';  // Close .hippotrack_container

            // Rotation & Inclination Sliders
            echo '<div class="hippotrack_sliders">';
            // Si bloqué, on ajoute un input hidden pour transmettre l'information
            echo '<input type="range" class="rotate-slider" name="rotation_' . $field . '" min="0" max="360" 
                value="' . $student_rotation . '"style="display: none;"><br>';

            echo '<input type="range" class="move-axis-slider" name="inclinaison_' . $field . '" min="-50" max="50" 
                value="' . $student_inclinaison_raw . '"style="display: none;"><br>';
            echo '</div>';

            echo '</div>';  // Close .rotation-hippotrack_container
        } else {
            // 🔥 Cas normal (name, sigle, vue_anterieure, vue_laterale)
            $student_answer = required_param($field, PARAM_RAW);
            $correct_answer = $dataset->$field;
            if (format_answer_string($student_answer) != format_answer_string($correct_answer)) {
                $is_current_correct = false;
                $is_correct = false;
            }
            if ($field === 'vue_anterieure' || $field === 'vue_laterale') {
                $elements = explode("/", $student_answer);
                $student_answer = end($elements);
                $prefix = ($field === 'vue_anterieure') ? 'bb_vue_ante_bf_' : 'bb_vue_lat_bf_';
                $image_path = $image_database[$field][$student_answer];

                // **🆕 Select Background Image Based on $field**
                $background_image = ($field === 'vue_anterieure') ? 'bassin_anterieur.png' : 'bassin_laterale.png';

                echo '<div class="image_cycling_hippotrack_container attempt_container" data-schema-type="' . $field . '" data-prefix="' . $prefix . '" id="' . $field . '_container">';
                echo html_writer::tag('h4', $label);
                echo html_writer::tag('p', ($is_current_correct ? ' La réponse est correcte. ✅' : ' La réponse est incorrecte. ❌'));
                echo '<div class="hippotrack_container">';
                // 🆕 Dynamically set background image
                echo '<img class="hippotrack_background-image_' . $field . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image) . '">';
                echo '<img class="hippotrack_attempt_cycling-image_' . $field . '" src="' . $image_path . '">';
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="attempt_container attempt_form_group" id="' . $field . '_container">';
                echo html_writer::tag('label', $label . ' - ' . (($dataset->inclinaison == 1) ? "Bien fléchis" : "Mal fléchis"), array('for' => $field));
                echo html_writer::tag('p', ($is_current_correct ? ' La réponse est correcte. ✅' : ' La réponse est incorrecte. ❌'));
                echo html_writer::empty_tag('input', array(
                    'type' => 'text',
                    'name' => $field,
                    'id' => $field,
                    'value' => $student_answer,
                    'readonly' => 'readonly'
                ));
                // Si readonly, ajouter un texte supplémentaire visible
                echo '</div>';
            }
        }
    }
    echo html_writer::tag('p', $is_correct ? 'Exercice Correct ✅' : 'Exercice Incorrect ❌');

    // 📌 Enregistrer les réponses de l'étudiant dans la base de données
    // Vérifier si une tentative existe déjà, sinon l'initialiser
    if (!isset($_SESSION['hippotrack_session_' . $session_id])) {
        $_SESSION['hippotrack_session_' . $session_id] = [];
    }

    // Incrémente le nombre de question
    if (!isset($_SESSION['hippotrack_session_' . $session_id]['_sumgrades'])) {
        // Si la variable n'existe pas, on l'initialise à 1
        if ($is_correct) {
            $_SESSION['hippotrack_session_' . $session_id]['_sumgrades'] = 1;
        } else {
            $_SESSION['hippotrack_session_' . $session_id]['_sumgrades'] = 0;
        }
    } else {
        if ($is_correct) {
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
    $_SESSION['hippotrack_session_' . $session_id]['attempts'][$last_index]['given_input'] = $_POST['input'];
    $_SESSION['hippotrack_session_' . $session_id]['attempts'][$last_index]['is_correct'] = (int) $is_correct;

    // Sauvegarde la réponse actuelle
    $student_data = array_filter($_POST, function ($key) {
        return preg_match('/^(inclinaison|rotation)_(\d+)$/', $key);
    }, ARRAY_FILTER_USE_KEY);

    // 📌 Boutons "Nouvelle Question" et "Terminer"
    $new_question_url = new moodle_url('/mod/hippotrack/attempt.php', array('id' => $cmid, 'session_id' => $session_id, 'difficulty' => $difficulty, 'new_question' => 1));
    $finish_url = new moodle_url('/mod/hippotrack/validate.php', array('id' => $cmid, 'session_id' => $session_id));

    echo $OUTPUT->single_button($new_question_url, 'Nouvelle Question', 'get');
    echo $OUTPUT->single_button($finish_url, 'Terminer', 'get');

    echo $OUTPUT->footer();
    exit;
} else {
    $random_dataset = $DB->get_record_sql("SELECT * FROM {hippotrack_datasets} ORDER BY RAND() LIMIT 1"); // TODO A regarder pk random un peu bizarre
    $random_input = $possible_inputs[array_rand($possible_inputs)]; // get random input from dataset

    // Enregistre la difficulté dans la session.
    if (!isset($_SESSION['hippotrack_session_' . $session_id]['_difficulty'])) {
        $_SESSION['hippotrack_session_' . $session_id]['_difficulty'] = $difficulty;
    }

    echo html_writer::tag('h3', "Trouvez les bonnes correspondances pour :");

    echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'attempt.php', 'id' => 'attempt_form'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cmid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'session_id', 'value' => $session_id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'difficulty', 'value' => $difficulty));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'input', 'value' => $random_input));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'dataset_id', 'value' => $random_dataset->id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'submitted', 'value' => '1'));

    // List Selector
    echo '<div class="hippotrack-tabs">';
    echo '<ul class="hippotrack-tab-list">';
    foreach ($possible_inputs as $field) {
        echo '<li class="hippotrack-tab ' . ($random_input === $field ? 'active' : '') . '" data-target="#' . $field . '_container">' . $field . '</li>';
    }
    echo '</ul">';
    echo '</div>';
    echo '<div class="hippotrack-license-notice">
    <img src="' . new moodle_url('/mod/hippotrack/pix/licence-cc-by-nc.png') . '" alt="CC BY-NC License">
    <br>
    FoetApp360\'s images © 2024 by Pierre-Yves Rabattu is licensed under CC BY-NC 4.0. 
    To view a copy of this license, visit 
    <a href="https://creativecommons.org/licenses/by-nc/4.0/" target="_blank">here</a>.
   </div>';

    foreach ($possible_inputs as $field) {
        $label = ucfirst(str_replace('_', ' ', $field));
        $is_given_input = ($field === $random_input);
        $readonly = $is_given_input ? 'readonly' : '';

        if ($field === 'partogramme' || $field === 'schema_simplifie') {

            $interior_image = ($field === 'partogramme') ? 'partogramme_interieur' : 'schema_simplifie_interieur';
            $background_image = ($field === 'partogramme') ? 'null' : 'bassin';
            $contour_class = ($field === 'partogramme') ? 'partogramme_contour' : 'schema_simplifie_contour';

            echo '<div class="rotation_hippotrack_container attempt_container" id="' . $field . '_container">';
            echo html_writer::tag('h4', $label);
            echo '<div class="hippotrack_container" data-schema-type="' . $field . '">';

            if ($background_image !== 'null') {
                echo '<img class="' . $background_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image . '.png') . '">';
            }

            echo '<img class="' . $contour_class . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $contour_class . '.png') . '">';
            echo '<img class="' . $interior_image . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $interior_image . '.png') . '">';
            echo '</div>';  // Close .hippotrack_container

            // Rotation & Inclination Sliders
            echo '<div class="hippotrack_sliders">';
            if (!$is_given_input) {
                echo '<label for="rotate-slider">Rotation:</label>';
            }
            // Si bloqué, on ajoute un input hidden pour transmettre l'information
            echo '<input type="range" class="rotate-slider" name="rotation_' . $field . '" min="0" max="360" 
                value="' . ($is_given_input ? $random_dataset->rotation : 0) . '" ' . ($is_given_input ? 'style="display: none;"' : '') . '><br>';

            if (!$is_given_input) {
                echo '<label for="move-axis-slider">Inclinaison:</label>';
            }
            echo '<input type="range" class="move-axis-slider" name="inclinaison_' . $field . '" min="-50" max="50" 
                value="' . ($is_given_input ? $random_dataset->inclinaison * 50 : 0) . '" ' . ($is_given_input ? 'style="display: none;"' : '') . '><br>';

            // Mandory. If not, will not send values with POST.
            if ($is_given_input) {
                echo '<input type="hidden" name="rotation_' . $field . '" value="' . ($is_given_input ? $random_dataset->rotation : 0) . '">';
                echo '<input type="hidden" name="inclinaison_' . $field . '" value="' . ($random_dataset->inclinaison * 50) . '">';
            }
            echo '</div>';

            echo '</div>';  // Close .rotation-hippotrack_container
        } elseif ($field === 'vue_anterieure' || $field === 'vue_laterale') {

            $prefix = ($field === 'vue_anterieure') ? 'bb_vue_ante_bf_' : 'bb_vue_lat_bf_';
            $image_path = ($is_given_input ? ($image_database[$field][$random_dataset->$random_input]) : (array_values($image_database[$field])[0]));

            // **🆕 Select Background Image Based on $field**
            $background_image = ($field === 'vue_anterieure') ? 'bassin_anterieur.png' : 'bassin_laterale.png';

            echo '<div class="image_cycling_hippotrack_container attempt_container" data-schema-type="' . $field . '" data-prefix="' . $prefix . '" id="' . $field . '_container">';
            echo '<input type="hidden" class="hippotrack_field" data-values="' . $field . '">';
            echo html_writer::tag('h4', $label);

            echo '<div class="hippotrack_container">';
            // 🆕 Dynamically set background image
            echo '<img class="hippotrack_background-image_' . $field . '" src="' . new moodle_url('/mod/hippotrack/pix/' . $background_image) . '">';
            echo '<img class="hippotrack_attempt_cycling-image_' . $field . '" src="' . $image_path . '">';
            echo '</div>';


            echo '<div class="hippotrack_container button-hippotrack_container">';
            if (!$is_given_input) {
                echo '<button type="button" class="hippotrack_attempt_prev-btn">←</button>';
                echo '<button type="button" class="hippotrack_attempt_next-btn">→</button>';
                echo '<button type="button" class="hippotrack_attempt_toggle_btn">🔄 Toggle bf/mf</button>'; // Toggle button
            }
            echo '<input type="hidden" class="hippotrack_attempt_selected_position" name="' . $field . '" value="' . $image_path . '">';
            echo '</div>';

            echo '</div>';
        } else {
            echo '<div class="attempt_container attempt_form_group" id="' . $field . '_container">';
            if ($is_given_input) {
                echo html_writer::tag('label', $label . ' - ' . (($random_dataset->inclinaison == 1) ? "Bien fléchis" : "Mal fléchis"), array('for' => $field));
            } else {
                echo html_writer::tag('label', $label, array('for' => $field));
            }
            echo html_writer::empty_tag('input', array(
                'type' => 'text',
                'name' => $field,
                'id' => $field,
                'value' => $is_given_input ? $random_dataset->$random_input : '',
                'required' => true,
                'readonly' => $is_given_input ? 'readonly' : null // Ajoute readonly si $is_given_input est vrai
            ));
            // Si readonly, ajouter un texte supplémentaire visible
            echo '</div>';
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
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Terminer la session', 'id' => 'submit_attempt'));

    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
