<?php
// Fichier : mod/hippotrack/stats.php

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/hippotrack/classes/stats_manager.php');

global $DB, $PAGE, $OUTPUT, $USER;

// Récupération des paramètres
$cmid = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

// Récupération de l'instance hippotrack et vérification
$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$hippotrack = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
// $course = $DB->get_record('course', ['id' => $hippotrack->course], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('mod/hippotrack:viewstats', $context);

// Configuration de la page
$PAGE->set_url('/mod/hippotrack/stats.php', ['id' => $cmid]);
$PAGE->set_title(get_string('stats', 'mod_hippotrack'));
$PAGE->set_heading($course->fullname);

// Instanciation du gestionnaire de statistiques
$stats_manager = new \mod_hippotrack\stats_manager($DB);

// Récupération des statistiques globales
$globalstats = $stats_manager->get_global_stats($cmid);

// Récupération de la liste des étudiants pour le menu déroulant
$students = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.firstname, u.lastname
     FROM {user} u
     JOIN {hippotrack_session} s ON u.id = s.userid
     WHERE s.id_hippotrack = :hippotrackid",
    ['hippotrackid' => $cmid]
);

function get_user_fullname($user) {
    return $user->firstname .' '. $user->lastname;
}

// Affichage
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('stats', 'mod_hippotrack'));


/* -------------------------------------------------------------------------- */
/*                            STATISTIQUES GLOBALES                           */
/* -------------------------------------------------------------------------- */

// Statistiques globales
echo html_writer::start_tag('div', ['class' => 'global-stats']);
echo html_writer::tag('h3', get_string('globalstats', 'mod_hippotrack'));
echo html_writer::tag('p', 'Nombre total d’étudiants : ' . ($globalstats['total_students'] ?? 0));
echo html_writer::tag('p', 'Taux de réussite : ' . (($globalstats['success_rate']/$globalstats['total_attempts']) ?? 0)*100 . '%');
echo html_writer::end_tag('div');

/* -------------------------------------------------------------------------- */
/*                             DEBUT TABLEAU EXOS                             */
/* -------------------------------------------------------------------------- */
// Récupérer les jeux de données corrects
$correct_datasets = $DB->get_records('hippotrack_datasets', null, '', '*');

// Récupérer les statistiques par difficulté et nom de dataset
$stats_facile = $stats_manager->get_dataset_stats_by_difficulty($hippotrack->id, 'easy');
$stats_difficile = $stats_manager->get_dataset_stats_by_difficulty($hippotrack->id, 'hard');

/* -------------------------------------------------------------------------- */
/*          GRAPHIQUES PAR TYPE D'INCLINAISON (REGROUPÉS PAR DATASET)         */
/* -------------------------------------------------------------------------- */

// Types d'inclinaison
$inclinaison_types = [
    'bien' => 'Bien Fléchi',
    'mal' => 'Mal Fléchi', 
    'peu' => 'Peu Fléchi'
];

// Récupérer tous les noms de dataset présents dans les statistiques
$all_datasets = array_unique(array_merge(array_keys($stats_facile), array_keys($stats_difficile)));

