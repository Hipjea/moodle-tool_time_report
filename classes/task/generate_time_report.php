<?php

namespace tool_time_report\task;

require_once __DIR__ . '/../../../../../config.php';

use core\message\message;
use moodle_url;

class generate_time_report extends \core\task\adhoc_task {

    public $totaltime = 0;

    public function setTotaltime($totaltime) { 
        $this->totaltime = $totaltime; 
    }

    public function getTotaltime() { 
        return $this->totaltime; 
    }

    private function get_time_spent($userid, $startmonth, $endmonth) {
        require_once dirname(__FILE__) . '/../../locallib.php';

        if (!isset($startmonth)) {
            $startmonth = date('mY');
        }
        if (!isset($endmonth)) {
            $endmonth = date('mY');
        }

        $lastday = date(
            date('t', strtotime('01')).'-'.$endmonth[0].$endmonth[1].'-'.$endmonth[2].$endmonth[3].$endmonth[4].$endmonth[5]
        );
        $startdate = \DateTime::createFromFormat('dmY', '01'.$startmonth);
        $enddate = \DateTime::createFromFormat('dmY', $lastday[0].$lastday[1].$endmonth);
        $startdate = $startdate->getTimestamp();
        $enddate = $enddate->getTimestamp();

        return get_log_records($userid, $startdate, $enddate);
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (isset($data)) {
            $user = $DB->get_record('user', array('id' => $data->userid), '*', MUST_EXIST);
            $results = $this->get_time_spent($user->id, $data->start, $data->end);
            $csv_data = $this->prepare_results($user, $results, $data->start, $data->end);
            $this->create_csv($user, $data->requestorid, $csv_data, $data->contextid, $data->start, $data->end);
        }
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

    private function prepare_results($user, $data, $startmonth, $endmonth) {
        if (!array_values($data)) {
            return '<h5>'. get_string('no_results_found', 'tool_time_report') .'</h5>';
        }

        $idletime = get_config('tool_time_report', 'idletime') / MINSECS;
        $borrowedtime = get_config('tool_time_report', 'borrowedtime') * 1;
        $currentday = array_values($data)[0];
        $timefortheday = 0;
        $i = 0;
        $length = sizeof($data);

        $out = array();
        $totaltime = 0;

        for ($i; $i < $length; $i++) {
            $item = array_values($data)[$i];
            $nextval = self::get_nextval($data, $i);

            // Last iteration
            if ($item->id == $nextval->id) {
                $totaltime = $totaltime + $timefortheday;
                $out = self::push_result($out, $item->timecreated, $timefortheday);
                break;
            }

            // If the item log time is different than the current day time, we move forward
            if ($item->logtimecreated != $currentday->logtimecreated) {
                $currentday = $item;
                $timefortheday = 0;
            }

            if (isset($nextval) && $nextval->logtimecreated == $currentday->logtimecreated) {
                $nextvaltimecreated = intval($nextval->timecreated);
                $itemtimecreated = intval($item->timecreated);
                $timedelta = $nextvaltimecreated - $itemtimecreated;

                if (intval($timedelta / MINSECS) > $idletime) {
                    $timefortheday = $timefortheday + $borrowedtime;
                } else {
                    $tmpdaytime = $timefortheday + $nextvaltimecreated - $itemtimecreated;
                    if ($tmpdaytime < intval($timefortheday + $idletime)) {
                        continue;
                    } else {
                        $timefortheday = $tmpdaytime;
                    }
                }
            } else if ($nextval->logtimecreated != $currentday->logtimecreated) {
                // Last iteration of the day
                $timefortheday = $timefortheday + $borrowedtime;
            }

            if (($timefortheday > 0 && isset($nextval) && $nextval->logtimecreated != $currentday->logtimecreated) 
                || ($timefortheday > 0 && $nextval == $item)) {
                $totaltime = $totaltime + $timefortheday;
                $out = self::push_result($out, $item->timecreated, $timefortheday);
            }
        }

        $this->setTotaltime($totaltime);
        return $out;
    }

    /**
     * Get the next item of the array of report results.
     */
    private static function get_nextval($data, $iteration) {
        $item = array_values($data)[$iteration];
        if (!isset(array_values($data)[$iteration+1])) {
            return $item;
        }
        return array_values($data)[$iteration+1];
    }

    private static function push_result($items, $itemtimecreated, $timefortheday) {
        $date = date('d/m/Y', $itemtimecreated);
        $seconds = self::format_seconds($timefortheday);
        array_push($items, array($date, $seconds));
        return $items;
    }

    private function create_csv($user, $requestorid, $data, $contextid, $startmonth, $endmonth) {
        global $CFG;
        require_once $CFG->libdir . '/csvlib.class.php';
        require_once dirname(__FILE__) . '/../../locallib.php';
        
        $datestart = \DateTime::createFromFormat('mY', $startmonth);
        $startmonthstr = $datestart->format('m/Y');
        $dateend = \DateTime::createFromFormat('mY', $endmonth);
        $endmonthstr = strftime($dateend->format('m/Y'));

        $delimiter = \csv_import_reader::get_delimiter('comma');
        $csventries = array(array());
        $csventries[] = array(get_string('name', 'core'), $user->lastname);
        $csventries[] = array(get_string('firstname', 'core'), $user->firstname);
        $csventries[] = array(get_string('email', 'core'), $user->email);
        $csventries[] = array(get_string('period', 'tool_time_report'), $startmonthstr . ' - ' . $endmonthstr);
        $csventries[] = array(get_string('period_total_time', 'tool_time_report'), self::format_seconds($this->getTotaltime()));
        $csventries[] = array('Date', get_string('total_duration', 'tool_time_report'));

        $returnstr = '';
        $len = sizeof($data);
        $shift = count($csventries);

        for ($i = 0; $i < $len; $i++) {
            $csventries[$i+$shift] = $data[$i];
        }
        foreach ($csventries as $entry) {
            $returnstr .= '"' . implode('"' . $delimiter . '"', $entry) . '"' . "\n";
        }
        
        $filename = generate_file_name(fullname($user), $startmonth, $endmonth);

        return $this->write_new_file($returnstr, $contextid, $filename, $user, $requestorid);
    }

    private function write_new_file($content, $contextid, $filename, $user, $requestorid) {
        global $CFG;

        $fs = get_file_storage();
        $fileinfo = array(
            'contextid' => $contextid,
            'component' => 'tool_time_report',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $user->id
        );

        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
                $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
            
        if ($file) {
            $file->delete(); // Delete the old file first.
        }

        if ($fs->create_file_from_string($fileinfo, $content)) {
            $path = "$CFG->wwwroot/pluginfile.php/$contextid/tool_time_report/content/0/$filename";
            $this->generate_message($user, $path, $filename, $file, $requestorid);
        }

        return $file;
    }

    public function generate_message($user, $path, $filename, $file, $requestorid) {
        $fullname = fullname($user);
        $messagehtml = "<p>" . get_string('download', 'core') . " : ";
        $messagehtml .= "<a href=\"$path\" download><i class=\"fa fa-download\"></i>$filename</a></p>";
        $contexturl = new moodle_url('/admin/tool/time_report/view.php', array('userid' => $user->id));

        $message = new message();
        $message->component         = 'tool_time_report';
        $message->name              = 'reportcreation';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $requestorid;
        $message->subject           = get_string('messageprovider:reportcreation', 'tool_time_report'). " : " .$fullname;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessage       = html_to_text($messagehtml);
        $message->fullmessagehtml   = $messagehtml;
        $message->smallmessage      = get_string('messageprovider:report_created', 'tool_time_report');
        $message->notification      = 1;
        $message->contexturl        = $contexturl;
        $message->contexturlname    = get_string('time_report', 'tool_time_report');
        $message->attachment = $file; // Set the file attachment
        message_send($message);
    }
}
