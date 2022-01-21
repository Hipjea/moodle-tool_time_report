<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Time Report tool plugin's lib file.
 *
 * @package   tool_time_report
 * @copyright 2022 Pierre Duverneix - Fondation UNIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

use core_user\output\myprofile\tree;

/**
 * Add nodes to myprofile page.
 *
 * @param tree $tree Tree object
 * @param stdClass $user User object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function tool_time_report_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $CFG, $USER;
    $isteacher = false;

    $userid = required_param('id', PARAM_INT);
    $courseid = optional_param('courseid', 0, PARAM_INT); // User id.

    $categoryname = get_string('time_report', 'tool_time_report');
    $category = new core_user\output\myprofile\category('time_report', $categoryname, 'contact', 'admin_category');
    $tree->add_category($category);

    if ($courseid != 0) {
        $context = context_course::instance($courseid);
    } else {
        $context = context_system::instance();
    }

    if (has_any_capability(array('moodle/site:config', 'moodle/course:manageactivities'), $context)) {
        $isteacher = true;
    }

    // Controle de droit ensiegnant => user / etudiant global user.
    if ($isteacher) {
        $usercontext = \context_user::instance($userid);
        $uid = $userid;
    } else {
        $usercontext = \context_user::instance($USER->id);
        $uid = $USER->id;
    }

    if (isset($course->id)) {
        $url = new moodle_url('/admin/tool/time_report/view.php', ['userid' => $user->id, 'course' => $course->id]);
    } else {
        $url = new moodle_url('/admin/tool/time_report/view.php', ['userid' => $user->id]);
    }

    $node = new core_user\output\myprofile\node('time_report', 'tool_time_report', get_string('time_report', 'tool_time_report'), null, $url);
    $category->add_node($node);

    return true;
}


/**
 * Serve the files from the tool_time_report file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function tool_time_report_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false; 
    }
 
    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'content' && $filearea !== 'time_csv') {
        return false;
    }
 
    // Make sure the user is logged in and has access to the module (plugins that are not course modules should leave out the 'cm' part).
    require_login($course, true, $cm);
 
    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability('tool/time_report:view', $context)) {
        return false;
    }
 
    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.
 
    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.
 
    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // $args is empty => the path is '/'
    } else {
        $filepath = '/'.implode('/', $args).'/'; // $args contains elements of the filepath
    }
 
    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_time_report', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }
 
    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering. 
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