// Pour chaque type d'inclinaison, créer un graphique regroupant tous les datasets
foreach ($inclinaison_types as $inclinaison_key => $inclinaison_name) {
    echo html_writer::tag('h2', "Statistiques - $inclinaison_name");
    
    // Un graphique pour le mode facile
    echo html_writer::tag('h3', "Mode Facile");
    
    // Création du graphique
    $chart_easy = new \core\chart_bar();
    $chart_easy->set_title("Tentatives par position - $inclinaison_name - Mode Facile");
    
    // Données pour les réussites et échecs en mode facile
    $success_data_easy = [];
    $failure_data_easy = [];
    $labels_easy = [];
    
    foreach ($all_datasets as $dataset_name) {
        // Vérifier si des données existent pour ce dataset et ce type d'inclinaison
        if (isset($stats_facile[$dataset_name][$inclinaison_key]) && 
            $stats_facile[$dataset_name][$inclinaison_key]['total_attempts'] > 0) {
            
            $total = $stats_facile[$dataset_name][$inclinaison_key]['total_attempts'];
            $correct = $stats_facile[$dataset_name][$inclinaison_key]['correct_attempts'];
            $incorrect = $total - $correct;
            
            $success_data_easy[] = $correct;
            $failure_data_easy[] = $incorrect;
            $labels_easy[] = $dataset_name;
        }
    }
    
    // N'afficher le graphique que si des données existent
    if (!empty($labels_easy)) {
        // Création des séries
        $success_series_easy = new \core\chart_series('Réussites', $success_data_easy);
        $failure_series_easy = new \core\chart_series('Échecs', $failure_data_easy);
        
        // Mise en forme
        $success_series_easy->set_color('#28a745'); // Vert pour les réussites
        $failure_series_easy->set_color('#dc3545'); // Rouge pour les échecs
        
        // Ajout des séries au graphique
        $chart_easy->add_series($success_series_easy);
        $chart_easy->add_series($failure_series_easy);
        $chart_easy->set_labels($labels_easy);
        $chart_easy->set_stacked(true);
        
        // Affichage du graphique
        echo $OUTPUT->render($chart_easy);
        
        // Affichage des taux de réussite
        echo html_writer::start_tag('div', ['class' => 'success-rates']);
        foreach ($labels_easy as $index => $dataset_name) {
            $total = $success_data_easy[$index] + $failure_data_easy[$index];
            $rate = $total > 0 ? round(($success_data_easy[$index] / $total) * 100, 2) : 0;
            
            echo html_writer::tag('p', "$dataset_name: $rate% ({$success_data_easy[$index]}/$total)");
        }
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::tag('p', "Aucune donnée disponible pour ce type d'inclinaison en mode facile.");
    }
    
    // Un graphique pour le mode difficile
    echo html_writer::tag('h3', "Mode Difficile");
    
    // Création du graphique
    $chart_hard = new \core\chart_bar();
    $chart_hard->set_title("Tentatives par position - $inclinaison_name - Mode Difficile");
    
    // Données pour les réussites et échecs en mode difficile
    $success_data_hard = [];
    $failure_data_hard = [];
    $labels_hard = [];
    
    foreach ($all_datasets as $dataset_name) {
        // Vérifier si des données existent pour ce dataset et ce type d'inclinaison
        if (isset($stats_difficile[$dataset_name][$inclinaison_key]) && 
            $stats_difficile[$dataset_name][$inclinaison_key]['total_attempts'] > 0) {
            
            $total = $stats_difficile[$dataset_name][$inclinaison_key]['total_attempts'];
            $correct = $stats_difficile[$dataset_name][$inclinaison_key]['correct_attempts'];
            $incorrect = $total - $correct;
            
            $success_data_hard[] = $correct;
            $failure_data_hard[] = $incorrect;
            $labels_hard[] = $dataset_name;
        }
    }
    
    // N'afficher le graphique que si des données existent
    if (!empty($labels_hard)) {
        // Création des séries
        $success_series_hard = new \core\chart_series('Réussites', $success_data_hard);
        $failure_series_hard = new \core\chart_series('Échecs', $failure_data_hard);
        
        // Mise en forme
        $success_series_hard->set_color('#28a745'); // Vert pour les réussites
        $failure_series_hard->set_color('#dc3545'); // Rouge pour les échecs
        
        // Ajout des séries au graphique
        $chart_hard->add_series($success_series_hard);
        $chart_hard->add_series($failure_series_hard);
        $chart_hard->set_labels($labels_hard);
        $chart_hard->set_stacked(true);
        
        // Affichage du graphique
        echo $OUTPUT->render($chart_hard);
        
        // Affichage des taux de réussite
        echo html_writer::start_tag('div', ['class' => 'success-rates']);
        foreach ($labels_hard as $index => $dataset_name) {
            $total = $success_data_hard[$index] + $failure_data_hard[$index];
            $rate = $total > 0 ? round(($success_data_hard[$index] / $total) * 100, 2) : 0;
            
            echo html_writer::tag('p', "$dataset_name: $rate% ({$success_data_hard[$index]}/$total)");
        }
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::tag('p', "Aucune donnée disponible pour ce type d'inclinaison en mode difficile.");
    }
    
    /* -------------------------------------------------------------------------- */
    /*                    GRAPHIQUE COMPARATIF DES TAUX DE RÉUSSITE               */
    /* -------------------------------------------------------------------------- */
    
    echo html_writer::tag('h3', "Comparaison des taux de réussite - $inclinaison_name");
    
    // Collecter les datasets qui ont des données dans les deux modes de difficulté
    $common_datasets = [];
    $rates_facile = [];
    $rates_difficile = [];
    
    foreach ($all_datasets as $dataset_name) {
        $has_easy_data = isset($stats_facile[$dataset_name][$inclinaison_key]) && 
                         $stats_facile[$dataset_name][$inclinaison_key]['total_attempts'] > 0;
        
        $has_hard_data = isset($stats_difficile[$dataset_name][$inclinaison_key]) && 
                         $stats_difficile[$dataset_name][$inclinaison_key]['total_attempts'] > 0;
        
        if ($has_easy_data || $has_hard_data) {
            $common_datasets[] = $dataset_name;
            
            $rate_facile = $has_easy_data ? 
                round($stats_facile[$dataset_name][$inclinaison_key]['success_rate'], 2) : 0;
            
            $rate_difficile = $has_hard_data ? 
                round($stats_difficile[$dataset_name][$inclinaison_key]['success_rate'], 2) : 0;
            
            $rates_facile[] = $rate_facile;
            $rates_difficile[] = $rate_difficile;
        }
    }
    
    // N'afficher le graphique que si des données existent
    if (!empty($common_datasets)) {
        // Création du graphique
        $chart_compare = new \core\chart_bar();
        $chart_compare->set_title("Taux de réussite par position - $inclinaison_name");
        
        // Création des séries
        $series_facile = new \core\chart_series('Mode Facile (%)', $rates_facile);
        $series_difficile = new \core\chart_series('Mode Difficile (%)', $rates_difficile);
        
        // Mise en forme
        $series_facile->set_color('#4285f4'); // Bleu pour mode facile
        $series_difficile->set_color('#ea4335'); // Rouge pour mode difficile
        
        // Ajout des séries au graphique
        $chart_compare->add_series($series_facile);
        $chart_compare->add_series($series_difficile);
        $chart_compare->set_labels($common_datasets);
        // Create the Y axis
        // Création d'un objet chart_axis pour l'axe Y
        $yaxis = new \core\chart_axis();
        $yaxis->set_min(0);  // Valeur minimale
        $yaxis->set_max(100);  // Valeur maximale
        $yaxis->set_label('Taux de réussite (%)');  // Label de l'axe

        // Configuration de l'axe Y sur le graphique
        $chart_compare->set_yaxis($yaxis);
        // $chart_compare->set_y_axis_max(100); // ! Échelle de 0 à 100%
        
        // Affichage du graphique
        echo $OUTPUT->render($chart_compare);
    } else {
        echo html_writer::tag('p', "Aucune donnée de comparaison disponible pour ce type d'inclinaison.");
    }
    
    // Séparateur entre les types d'inclinaison
    echo html_writer::empty_tag('hr', ['style' => 'margin: 40px 0; border-top: 2px solid #333;']);
}

