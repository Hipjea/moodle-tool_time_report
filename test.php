<?php

namespace tool_time_report;
 
require_once(__DIR__ . '/../../../config.php');

use core\message\message;
use moodle_url;

class generate_time_report_bis {
 
    const BORROWED_TIME = 900;

    public $totaltime = 0;

    public function setTotaltime($totaltime) { 
        $this->totaltime = $totaltime; 
    }

    public function getTotaltime() { 
        return $this->totaltime; 
    }

    private function get_time_spent($userid, $startmonth, $endmonth) {
        global $DB;
        
        if (!isset($startmonth)) {
            $startmonth = date('mY');
        }
        if (!isset($endmonth)) {
            $endmonth = date('mY');
        }

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

    /**
     * Execute the task.
     */
    public function execute($requestorid, $userid, $start, $end, $contextid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
        $results = $this->get_time_spent($user->id, $start, $end);
        $csv_data = $this->prepare_results($user, $results, $start, $end);
        print "<pre>";
        print_r($csv_data);
        print "</pre>";
        $this->create_csv($user, $requestorid, $csv_data, $contextid, $start, $end);
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
            return '<h5>Pas de résultats trouvés.</h5>';
        }

        $current_day = array_values($data)[0]->time;
        $current_timestamp = array_values($data)[0]->timecreated;
        $day_time = 0;
        $i = 0;
        $shift = 6;
        $l = sizeof($data);
        $interactions = 0;

        $out = array();
        $totaltime = 0;

        for ($i; $i < $l; $i++) {
            $item = array_values($data)[$i];
            if (!isset(array_values($data)[$i+1])) {
                $next_val = $item;
            } else {
                $next_val = array_values($data)[$i+1];
            }

            if ($item->time == $current_day) {
                if ($item->timecreated == $current_timestamp) {
                    $day_time = 0;
                } else {
                    if ( isset($next_val) && $next_val->time == $current_day ) {
                        $nval = intval($next_val->timecreated);
                        $inttimecreated = intval($item->timecreated);
                        $ts = $nval - $inttimecreated;
                        if (intval($ts / 60) > 30) {
                            $day_time = $day_time + self::BORROWED_TIME;
                        } else {
                            $new_day_time = $day_time + $nval - $inttimecreated;
                            if ( $day_time == $new_day_time ) {
                                continue;
                            } else {
                                if ( $new_day_time < intval($day_time + 30) ) {
                                    $day_time = $day_time + $nval - $inttimecreated;
                                    continue;
                                } else {
                                    $day_time = $day_time + $nval - $inttimecreated;
                                }
                            }
                        }
                    }
                }
                
                if ( $day_time > 0 ) {
                    $end_connection = isset($next_val) ? date('H:i:s', $this->end_connection($item->timecreated, $next_val->timecreated)) : '...';

                    // Calculate total time.
                    if ($i+1 < $l) {
                        if ($next_val) {
                            if ($current_day != $next_val->time) {
                                $totaltime = $totaltime + $day_time;
                            }
                        }
                    } else {
                        $totaltime = $totaltime + $day_time;
                    }
                }
                
            } else {
                $current_day = $item->time;
                $day_time = 0;
                if ( $day_time > 0 ) {
                    $end_connection = isset($next_val) ? date('H:i:s', $this->end_connection($item->timecreated, $next_val->timecreated)) : '...';
                }
            }

            if ( ($day_time > 0 && isset($next_val) && $next_val->time != $current_day) || ($day_time > 0 && $next_val == $item) ) {
                $date = date('d/m/Y', $item->timecreated);
                $seconds = $this->format_seconds($day_time);
                array_push($out, array($date, $seconds));
            }
        }

        $this->setTotaltime($totaltime);
        return $out;
    }

    private function create_csv($user, $requestorid, $data, $contextid, $startmonth, $endmonth) {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');
        require_once(dirname(__FILE__) . '/locallib.php');

        $lib = new \TimeReport();
        
        $datestart = \DateTime::createFromFormat('mY', $startmonth);
        $startmonthstr = $datestart->format('m/Y');
        $dateend = \DateTime::createFromFormat('mY', $endmonth);
        $endmonthstr = strftime($dateend->format('m/Y'));

        $delimiter = \csv_import_reader::get_delimiter('comma');
        $csventries = array(array());
        $csventries[] = array('Nom', 'Prenom', 'Email');
        $csventries[] = array($user->firstname, $user->lastname, $user->email);
        $csventries[] = array("\n");
        $csventries[] = array('Période', $startmonthstr . ' - ' . $endmonthstr);
        $csventries[] = array('Temps total pour la période', $this->format_seconds($this->getTotaltime()));
        $csventries[] = array("\n");
        $csventries[] = array('Date', 'Durée cumulée');

        $returnstr = '';
        $len = sizeof($data);
        $shift = 6;

        for ($i = 0; $i < $len; $i++) {
            $csventries[$i+$shift] = $data[$i];
        }
        foreach ($csventries as $entry) {
            $returnstr .= '"' . implode('"' . $delimiter . '"', $entry) . '"' . "\n";
        }
        
        $filename = $lib->generate_file_name($user->id, $startmonth, $endmonth);

        return $this->write_new_file($returnstr, $contextid, $filename, $user, $requestorid);
    }

    private function write_new_file($content, $contextid, $name, $user, $requestorid) {
        global $CFG;

        $fs = get_file_storage();
        $fileinfo = array(
            'contextid' => $contextid, // ID of context
            'component' => 'tool_time_report',     // usually = table name
            'filearea' => 'content',    // usually = table name
            'itemid' => 0,               // usually = ID of row in table
            'filepath' => '/',           // any path beginning and ending in /
            'filename' => $name); // any filename

        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
            
        if ($file) {
            // Delete the old file first
            $file->delete();
        }

        if ($fs->create_file_from_string($fileinfo, $content)) {
            $path = "$CFG->wwwroot/pluginfile.php/$contextid/tool_time_report/content/0/$name";

            $fullname = fullname($user);
            $messagehtml = "<p>Le rapport de l'utilisateur <strong>$fullname</strong> a été créé.</p>";
            $messagehtml .= "<p>Téléchargement : <a href=\"$path\" download><i class=\"fa fa-download\"></i>$name</a></p>";
            $contexturl = new moodle_url('/admin/tool/time_report/view.php', array('userid' => $user->id));

            $message = new message();
            $message->component         = 'tool_time_report';
            $message->name              = 'reportcreation';
            $message->userfrom          = \core_user::get_noreply_user();
            $message->userto            = $requestorid;
            $message->subject           = get_string('messageprovider:reportcreation', 'tool_time_report');
            $message->fullmessageformat = FORMAT_MARKDOWN;
            $message->fullmessage       = html_to_text($messagehtml);
            $message->fullmessagehtml   = $messagehtml;
            $message->smallmessage      = "Un rapport de d'utilisateur a été créé";
            $message->notification      = 1;
            $message->contexturl        = $contexturl;
            $message->contexturlname    = 'Rapport de temps de connexion';
            // Set the file attachment
            $message->attachment = $file;
            message_send($message);
        }

        return $file;
    }
}

global $USER;

$context = \context_system::instance();
$lib = new \tool_time_report\generate_time_report_bis();
$lib->execute($USER->id, 2, "122021", "022022", $context->id);
