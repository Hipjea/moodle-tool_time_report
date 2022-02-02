<?php

namespace tool_time_report\task;

require_once(__DIR__ . '/../../../../../config.php');

use core\message\message;
use moodle_url;


class daily_time_report extends \core\task\scheduled_task {
 
    const BORROWED_TIME = 900;

    protected $userid;
    protected $starttime = 0;
    protected $endtime = 0;
    protected $totaltime = 0;

    public function getStarttime() { 
        return $this->starttime; 
    }

    public function setStarttime($starttime) { 
        $this->starttime = $starttime; 
    }

    public function getEndtime() { 
        return $this->endtime; 
    }

    public function setEndtime($endtime) { 
        $this->endtime = $endtime; 
    }

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

        $starttime = $this->setStarttime("122020");
        $endtime = $this->setEndtime(new \Datetime("now"));

        if ($users = $DB->get_records('user')) {
            foreach($users as $user) {
                // reset the total time
                $this->setTotaltime(0);
                $results = $this->get_time_spent($user->id, $this->starttime, $this->endtime);
                $parsedresults = $this->prepare_results($user, $results, $this->starttime, $this->endtime);

                var_dump("User : " . $user->id . " => " . $this->getTotaltime());
                print "<br>";
            }
        }
    }

    private function get_time_spent($userid, $startmonth) {
        global $DB;
        
        if (!isset($startmonth)) {
            $startmonth = date('mY');
        }

        $endmonth = date('mY', $this->endtime->getTimestamp());
        $lastday = date(date('t', strtotime('01')) .'-' . $endmonth[0] . $endmonth[1] . '-' . $endmonth[2] . $endmonth[3] . $endmonth[4] . $endmonth[5]);
        $startdate = \DateTime::createFromFormat('dmY', '01'.$startmonth);
        $enddate = \DateTime::createFromFormat('dmY', $lastday[0].$lastday[1].$endmonth);
        $startdate = $startdate->getTimestamp();
        $enddate = $enddate->getTimestamp();

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
            
        $results = $DB->get_records_sql($sql, array($userid, $startdate, $enddate));
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
}

// TEST CALL
$test = new daily_time_report();
$test->execute();
