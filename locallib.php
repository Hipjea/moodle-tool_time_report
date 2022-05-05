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
 * Retrives the moodle_url of existing reports
 *
 * @return Array of moodle_url
 */
function get_reports_urls($contextid, $userid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'tool_time_report', 'content');
    $out = array();

    foreach ($files as $file) {
        $filename = $file->get_filename();
        $file_userid = get_user_id_from_filename($filename);
        if ($file_userid == $userid && $filename != '.') {
            $path = '/' . $file->get_contextid() . '/tool_time_report/content/' . $file->get_itemid() . $file->get_filepath() . $filename;
            $url = moodle_url::make_file_url('/pluginfile.php', $path);
            array_push($out, array('url' => $url, 'filename' => $filename));
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