/* -------------------------------------------------------------------------- */
/*          TABLEAU RÉCAPITULATIF DE TOUS LES TAUX DE RÉUSSITE                */
/* -------------------------------------------------------------------------- */

echo html_writer::tag('h2', "Tableau récapitulatif des taux de réussite");

// Création du tableau
$table = new html_table();
$table->head = array('Position', 'Bien Fléchi Facile', 'Bien Fléchi Difficile', 'Mal Fléchi Facile', 'Mal Fléchi Difficile', 'Peu Fléchi Facile', 'Peu Fléchi Difficile');
$table->data = array();

foreach ($all_datasets as $dataset_name) {
    $row = array($dataset_name);
    
    foreach (['bien', 'mal', 'peu'] as $inclinaison_key) {
        // Mode facile
        if (isset($stats_facile[$dataset_name][$inclinaison_key]) && 
            $stats_facile[$dataset_name][$inclinaison_key]['total_attempts'] > 0) {
            
            $rate = round($stats_facile[$dataset_name][$inclinaison_key]['success_rate'], 2);
            $correct = $stats_facile[$dataset_name][$inclinaison_key]['correct_attempts'];
            $total = $stats_facile[$dataset_name][$inclinaison_key]['total_attempts'];
            
            $row[] = "$rate% ($correct/$total)";
        } else {
            $row[] = '-';
        }
        
        // Mode difficile
        if (isset($stats_difficile[$dataset_name][$inclinaison_key]) && 
            $stats_difficile[$dataset_name][$inclinaison_key]['total_attempts'] > 0) {
            
            $rate = round($stats_difficile[$dataset_name][$inclinaison_key]['success_rate'], 2);
            $correct = $stats_difficile[$dataset_name][$inclinaison_key]['correct_attempts'];
            $total = $stats_difficile[$dataset_name][$inclinaison_key]['total_attempts'];
            
            $row[] = "$rate% ($correct/$total)";
        } else {
            $row[] = '-';
        }
    }
    
    $table->data[] = $row;
}

