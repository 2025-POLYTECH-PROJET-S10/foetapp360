<?php
// Fichier : mod/hippotrack/classes/stats_manager.php

namespace mod_hippotrack;

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
     * Récupère les statistiques globales pour une instance hippotrack.
     *
     * @param int $hippotrackid ID de l'instance hippotrack
     * @return array Statistiques globales
     */
    public function get_global_stats(int $hippotrackid): array
    {
        $sql = "SELECT COUNT(DISTINCT userid) as total_students,
               AVG(sumgrades) as average_grade,
               AVG(questionsdone) as average_questions,
               AVG(a.is_correct) as success_rate,
               COUNT(a.id) as total_attempts
            FROM {hippotrack_session} s
            JOIN {hippotrack_attempt} a ON s.id = a.id_session
            WHERE s.id_hippotrack = :hippotrackid";

        $params = ['hippotrackid' => $hippotrackid];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }

    /**
     * Get exercice stats to show the stats per exercice
     * @param int $hippotrackid
     * @param int $dataset_id
     * 
     * @return array containing the stats for this exercice
     */
    public function get_exo_stats(int $hippotrackid, int $dataset_id)
    {
        $sql = "SELECT COUNT(DISTINCT userid) as total_students,
                        SUM(a.is_correct) as success_rate,
                        COUNT(a.id) as total_attempts
                FROM {hippotrack_attempt} a
                JOIN {hippotrack_session} s ON a.id_session = s.id
                WHERE s.id_hippotrack = :hippotrackid AND a.id_dataset = :dataset_id";

        $params = ["hippotrackid" => $hippotrackid, "dataset_id" => $dataset_id];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }

    /**
     * Get exercise statistics by difficulty level
     * @param int $hippotrackid The hippotrack activity instance ID
     * @param string $difficulty The difficulty level (easy/hard)
     * 
     * @return array Statistics grouped by inclination types (bien/mal/peu)
     */
    public function get_exo_stats_by_difficulty(int $hippotrackid, string $difficulty) {
        // Base SQL for all three inclination types
        $sql_base = "SELECT 
                        COUNT(DISTINCT s.userid) as total_students,
                        SUM(a.is_correct) as correct_attempts,
                        COUNT(a.id) as total_attempts,
                        ROUND((SUM(a.is_correct) * 100.0 / COUNT(a.id)), 2) as success_rate
                    FROM {hippotrack_attempt} a
                    JOIN {hippotrack_session} s ON a.id_session = s.id
                    JOIN {hippotrack_datasets} d ON a.id_dataset = d.id
                    WHERE s.id_hippotrack = :hippotrackid AND s.difficulty = :difficulty";

        // Specific SQL for each inclination type
        $sql_bien_flechi = $sql_base . " AND d.inclinaison = 1
        GROUP BY d.name";
        $sql_mal_flechi = $sql_base . " AND d.inclinaison = -1
        GROUP BY d.name";
        $sql_peu_flechi = $sql_base . " AND d.inclinaison <> -1 AND d.inclinaison <> 1
        GROUP BY d.name";

        $params = ["hippotrackid" => $hippotrackid, "difficulty" => $difficulty];
        
        // Get records for each inclination type
        $record_bien_flechi = $this->db->get_record_sql($sql_bien_flechi, $params);
        $record_mal_flechi = $this->db->get_record_sql($sql_mal_flechi, $params);
        $record_peu_flechi = $this->db->get_record_sql($sql_peu_flechi, $params);

        // Create an array containing the stats for this exercise
        $record_final = [
            "bien" => $record_bien_flechi ? (array)$record_bien_flechi : [],
            "mal" => $record_mal_flechi ? (array)$record_mal_flechi : [],
            "peu" => $record_peu_flechi ? (array)$record_peu_flechi : []
        ];

        return $record_final;
    }


        /**
     * Get exercice stats to show the stats per exercice
     * @param int $hippotrackid
     * @param int $dataset_id
     * 
     * @return array containing the stats for this exercice
     */
    public function get_exos(int $hippotrackid, int $dataset_id)
    {
        $sql = "SELECT *
                FROM {hippotrack_attempt} a
                JOIN {hippotrack_session} s ON a.id_session = s.id
                WHERE s.id_hippotrack = :hippotrackid AND a.id_dataset = :dataset_id";

        $params = ["hippotrackid" => $hippotrackid, "dataset_id" => $dataset_id];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }


    /* -------------------------------------------------------------------------- */
    /*                         STUDENT SPECIFIC FUNCTIONS                         */
    /* -------------------------------------------------------------------------- */


    /**
     * Récupère la durée total passé sur une sessions.
     *
     * @param int $hippotrackid ID de l'instance hippotrack
     * @param int $userid ID de l'utilisateur
     * @return int Temp passé par l'étudient 
     */
    public function get_student_time_passed(int $hippotrackid, int $userid): int
    {
        $sessions = $this->db->get_records('hippotrack_session', ['id_hippotrack' => $hippotrackid, 'userid' => $userid]);

        $total_time = 0; // Initialize total time in seconds

        foreach ($sessions as $session) {
            // Calculate duration of each session (in seconds)
            $session_duration = $session->timefinish - $session->timestart;
            $total_time += $session_duration; // Add session duration to total time
        }
        return $total_time;
    }



    /**
     * Get Number of Sessions by Difficulty
     *
     * @param int $hippotrackid ID de l'instance hippotrack
     * @param int $userid ID de l'utilisateur
     * @return array  number of (easy_session,hard_session)
     */
    public function get_student_difficulties_amount(int $hippotrackid, int $userid): array
    {
        $sessions = $this->db->get_records('hippotrack_session', ['id_hippotrack' => $hippotrackid, 'userid' => $userid]);

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
     * @param int $hippotrackid ID de l'instance hippotrack
     * @param int $userid ID de l'utilisateur
     * @return array [easy_success, hard_success] en pourcentage
     */
    public function get_student_success_rate(int $hippotrackid, int $userid): array
    {
        global $DB;

        // Initialize success rates
        $easy_success = 0;
        $hard_success = 0;

        // Vérifier s'il y a des sessions
        $sessions = $DB->get_records('hippotrack_session', ['id_hippotrack' => $hippotrackid, 'userid' => $userid]);

        if (!empty($sessions)) {
            $sql = "SELECT s.difficulty, AVG(a.is_correct) * 100 AS success_rate 
                FROM {hippotrack_attempt} a
                JOIN {hippotrack_session} s ON a.id_session = s.id
                WHERE s.userid = :userid AND s.id_hippotrack = :hippotrackid
                GROUP BY s.difficulty";

            $params = ['userid' => $userid, 'hippotrackid' => $hippotrackid];
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
     * @param int $hippotrackid ID de l'instance hippotrack
     * @param int $userid ID de l'utilisateur
     * @return array Clés = types d'input, Valeurs = taux de réussite (%)
     */
    public function get_success_rate_by_input(int $hippotrackid, int $userid): array
    {
        global $DB;

        $success_rates = [];

        $sql = "SELECT 
                a.given_input, 
                COUNT(CASE WHEN a.is_correct = 1 THEN 1 END) * 100.0 / COUNT(*) AS success_ratio
            FROM {hippotrack_attempt} a
            JOIN {hippotrack_session} s ON a.id_session = s.id
            WHERE s.userid = :userid AND s.id_hippotrack = :hippotrackid
            GROUP BY a.given_input
            ORDER BY success_ratio DESC";

        $params = ['userid' => $userid, 'hippotrackid' => $hippotrackid];
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
     * @param int $hippotrackid ID de l'instance hippotrack
     * @param int $userid ID de l'utilisateur
     * @return array Statistiques de l'étudiant
     */
    public function get_student_stats(int $hippotrackid, int $userid): array
    {
        $sql = "SELECT COUNT(a.id) as total_attempts,
                        SUM(a.is_correct) as success_total
                FROM {hippotrack_attempt} a
                JOIN {hippotrack_session} s ON a.id_session = s.id
                WHERE s.id_hippotrack = :hippotrackid AND s.userid = :userid";

        $params = ['hippotrackid' => $hippotrackid, 'userid' => $userid];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }


    /**
     * Récupère le taux de réussite pour chaque type de représentation visuelle, 
     * séparé en trois catégories : Correct, OK, Mauvais.
     *
     * @param int $hippotrackid ID de l'instance hippotrack
     * @param int $userid ID de l'utilisateur
     * @return array Un tableau contenant trois sous-tableaux : Correct, OK et Mauvais
     */
    public function get_success_rate_by_representation(int $hippotrackid, int $userid): array
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
                FROM {hippotrack_attempt} a
                JOIN {hippotrack_datasets} d ON a.id_dataset = d.id
                JOIN {hippotrack_session} s ON a.id_session = s.id
                WHERE d.inclinaison = :inclinaison 
                  AND s.userid = :userid 
                  AND s.id_hippotrack = :hippotrackid
                GROUP BY d.name
                ORDER BY success_ratio DESC";

        $params = ['userid' => $userid, 'hippotrackid' => $hippotrackid];

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
     * @param int $hippotrackid ID de l'instance hippotrack
     * @return array Données pour le graphique
     */
    public function get_student_performance_data(int $userid, int $hippotrackid): array
    {
        $sql = "SELECT a.id, a.attempt_number, a.is_correct
                FROM {hippotrack_attempt} a
                JOIN {hippotrack_session} s ON a.id_session = s.id
                WHERE s.userid = :userid AND s.id_hippotrack = :hippotrackid
                ORDER BY a.attempt_number ASC";

        $params = ['userid' => $userid, 'hippotrackid' => $hippotrackid];
        return $this->db->get_records_sql($sql, $params);
    }
}