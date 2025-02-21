<?php
namespace mod_hippotrack;

defined('MOODLE_INTERNAL') || die();

class professor_stats {
    private $hippotrackid;
    private $db;

    public function __construct($hippotrackid) {
        global $DB;
        $this->hippotrackid = $hippotrackid;
        $this->db = $DB;
    }

    /**
     * ✅ Get total number of unique students who attempted the activity
     */
    public function getTotalStudents() {
        return $this->db->count_records_sql("
            SELECT COUNT(DISTINCT userid) 
            FROM {hippotrack_attempt} JOIN {hippotrack_session} ON {hippotrack_attempt}.id_session = {hippotrack_session}.id
            WHERE id_hippotrack = ?", [$this->hippotrackid]);
    }

    /**
     * ✅ Get total number of attempts across all students
     */
    public function getTotalAttempts() {
        return $this->db->count_records('hippotrack_attempt', ['id_hippotrack' => $this->hippotrackid]);
    }

    /**
     * ✅ Get the average score across all students
     */
    public function getAverageScore() {
        return $this->db->get_field_sql("
            SELECT AVG(score) 
            FROM {hippotrack_attempt} 
            WHERE id_hippotrack = ?", [$this->hippotrackid]);
    }

    /**
     * ✅ Get the overall success rate (percentage of correct attempts)
     */
    public function getSuccessRate() {
        $total = $this->getTotalAttempts();
        if ($total == 0) return 0;

        $success = $this->db->get_field_sql("
            SELECT SUM(success) 
            FROM {hippotrack_attempt} 
            WHERE id_hippotrack = ?", [$this->hippotrackid]);

        return round(($success / $total) * 100, 2); // Percentage
    }

    /**
     * ✅ Get total teaching time (sum of all attempt times)
     */
    public function getTotalTeachingTime() {
        return $this->db->get_field_sql("
            SELECT SUM(TIMESTAMPDIFF(SECOND, timestart, timefinish)) 
            FROM {hippotrack_attempt} JOIN {hippotrack_session} ON {hippotrack_attempt}.id_session = {hippotrack_session}.id
            WHERE id_hippotrack = ?", [$this->hippotrackid]);
    }

    /**
     * ✅ Get all stats in one array
     */
    public function getAllStats() {
        return [
            'total_students' => $this->getTotalStudents(),
            'total_attempts' => $this->getTotalAttempts(),
            'average_score' => $this->getAverageScore(),
            'success_rate' => $this->getSuccessRate(),
            'total_teaching_time' => $this->getTotalTeachingTime(),
        ];
    }
}