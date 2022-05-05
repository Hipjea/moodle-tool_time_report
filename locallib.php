<?php

/**
 * Time Report tool plugin's local lib.
 *
 * @package   tool_time_report
 * @copyright 2022 Pierre Duverneix - Fondation UNIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Retrives the files of existing reports
 *
 * @return Array of moodle_url
 */
function get_reports_files($contextid, $userid) {
    global $DB;

    $conditions = array('contextid' => $contextid, 'component' => 'tool_time_report', 'filearea' => 'content', 'userid' => $userid);
    $file_records = $DB->get_records('files', $conditions);
    return $file_records;
}

/**
 * Retrives the moodle_url of existing reports
 *
 * @return Array of moodle_url
 */
function get_reports_urls($contextid, $userid) {
    $files = get_reports_files($contextid, $userid);
    $out = array();

    foreach ($files as $file) {
        if ($file->filename != '.') {
            $path = '/' . $file->contextid . '/tool_time_report/content/' . $file->itemid . $file->filepath . $file->filename;
            $url = moodle_url::make_file_url('/pluginfile.php', $path);
            array_push($out, array('url' => $url, 'filename' => $file->filename));
        }
    }

    return $out;
}

/**
 * Generates the filename.
 *
 * @param  string $startdate
 * @param  string $enddate
 * @return string
 */
function generate_file_name($userid, $startdate, $enddate) {
    if (!$userid) {
        throw new \coding_exception('Missing userid');
    }
    return 'report_user_' . $userid . '__' . $startdate . '_' . $enddate . '.csv';
}

/**
 * Extracts the ID of the user from the filename.
 *
 * @param  string $filename
 * @return int
 */
function get_user_id_from_filename($filename) {
    $parts = explode('_', $filename);
    if (isset($parts[2])) {
        return intval($parts[2]);
    }
    return false;
}

/**
 * Removes the report files for a given user.
 *
 * @param  string $filename
 * @return int
 */
function remove_reports_files($contextid, $userid) {
    $files = get_reports_files($contextid, $userid);

    foreach($files as $file) {
        $fs = get_file_storage();
        $file = $fs->get_file($file->contextid, $file->component, $file->filearea,
            $file->itemid, $file->filepath, $file->filename);
        if ($file) {
            $file->delete();
        }
    }
}
