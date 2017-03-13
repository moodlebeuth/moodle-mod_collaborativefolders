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
 * Ad hoc task for the creation of group folders in ownCloud.
 *
 * @package    mod_collaborativefolders
 * @copyright  2017 Westfälische Wilhelms-Universität Münster (WWU Münster)
 * @author     Projektseminar Uni Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_collaborativefolders\task;

defined('MOODLE_INTERNAL') || die;

use mod_collaborativefolders\event\folders_created;
use mod_collaborativefolders\owncloud_access;
use moodle_url;

require_once($CFG->libdir . '/modinfolib.php');
require_once($CFG->libdir . '/accesslib.php');

class collaborativefolders_create extends \core\task\adhoc_task {

    public function execute() {
        $returnurl = new moodle_url('/admin/settings.php?section=modsettingcollaborativefolders', [
                'callback'  => 'yes',
                'sesskey'   => sesskey(),
        ]);

        $oc = new owncloud_access($returnurl);
        $data = $this->get_custom_data();

        foreach ($data as $key => $value) {
            $code = $oc->handle_folder('make', $value);
            if ($code == false) {
                throw new \coding_exception('Folder ' . $value . ' not created.');
            } else {
                mtrace('Folder: ' . $value . ', Code: ' . $code);
                if (($code != 201) && ($code != 405)) {
                    throw new \coding_exception('Folder ' . $value . ' not created.');
                }
            }
        }

        //list ($course, $cm) = get_course_and_cm_from_cmid($data['cmid'], 'collaborativefolders');
        $params = array(
                'context' => \context_system::instance(),
                'objectid' => 10
        );
        $done = folders_created::create($params);
        $done->trigger();
    }
}