echo html_writer::table($table);


/* -------------------------------------------------------------------------- */
/*                 GRAPHIQUE COMPARATIF DES TAUX DE RÉUSSITE                  */
/* -------------------------------------------------------------------------- */

echo html_writer::tag('h2', "Comparaison des taux de réussite par position");

// Créer un graphique comparatif pour chaque type d'inclinaison
$inclinaison_types = [
    'bien' => 'Bien Fléchi',
    'mal' => 'Mal Fléchi', 
    'peu' => 'Peu Fléchi'
];

foreach ($inclinaison_types as $type => $description) {
    echo html_writer::tag('h3', "Type: $description");
    
    $chart = new \core\chart_bar();
    $chart->set_title("Taux de réussite par position - $description");
    
    $datasets_names = [];
    $success_rates_facile = [];
    $success_rates_difficile = [];
    
    foreach ($all_datasets as $dataset_name) {
        $facile_rate = 0;
        $difficile_rate = 0;
        
        if (isset($stats_facile[$dataset_name][$type]) && 
            $stats_facile[$dataset_name][$type]['total_attempts'] > 0) {
            $facile_rate = round($stats_facile[$dataset_name][$type]['success_rate'], 2);
        }
        
        if (isset($stats_difficile[$dataset_name][$type]) && 
            $stats_difficile[$dataset_name][$type]['total_attempts'] > 0) {
            $difficile_rate = round($stats_difficile[$dataset_name][$type]['success_rate'], 2);
        }
        
        // N'ajouter que si des données existent
        if ($facile_rate > 0 || $difficile_rate > 0) {
            $datasets_names[] = $dataset_name;
            $success_rates_facile[] = $facile_rate;
            $success_rates_difficile[] = $difficile_rate;
        }
    }
    
    // N'afficher le graphique que si des données existent
    if (!empty($datasets_names)) {
        // Création des séries
        $series_facile = new \core\chart_series('Mode Facile (%)', $success_rates_facile);
        $series_difficile = new \core\chart_series('Mode Difficile (%)', $success_rates_difficile);
        
        // Mise en forme
        $series_facile->set_color('#4285f4'); // Bleu pour mode facile
        $series_difficile->set_color('#ea4335'); // Rouge pour mode difficile
        
        // Ajout des séries au graphique
        $chart->add_series($series_facile);
        $chart->add_series($series_difficile);
        $chart->set_labels($datasets_names);
        // $chart->set_y_axis_max(100); // Échelle de 0 à 100%
        
        // Affichage du graphique
        echo $OUTPUT->render($chart);
    } else {
        echo html_writer::tag('p', "Aucune donnée disponible pour ce type d'inclinaison.");
    }
    
    // Séparateur
    echo html_writer::empty_tag('hr');
}

