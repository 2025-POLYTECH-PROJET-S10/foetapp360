<?php
namespace mod_hippotrack;

defined('MOODLE_INTERNAL') || die();

class students_stats {
    private $userid;
    private $hippotrackid;
    private $db;

    public function __construct($userid, $hippotrackid) {
        global $DB;
        $this->userid = $userid;
        $this->hippotrackid = $hippotrackid;
        $this->db = $DB;
    }

    /**
     * ✅ Get total number of attempts for the student
     */
    public function getTotalAttempts() {
        return $this->db->count_records('hippotrack_attempt', [
            'id_hippotrack' => $this->hippotrackid,
            'userid' => $this->userid
        ]);
    }

    /**
     * ✅ Get the average score for the student
     */
    public function getAverageScore() {
        return $this->db->get_field_sql("
            SELECT AVG(score) 
            FROM {hippotrack_attempt} 
            WHERE id_hippotrack = ? AND userid = ?", 
            [$this->hippotrackid, $this->userid]
        );
    }

    /**
     * ✅ Get the best score for the student
     */
    public function getBestScore() {
        return $this->db->get_field_sql("
            SELECT MAX(score) 
            FROM {hippotrack_attempt} 
            WHERE id_hippotrack = ? AND userid = ?", 
            [$this->hippotrackid, $this->userid]
        );
    }

    /**
     * ✅ Get the worst score for the student
     */
    public function getWorstScore() {
        return $this->db->get_field_sql("
            SELECT MIN(score) 
            FROM {hippotrack_attempt} 
            WHERE id_hippotrack = ? AND userid = ?", 
            [$this->hippotrackid, $this->userid]
        );
    }

    /**
     * ✅ Get total time spent on all attempts
     */
    public function getTotalTimeSpent() {
        return $this->db->get_field_sql("
            SELECT SUM(time_taken) 
            FROM {hippotrack_attempt} 
            WHERE id_hippotrack = ? AND userid = ?", 
            [$this->hippotrackid, $this->userid]
        );
    }

    /**
     * ✅ Get success rate for the student (percentage of correct attempts)
     */
    public function getSuccessRate() {
        $total = $this->getTotalAttempts();
        if ($total == 0) return 0;

        $success = $this->db->get_field_sql("
            SELECT SUM(success) 
            FROM {hippotrack_attempt} 
            WHERE id_hippotrack = ? AND userid = ?", 
            [$this->hippotrackid, $this->userid]
        );

        return round(($success / $total) * 100, 2); // Percentage
    }

    /**
     * ✅ Get all stats in one array
     */
    public function getAllStats() {
        return [
            'total_attempts' => $this->getTotalAttempts(),
            'average_score' => $this->getAverageScore(),
            'best_score' => $this->getBestScore(),
            'worst_score' => $this->getWorstScore(),
            'total_time_spent' => $this->getTotalTimeSpent(),
            'success_rate' => $this->getSuccessRate(),
        ];
    }
}