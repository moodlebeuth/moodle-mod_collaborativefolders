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
 * Library of interface functions and constants for module collaborativefolders
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the collaborativefolders specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_collaborativefolders
 * @copyright  2016 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_collaborativefolders\owncloud_access;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/setuplib.php');
require_once($CFG->libdir.'/oauthlib.php');

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function collaborativefolders_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the collaborativefolders into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $collaborativefolders Submitted data from the form in mod_form.php
 * @param mod_collaborativefolders_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted collaborativefolders record
 */
function collaborativefolders_add_instance(stdClass $collaborativefolders, mod_collaborativefolders_mod_form $mform = null) {
    global $DB;

    $fromform = $mform->get_data();
    $arraydata = get_object_vars($fromform);
    $collaborativefolders->teacher = $arraydata['teacher'];
    $collaborativefolders->timecreated = time();
    $collaborativefolders->id = $DB->insert_record('collaborativefolders', $collaborativefolders);

    if ($fromform) {
        $allgroups = $DB->get_records('groups');
        foreach ($allgroups as $key => $group) {
            $identifierstring = '' . $group->id;
            if ($arraydata[$identifierstring] == '1') {
                $databaserecord['modid'] = $collaborativefolders->id;
                $databaserecord['groupid'] = $group->id;
                $DB->insert_record('collaborativefolders_group', $databaserecord);
            }
        }
    }

    $DB->update_record('collaborativefolders', $collaborativefolders);

    return $collaborativefolders->id;
}

/**
 * Updates an instance of the collaborativefolders in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $collaborativefolders An object from the form in mod_form.php
 * @param mod_collaborativefolders_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function collaborativefolders_update_instance(stdClass $collaborativefolders, mod_collaborativefolders_mod_form $mform = null) {
    global $DB;

    $oldfolder = $DB->get_record('collaborativefolders', array('id' => $collaborativefolders->instance));
    $oldfolder->timemodified = time();
    $oldfolder->intro = $collaborativefolders->intro;
    $oldfolder->introformat = $collaborativefolders->introformat;

    $collaborativefolders->id = $DB->update_record('collaborativefolders', $oldfolder);

    $DB->update_record('collaborativefolders', $collaborativefolders);

    return $collaborativefolders->id;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every collaborativefolders event in the site is checked, else
 * only collaborativefolders events belonging to the course specified are checked.
 * This is only required if the module is generating calendar events.
 *
 * @param int $courseid Course ID
 * @return bool
 */
function collaborativefolders_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$collaborativefolderss = $DB->get_records('collaborativefolders')) {
            return true;
        }
    } else {
        if (!$collaborativefolderss = $DB->get_records('collaborativefolders', array('course' => $courseid))) {
            return true;
        }
    }

    return true;
}

/**
 * Removes an instance of the collaborativefolders from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function collaborativefolders_delete_instance($id) {
    global $DB;

    if (! $collaborativefolders = $DB->get_record('collaborativefolders', array('id' => $id))) {
        return false;
    }
    $groupmode = $DB->get_records('collaborativefolders_group', array('modid' => $collaborativefolders->id));
    $helper = new owncloud_access();

    // In Case no group mode is active the complete Folder is deleted.
    if (empty($groupmode)) {
        $path = $collaborativefolders->id;
        $helper->handle_folder('delete', $path);
    } else {
        foreach ($groupmode as $key => $group) {
            $path = $collaborativefolders->id . '/' . $group->id;
            $helper->handle_folder('delete', $path);
        }
        $path = $collaborativefolders->id;
        $helper->handle_folder('delete', $path);
    }

    // Delete any dependent records here.
    $DB->delete_records('collaborativefolders_group', array('modid' => $collaborativefolders->id));
    $DB->delete_records('collaborativefolders', array('id' => $collaborativefolders->id));

    return true;
}
