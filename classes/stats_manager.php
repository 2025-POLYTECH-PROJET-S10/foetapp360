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
                FROM {hippotrack_session} s
                JOIN {hippotrack_attempt} a ON s.id = a.id_session
                WHERE s.id_hippotrack = :hippotrackid AND a.id_dataset = :dataset_id";

        $params = ["hippotrackid" => $hippotrackid, "dataset_id" => $dataset_id];
        $record = $this->db->get_record_sql($sql, $params);
        return $record ? (array) $record : [];
    }


    /* -------------------------------------------------------------------------- */
    /*                         STUDENT SPECIFIC FUNCTIONS                         */
    /* -------------------------------------------------------------------------- */

    /**
     * Récupère les statistiques d'un étudiant spécifique.
     *
     * @param int $hippotrackid ID de l'instance hippotrack
     * @param int $userid ID de l'utilisateur
     * @return array Statistiques de l'étudiant
     */
    public function get_student_stats(int $hippotrackid, int $userid): array
    {
        $sql = "SELECT id, sumgrades, questionsdone, timestart
                FROM {hippotrack_session}
                WHERE id_hippotrack = :hippotrackid AND userid = :userid
                ORDER BY timestart ASC";

        $params = ['hippotrackid' => $hippotrackid, 'userid' => $userid];
        return $this->db->get_records_sql($sql, $params);
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