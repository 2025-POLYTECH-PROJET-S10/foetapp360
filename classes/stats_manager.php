<?php
// Fichier : mod/foetapp360/classes/stats_manager.php

namespace mod_foetapp360;

defined('MOODLE_INTERNAL') || die();

use moodle_database;

class stats_manager
{
    private $db;

    /**
     * Constructeur de la classe stats_manager.
     *
     * @param moodle_database $db Instance de la base de données Moodle
     */
    public function __construct(moodle_database $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les statistiques globales pour une instance foetapp360.
     *
     * @param int $foetapp360id ID de l'instance foetapp360
     * @return array Statistiques globales
     */
    public function get_global_stats(int $foetapp360id): array
    {
        $sql = "SELECT COUNT(DISTINCT userid) as total_students,
               AVG(sumgrades) as average_grade,
               AVG(questionsdone) as average_questions,
               AVG(a.is_correct) as success_rate,
               COUNT(a.id) as total_attempts
            FROM {foetapp360_session} s
            JOIN {foetapp360_attempt} a ON s.id = a.id_session
            WHERE s.id_foetapp360 = :foetapp360id";

        $params = ['foetapp360id' => $foetapp360id];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }

    /**
     * Get exercice stats to show the stats per exercice
     * @param int $foetapp360id
     * @param int $dataset_id
     * 
     * @return array containing the stats for this exercice
     */
    public function get_exo_stats(int $foetapp360id, int $dataset_id)
    {
        $sql = "SELECT COUNT(DISTINCT userid) as total_students,
                        SUM(a.is_correct) as success_rate,
                        COUNT(a.id) as total_attempts
                FROM {foetapp360_attempt} a
                JOIN {foetapp360_session} s ON a.id_session = s.id
                WHERE s.id_foetapp360 = :foetapp360id AND a.id_dataset = :dataset_id";

        $params = ["foetapp360id" => $foetapp360id, "dataset_id" => $dataset_id];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }

    /**
     * Récupère les statistiques des exercices regroupées par nom de dataset et type d'inclinaison
     * pour une difficulté donnée
     * 
     * @param int $foetapp360id ID de l'instance foetapp360
     * @param string $difficulty Niveau de difficulté ('easy' ou 'hard')
     * @return array Statistiques groupées par nom de dataset et type d'inclinaison
     */
    public function get_dataset_stats_by_difficulty(int $foetapp360id, string $difficulty): array {
        // SQL pour récupérer les statistiques par dataset et inclinaison
        $sql = "SELECT 
                    d.name as dataset_name,
                    d.inclinaison,
                    COUNT(DISTINCT s.userid) as total_students,
                    SUM(a.is_correct) as correct_attempts,
                    COUNT(a.id) as total_attempts,
                    CAST(SUM(a.is_correct) AS FLOAT) / NULLIF(COUNT(a.id), 0) * 100 as success_rate
                FROM {foetapp360_attempt} a
                JOIN {foetapp360_session} s ON a.id_session = s.id
                JOIN {foetapp360_datasets} d ON a.id_dataset = d.id
                WHERE s.id_foetapp360 = :foetapp360id 
                    AND s.difficulty = :difficulty
                GROUP BY d.name, d.inclinaison
                ORDER BY d.name, d.inclinaison";

        $params = ["foetapp360id" => $foetapp360id, "difficulty" => $difficulty];
        
        // Exécuter la requête
        $records = $this->db->get_records_sql($sql, $params);

        // Organiser les résultats par nom de dataset et type d'inclinaison
        $results = [];
        
        foreach ($records as $record) {
            $dataset_name = $record->dataset_name;
            
            if (!isset($results[$dataset_name])) {
                $results[$dataset_name] = [
                    'bien' => [
                        'total_students' => 0, 
                        'correct_attempts' => 0, 
                        'total_attempts' => 0, 
                        'success_rate' => 0
                    ],
                    'mal' => [
                        'total_students' => 0, 
                        'correct_attempts' => 0, 
                        'total_attempts' => 0, 
                        'success_rate' => 0
                    ],
                    'peu' => [
                        'total_students' => 0, 
                        'correct_attempts' => 0, 
                        'total_attempts' => 0, 
                        'success_rate' => 0
                    ]
                ];
            }
            
            // Déterminer le type d'inclinaison
            $inclinaison_type = '';
            if ($record->inclinaison == 1) {
                $inclinaison_type = 'bien';
            } else if ($record->inclinaison == -1) {
                $inclinaison_type = 'mal';
            } else {
                $inclinaison_type = 'peu';
            }
            
            // Stocker les statistiques
            $results[$dataset_name][$inclinaison_type] = [
                'total_students' => $record->total_students,
                'correct_attempts' => $record->correct_attempts,
                'total_attempts' => $record->total_attempts,
                'success_rate' => $record->success_rate
            ];
        }
        
        return $results;
    }



        /**
     * Get exercice stats to show the stats per exercice
     * @param int $foetapp360id
     * @param int $dataset_id
     * 
     * @return array containing the stats for this exercice
     */
    public function get_exos(int $foetapp360id, int $dataset_id)
    {
        $sql = "SELECT *
                FROM {foetapp360_attempt} a
                JOIN {foetapp360_session} s ON a.id_session = s.id
                WHERE s.id_foetapp360 = :foetapp360id AND a.id_dataset = :dataset_id";

        $params = ["foetapp360id" => $foetapp360id, "dataset_id" => $dataset_id];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }


    /* -------------------------------------------------------------------------- */
    /*                         STUDENT SPECIFIC FUNCTIONS                         */
    /* -------------------------------------------------------------------------- */


    /**
     * Récupère la durée total passé sur une sessions.
     *
     * @param int $foetapp360id ID de l'instance foetapp360
     * @param int $userid ID de l'utilisateur
     * @return int Temp passé par l'étudient 
     */
    public function get_student_time_passed(int $foetapp360id, int $userid): int
    {
        $sessions = $this->db->get_records('foetapp360_session', ['id_foetapp360' => $foetapp360id, 'userid' => $userid]);

        $total_time = 0; // Initialize total time in seconds

        foreach ($sessions as $session) {
            // Calculate duration of each session (in seconds)
            if ($session->timefinish == 0) {
                continue; // Skip session if it's not finished
            } 
            $session_duration = $session->timefinish - $session->timestart;
            $total_time += $session_duration; // Add session duration to total time
        }
        return $total_time;
    }



    /**
     * Get Number of Sessions by Difficulty
     *
     * @param int $foetapp360id ID de l'instance foetapp360
     * @param int $userid ID de l'utilisateur
     * @return array  number of (easy_session,hard_session)
     */
    public function get_student_difficulties_amount(int $foetapp360id, int $userid): array
    {
        $sessions = $this->db->get_records('foetapp360_session', ['id_foetapp360' => $foetapp360id, 'userid' => $userid]);

        $easy_session = 0;
        $hard_session = 0;
        foreach ($sessions as $session) {
            if ($session->difficulty == 'easy') {
                $easy_session += 1;
            } else if ($session->difficulty == 'hard') {
                $hard_session += 1;
            }
        }


        return array($easy_session, $hard_session);
    }

    /**
     * Récupère le taux de réussite par difficulté pour un étudiant.
     *
     * @param int $foetapp360id ID de l'instance foetapp360
     * @param int $userid ID de l'utilisateur
     * @return array [easy_success, hard_success] en pourcentage
     */
    public function get_student_success_rate(int $foetapp360id, int $userid): array
    {
        global $DB;

        // Initialize success rates
        $easy_success = 0;
        $hard_success = 0;

        // Vérifier s'il y a des sessions
        $sessions = $DB->get_records('foetapp360_session', ['id_foetapp360' => $foetapp360id, 'userid' => $userid]);

        if (!empty($sessions)) {
            $sql = "SELECT s.difficulty, AVG(a.is_correct) * 100 AS success_rate 
                FROM {foetapp360_attempt} a
                JOIN {foetapp360_session} s ON a.id_session = s.id
                WHERE s.userid = :userid AND s.id_foetapp360 = :foetapp360id
                GROUP BY s.difficulty";

            $params = ['userid' => $userid, 'foetapp360id' => $foetapp360id];
            $results = $DB->get_records_sql($sql, $params);

            // Parcourir les résultats et assigner les valeurs
            foreach ($results as $result) {
                if ($result->difficulty === 'easy') {
                    $easy_success = round($result->success_rate, 2);
                } elseif ($result->difficulty === 'hard') {
                    $hard_success = round($result->success_rate, 2);
                }
            }
        }

        return [$easy_success, $hard_success];
    }


    /**
     * Récupère le taux de réussite par type d'input pour un étudiant.
     *
     * @param int $foetapp360id ID de l'instance foetapp360
     * @param int $userid ID de l'utilisateur
     * @return array Clés = types d'input, Valeurs = taux de réussite (%)
     */
    public function get_success_rate_by_input(int $foetapp360id, int $userid): array
    {
        global $DB;

        $success_rates = [];

        $sql = "SELECT 
                a.given_input, 
                COUNT(CASE WHEN a.is_correct = 1 THEN 1 END) * 100.0 / COUNT(*) AS success_ratio
            FROM {foetapp360_attempt} a
            JOIN {foetapp360_session} s ON a.id_session = s.id
            WHERE s.userid = :userid AND s.id_foetapp360 = :foetapp360id
            GROUP BY a.given_input
            ORDER BY success_ratio DESC";

        $params = ['userid' => $userid, 'foetapp360id' => $foetapp360id];
        $results = $DB->get_records_sql($sql, $params);

        // Stocker les résultats sous forme d'un tableau associatif
        foreach ($results as $result) {
            $success_rates[$result->given_input] = round($result->success_ratio, 2);
        }

        return $success_rates;
    }


    /**
     * Récupère les statistiques d'un étudiant spécifique.
     *
     * @param int $foetapp360id ID de l'instance foetapp360
     * @param int $userid ID de l'utilisateur
     * @return array Statistiques de l'étudiant
     */
    public function get_student_stats(int $foetapp360id, int $userid): array
    {
        $sql = "SELECT COUNT(a.id) as total_attempts,
                        SUM(a.is_correct) as success_total
                FROM {foetapp360_attempt} a
                JOIN {foetapp360_session} s ON a.id_session = s.id
                WHERE s.id_foetapp360 = :foetapp360id AND s.userid = :userid";

        $params = ['foetapp360id' => $foetapp360id, 'userid' => $userid];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }


    /**
     * Récupère le taux de réussite pour chaque type de représentation visuelle, 
     * séparé en trois catégories : Correct, OK, Mauvais.
     *
     * @param int $foetapp360id ID de l'instance foetapp360
     * @param int $userid ID de l'utilisateur
     * @return array Un tableau contenant trois sous-tableaux : Correct, OK et Mauvais
     */
    public function get_success_rate_by_representation(int $foetapp360id, int $userid): array
    {
        global $DB;

        $success_rates = [
            'correct' => [],
            'ok' => [],
            'bad' => []
        ];

        // Base SQL query
        $base_sql = "SELECT 
                    d.name AS representation,
                    COUNT(CASE WHEN a.is_correct = 1 THEN 1 END) * 100.0 / COUNT(*) AS success_ratio
                FROM {foetapp360_attempt} a
                JOIN {foetapp360_datasets} d ON a.id_dataset = d.id
                JOIN {foetapp360_session} s ON a.id_session = s.id
                WHERE d.inclinaison = :inclinaison 
                  AND s.userid = :userid 
                  AND s.id_foetapp360 = :foetapp360id
                GROUP BY d.name
                ORDER BY success_ratio DESC";

        $params = ['userid' => $userid, 'foetapp360id' => $foetapp360id];

        // Execute queries for each position type
        foreach (['correct' => 1, 'ok' => 0, 'bad' => -1] as $key => $inclinaison) {
            $params['inclinaison'] = $inclinaison;
            $results = $DB->get_records_sql($base_sql, $params);

            foreach ($results as $result) {
                $success_rates[$key][$result->representation] = round($result->success_ratio, 2);
            }
        }

        return $success_rates;
    }


    /**
     * Récupère les données de performance pour un graphique (tentatives et succès).
     *
     * @param int $userid ID de l'utilisateur
     * @param int $foetapp360id ID de l'instance foetapp360
     * @return array Données pour le graphique
     */
    public function get_student_performance_data(int $userid, int $foetapp360id): array
    {
        $sql = "SELECT a.id, a.attempt_number, a.is_correct
                FROM {foetapp360_attempt} a
                JOIN {foetapp360_session} s ON a.id_session = s.id
                WHERE s.userid = :userid AND s.id_foetapp360 = :foetapp360id
                ORDER BY a.attempt_number ASC";

        $params = ['userid' => $userid, 'foetapp360id' => $foetapp360id];
        return $this->db->get_records_sql($sql, $params);
    }
}