/* -------------------------------------------------------------------------- */
/*                             FIN TABLEAU EXOS                               */
/* -------------------------------------------------------------------------- */

/* -------------------------------------------------------------------------- */
/*                       DEBUT TABLEAU ÉTUDIANTS                              */
/* -------------------------------------------------------------------------- */

// Tableau récapitulatif de tous les étudiants avec le nombre d'attempts et leur taux de réussite
echo html_writer::start_tag('table', array('class' => 'table table-striped'));
echo html_writer::start_tag('thead');
echo html_writer::tag('tr',
    html_writer::tag('th', get_string('student', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('attempts', 'mod_hippotrack')) .
    html_writer::tag('th', get_string('successrate', 'mod_hippotrack')) .
    html_writer::tag('th', '')
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($students as $student) {
    // Récupération des statistiques de l'étudiant
    $studentstats = $stats_manager->get_student_stats( $hippotrack->id, $student->id);
    $attempts = $studentstats['total_attempts'] ?? 0;
    
    // Récupération des données de performance pour calculer le taux de réussite
    if ($attempts > 0) {
        $rate = ($studentstats['success_total'] ?? 0) / $attempts * 100;
    } else {
        $rate = 0;
    }

    // Création d'un bouton pour afficher les statistiques complètes de l'étudiant
    $url = new moodle_url('/mod/hippotrack/stats.php', ['id' => $cmid, 'userid' => $student->id]);
    $button = html_writer::link($url, get_string('showstats', 'mod_hippotrack'), ['class' => 'btn btn-primary']);

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', get_user_fullname($student));
    echo html_writer::tag('td', $attempts);
    echo html_writer::tag('td', $rate . '%');
    echo html_writer::tag('td', $button);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

/* -------------------------------------------------------------------------- */
/*                            FIN TABLEAU ÉTUDIANTS                           */
/* -------------------------------------------------------------------------- */

// ! TO BE REMOVED
// Statistiques spécifiques à un étudiant
if ($userid) {
    $studentstats = $stats_manager->get_student_stats($cmid, $userid);
    $performancedata = $stats_manager->get_student_performance_data($userid, $cmid);

    echo html_writer::start_tag('div', ['class' => 'student-stats']);
    echo html_writer::tag('h3', get_string('studentstats', 'mod_hippotrack') . ' : ' . get_user_fullname($students[$userid]));
    echo '<ul>';
    foreach ($studentstats as $session) {
        echo html_writer::tag('li', 'Session #' . $session->id . ' - Note : ' . $session->sumgrades . ' - Questions : ' . $session->questionsdone);
    }
    echo '</ul>';

    // Préparation des données pour le graphique
    $labels = [];
    $success = [];
    foreach ($performancedata as $attempt) {
        $labels[] = $attempt->attempt_number;
        $success[] = $attempt->is_correct;
    }

    // Graphique avec Chart.js
    echo '<canvas id="performanceChart" width="400" height="200"></canvas>';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script>
        var ctx = document.getElementById("performanceChart").getContext("2d");
        new Chart(ctx, {
            type: "line",
            data: {
                labels: ' . json_encode($labels) . ',
                datasets: [{
                    label: "' . get_string('success', 'mod_hippotrack') . '",
                    data: ' . json_encode($success) . ',
                    borderColor: "rgba(75, 192, 192, 1)",
                    fill: false
                }]
            },
            options: {
                scales: { y: { beginAtZero: true, max: 1 } }
            }
        });
    </script>';
    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();