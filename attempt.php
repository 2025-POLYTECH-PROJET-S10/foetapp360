<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/sessionlib.php');

$cmid = required_param('id', PARAM_INT);
$session_id = required_param('session_id', PARAM_INT);
$difficulty = optional_param('difficulty', '', PARAM_ALPHA);
$new_question = optional_param('new_question', 0, PARAM_INT);
$submitted = optional_param('submitted', 0, PARAM_INT);
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
$PAGE->requires->css("/mod/hippotrack/styles.css");

echo $OUTPUT->header();

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

    // Incrémente le nombre de question
    if (!isset($_SESSION['hippotrack_session_' . $session_id . '_sumgrades'])) {
        // Si la variable n'existe pas, on l'initialise à 1
        $_SESSION['hippotrack_session_' . $session_id . '_sumgrades'] = 1;
    } else {
        if($is_correct){
            $_SESSION['hippotrack_session_' . $session_id . '_sumgrades']++;
        }
    }

    // 📌 Enregistrer les réponses de l'étudiant dans la base de données
    // Vérifier si une tentative existe déjà, sinon l'initialiser
    if (!isset($_SESSION['hippotrack_session_' . $session_id])) {
        $_SESSION['hippotrack_session_' . $session_id] = [];
    }

    // Créer une nouvelle entrée avec les valeurs de base
    $new_attempt = [
        'id_dataset' => $dataset->id
    ];

    // Ajouter l’entrée à la session
    $_SESSION['hippotrack_session_' . $session_id][] = $new_attempt;

    // Récupérer l'index de la dernière entrée ajoutée
    $last_index = count($_SESSION['hippotrack_session_' . $session_id]) - 1;

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
        $_SESSION['hippotrack_session_' . $session_id][$last_index][$field] = $student_answer;
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
    $random_input = $possible_inputs[array_rand($possible_inputs)];
    $random_input_label = ucfirst(str_replace('_', ' ', $random_input));

    // Enregistre la difficulté dans la session.
    if (!isset($_SESSION['hippotrack_session_' . $session_id . '_difficulty'])) {
        $_SESSION['hippotrack_session_' . $session_id . '_difficulty'] = $difficulty;
    }

    // Incrémente le nombre de question
    if (!isset($_SESSION['hippotrack_session_' . $session_id . '_questionsdone'])) {
        // Si la variable n'existe pas, on l'initialise à 1
        $_SESSION['hippotrack_session_' . $session_id . '_questionsdone'] = 1;
    } else {
        // Si la variable existe, on l'incrémente de 1
        $_SESSION['hippotrack_session_' . $session_id . '_questionsdone']++;
    }

    echo html_writer::tag('h3', "Trouvez les bonnes correspondances pour :");

    // 📌 Affichage du formulaire d'exercice
    echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'attempt.php'));

    // Ajouter des inputs cachés pour envoyer les variables via POST
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cmid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'session_id', 'value' => $session_id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'difficulty', 'value' => $difficulty));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'input', 'value' => $random_input));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'dataset_id', 'value' => $random_dataset->id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'submitted', 'value' => '1'));

    foreach ($possible_inputs as $field) {
        $label = ucfirst(str_replace('_', ' ', $field));
        $is_given_input = ($field === $random_input);
        $readonly = $is_given_input ? 'readonly' : '';

        if ($field === 'partogramme' || $field === 'schema_simplifie') {
            echo html_writer::tag('h4', $label);
            echo html_writer::tag('label', "Inclinaison", array('for' => "inclinaison_$field"));
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => "inclinaison_$field", 'id' => "inclinaison_$field", 'value' => $is_given_input ? $random_dataset->inclinaison : '', 'required' => true, $readonly => $readonly));
            echo "<br>";

            echo html_writer::tag('label', "Rotation", array('for' => "rotation_$field"));
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => "rotation_$field", 'id' => "rotation_$field", 'value' => $is_given_input ? $random_dataset->rotation : '', 'required' => true, $readonly => $readonly));
            echo "<br>";
        } else {
            echo html_writer::tag('label', $label, array('for' => $field));
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => $field, 'id' => $field, 'value' => $is_given_input ? $random_dataset->$random_input : '', 'required' => true, $readonly => $readonly));
            echo "<br>";
        }
    }

    // 📌 Bouton de validation
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Valider', 'class' => 'btn btn-primary'));

    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
