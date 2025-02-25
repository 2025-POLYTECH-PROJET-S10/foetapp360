<?php

namespace mod_hippotrack;

defined('MOODLE_INTERNAL') || die();


class single_student {
    private $userid;
    private $user;
    private $sessions;
    private $attempts;

    public function __construct($userid) {
        global $DB;
        $this->userid = $userid;
        $this->user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $this->sessions = $DB->get_records('hippotrack_session', ['userid' => $userid]);
        $this->attempts = $DB->get_records_sql(
            "SELECT ha.* FROM {hippotrack_attempt} ha
            JOIN {hippotrack_session} hs ON ha.id_session = hs.id
            WHERE hs.userid = ?", 
            [$userid]
        );
    }

    public function get_user_info() {
        return [
            'id' => $this->user->id,
            'username' => $this->user->username,
            'firstname' => $this->user->firstname,
            'lastname' => $this->user->lastname,
            'email' => $this->user->email,
        ];
    }

    public function get_sessions() {
        return $this->sessions;
    }

    public function get_attempts() {
        return $this->attempts;
    }

    public function get_attempt_success_rate() {
        if (empty($this->attempts)) {
            return 0;
        }
        $total_attempts = count($this->attempts);
        $successful_attempts = count(array_filter($this->attempts, function($attempt) {
            return $attempt->is_correct == 1;
        }));
        return ($successful_attempts / $total_attempts) * 100;
    }
}
