<?php

/**
 * Time Report tool plugin's local lib.
 *
 * @package   tool_time_report
 * @copyright 2022 Pierre Duverneix - Fondation UNIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

define("BORROWED_TIME", 900);

class TimeReport {

    protected $contextid;

    protected $userid;

    public function __construct($contextid, $userid) {
        $this->contextid = $contextid;
        $this->userid = $userid;
    }

    public function get_reports_urls() {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->contextid, 'tool_time_report', 'content');
        $out = array();

        foreach ($files as $file) {
            $filename = $file->get_filename();
            $file_userid = $this->get_user_id_from_filename($filename);
            if ($file_userid == $this->userid && $filename != '.') {
                $path = '/'.$file->get_contextid().'/tool_time_report/content/'.$file->get_itemid().$file->get_filepath().$filename;
                $url = moodle_url::make_file_url('/pluginfile.php', $path);
                array_push($out, array('url' => $url, 'filename' => $filename));
            }
        }

        return $out;
    }

    public function group_by($data, $key) {
        $result = array();
    
        foreach($data as $val) {
            if (isset($key, $val)) {
                $val = (array) $val;
                $result[$val[$key]][] = $val;
            } else {
                $result[""][] = $val;
            }
        }
    
        return $result;
    }
    
    public function starts_with($haystack, $needle) {
        return stripos($haystack, $needle) === 0;
    }

    public function tool_time_report_array_to_csv_download($array, $filename = 'export.csv') {
        header('Content-Type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'";');
        $f = fopen('php://output', 'w');
        foreach ($array as $line) {
            fputcsv($f, $line, ';');
        }
        fclose($f);
    }

    public function generate_file_name($startdate, $enddate) {
        if (!$this->userid) {
            throw new \coding_exception('Missing userid');
        }
        return 'report_user_' .$this->userid. '__' .$startdate. '_' .$enddate. '.csv';
    }

    public function get_user_id_from_filename($filename) {
        $parts = explode('_', $filename);
        if (isset($parts[2])) {
            return intval($parts[2]);
        }
        return false;
    }

}
