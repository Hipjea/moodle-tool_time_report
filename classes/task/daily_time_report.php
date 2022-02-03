<?php

namespace tool_time_report\task;

require_once(__DIR__ . '/../../../../../config.php');

use core\message\message;
use moodle_url;


class daily_time_report extends \core\task\scheduled_task {
 
    const BORROWED_TIME = 900;

    protected $userid;
    protected $totaltime = 0;

    public function setTotaltime($totaltime) { 
        $this->totaltime = $totaltime; 
    }

    public function getTotaltime() { 
        return $this->totaltime; 
    }

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('daily_time_report', 'tool_time_report');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $now = new \Datetime("now");
        $yesterday = new \Datetime("now");
        $yesterday->sub(new \DateInterval('P1D'));
        $endtime = $now->getTimestamp();

        if ($users = $DB->get_records('user')) {
            foreach($users as $user) {
                // reset the total time
                $this->setTotaltime(0);
                $userrow = $this->get_user_timespent($user->id);
                if (isset($userrow->updatedat) && $userrow->updatedat > 1) {
                    if ($userrow->timespent == 0) {
                        // case for the first timespent insertion
                        $results = $this->get_time_spent($user->id, 0, $endtime);
                        $parsedresults = $this->prepare_results($user, $results, 0, $endtime);
                        $usertimespent = $userrow->timespent;
                        $newtotal = intval($usertimespent) + intval($this->getTotaltime());
                        $this->update_user_timespent($userrow, $newtotal);
                    } else {
                        // otherwise, we use the user's updatedat timestamp value as the start time
                        $results = $this->get_time_spent($user->id, $userrow->updatedat, $endtime);
                        $parsedresults = $this->prepare_results($user, $results, $userrow->updatedat, $endtime);
                        $usertimespent = $userrow->timespent;
                        $newtotal = intval($usertimespent) + intval($this->getTotaltime());
                        $this->update_user_timespent($userrow, $newtotal);
                    }
                    // var_dump("User : " . $user->id . " / DB => " . $this->format_seconds($this->get_user_timespent($user->id)->timespent) . " / New time => " . $this->format_seconds($this->getTotaltime()));
                    // print "<br>";
                } else {
                    $starttime = 0;
                    $results = $this->get_time_spent($user->id, $starttime, $endtime);
                    $parsedresults = $this->prepare_results($user, $results, $starttime, $endtime);
                    $totaltime = intval($this->getTotaltime());

                    if ($this->create_user_timespent($user->id, $totaltime)) {

                    }
                }
            }
        }
    }

    private function get_user_timespent($userid) {
        global $DB;
        // get the user record
        $sql = 'SELECT *
                FROM {time_report_user_time}
                WHERE userid = :userid
                LIMIT 1';
        return $DB->get_record_sql($sql, array('userid' => $userid));
    }

    private function create_user_timespent($userid, $newtimespent) {
        global $DB;
        // update the data
        $now = new \Datetime("now");
        $row = new \stdClass();
        $row->userid = $userid;
        $row->timespent = $newtimespent;
        $row->updatedat = $now->getTimestamp();
        $DB->insert_record('time_report_user_time', $row);
    }

    private function update_user_timespent($row, $newtimespent) {
        global $DB;
        // update the data
        $now = new \Datetime("now");
        $row->timespent = $newtimespent;
        $row->updatedat = $now->getTimestamp();
        $DB->update_record('time_report_user_time', $row);
    }

    private function get_time_spent($userid, $starttime, $endtime) {
        global $DB;
        
        if (!isset($startmonth)) {
            $startmonth = date('mY');
        }

        $endmonth = date('mY', $endtime);
        $lastday = date(date('t', strtotime('01')) .'-' . $endmonth[0] . $endmonth[1] . '-' . $endmonth[2] . $endmonth[3] . $endmonth[4] . $endmonth[5]);
        $newendtime = \DateTime::createFromFormat('dmY', $lastday[0].$lastday[1].$endmonth);
        $newendtime = $newendtime->getTimestamp();

        $sql = 'SELECT {logstore_standard_log}.id, {logstore_standard_log}.timecreated, 
                {logstore_standard_log}.courseid, 
                DATE_FORMAT(FROM_UNIXTIME({logstore_standard_log}.timecreated), "%Y%m") AS datecreated, 
                DATE(FROM_UNIXTIME({logstore_standard_log}.timecreated)) AS time, 
                {logstore_standard_log}.userid, {user}.email, {course}.fullname 
                FROM {logstore_standard_log} 
                INNER JOIN {course} ON {logstore_standard_log}.courseid = {course}.id 
                LEFT OUTER JOIN {user} ON {logstore_standard_log}.userid = {user}.id 
                WHERE {logstore_standard_log}.userid = ? 
                AND {logstore_standard_log}.timecreated BETWEEN ? AND ? 
                AND {logstore_standard_log}.courseid <> 1 
                ORDER BY {logstore_standard_log}.timecreated ASC';
            
        $results = $DB->get_records_sql($sql, array($userid, $starttime, $newendtime));
        return $results;
    }

    private static function end_connection($current_item_time, $next_item_time) {
        if ($next_item_time == NULL) {
            return '';
        }
        $val = $next_item_time - $current_item_time / 60;
        if ($val > 30) {
            return $current_item_time + self::BORROWED_TIME;
        } else {
            return $next_item_time;
        }
    }

    private function prepare_results($user, $data, $startmonth, $endmonth) {
        if (!array_values($data)) {
            return '<h5>'. get_string('no_results_found', 'tool_time_report') .'</h5>';
        }

        $currentday = array_values($data)[0];
        $daytime = 0;
        $i = 0;
        $length = sizeof($data);

        $totaltime = 0;

        for ($i; $i < $length; $i++) {
            $item = array_values($data)[$i];
            if (!isset(array_values($data)[$i+1])) {
                $nextval = $item;
            } else {
                $nextval = array_values($data)[$i+1];
            }

            if ($item->time == $currentday->time) {
                if ($item->timecreated == $currentday->timecreated) {
                    $daytime = 0;
                } else {
                    if ( isset($nextval) && $nextval->time == $currentday->time ) {
                        $nextvaltimecreated = intval($nextval->timecreated);
                        $inttimecreated = intval($item->timecreated);
                        $ts = $nextvaltimecreated - $inttimecreated;
                        if (intval($ts / 60) > 30) {
                            $daytime = $daytime + self::BORROWED_TIME;
                        } else {
                            $new_day_time = $daytime + $nextvaltimecreated - $inttimecreated;
                            if ( $daytime == $new_day_time ) {
                                continue;
                            } else {
                                if ( $new_day_time < intval($daytime + 30) ) {
                                    $daytime = $daytime + $nextvaltimecreated - $inttimecreated;
                                    continue;
                                } else {
                                    $daytime = $daytime + $nextvaltimecreated - $inttimecreated;
                                }
                            }
                        }
                    }
                }
                
                if ( $daytime > 0 ) {
                    $end_connection = isset($nextval) ? date('H:i:s', $this->end_connection($item->timecreated, $nextval->timecreated)) : '...';

                    // Calculate total time.
                    if ($i+1 < $length) {
                        if ($nextval) {
                            if ($currentday->time != $nextval->time) {
                                $totaltime = $totaltime + $daytime;
                            }
                        }
                    } else {
                        $totaltime = $totaltime + $daytime;
                    }
                }
            }
        }

        return $this->setTotaltime($totaltime);
    }

    private static function format_seconds($seconds) {
        $hours = 0;
        $milliseconds = str_replace('0.', '', $seconds - floor( $seconds ));
        if ($seconds > 3600) {
            $hours = floor($seconds / 3600);
        }
        $seconds = $seconds % 3600;
        return str_pad($hours, 2, '0', STR_PAD_LEFT)
            . date(':i:s', $seconds)
            . ($milliseconds ? $milliseconds : '');
    }
}

// TEST CALL
// $test = new daily_time_report();
// $test->execute();
