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
 * Tests for resourcelibraryfields in courses and modules
 *
 * @package    local_resourcelibrary
 * @copyright  2020 CALL Learning 2020 - Laurent David laurent@call-learning.fr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_resourcelibrary\customfield\course_handler;
use local_resourcelibrary\customfield\coursemodule_handler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests for customfields in courses
 *
 * @package    local_resourcelibrary
 * @copyright  2020 CALL Learning 2020 - Laurent David laurent@call-learning.fr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_resourcelibrary_resourcelibraryfield_testcase extends advanced_testcase {

    /**
     * Set up
     */
    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $dg = self::getDataGenerator();
        $generator = $dg->get_plugin_generator('local_resourcelibrary');
        foreach (array('course', 'coursemodule') as $type) {
            $catid = $generator->create_category([], $type)->get('id');
            $generator->create_field(['categoryid' => $catid, 'type' => 'text', 'shortname' => 'f1'], $type);
            $generator->create_field(['categoryid' => $catid, 'type' => 'checkbox', 'shortname' => 'f2'], $type);
            $generator->create_field(['categoryid' => $catid, 'type' => 'date', 'shortname' => 'f3',
                'configdata' => ['startyear' => 2000, 'endyear' => 3000, 'includetime' => 1]], $type);
            $generator->create_field(['categoryid' => $catid, 'type' => 'select', 'shortname' => 'f4',
                'configdata' => ['options' => "a\nb\nc"]], $type);
            $dg->create_custom_field(['categoryid' => $catid, 'type' => 'textarea', 'shortname' => 'f5']);
        }
    }

    /**
     * Test creating course with resourcelibrary custom fields and retrieving them
     */
    public function test_create_course() {
        global $DB;
        $dg = $this->getDataGenerator();

        $now = time();
        $data = ['shortname' => 'SN', 'fullname' => 'FN',
            'summary' => 'DESC', 'summaryformat' => FORMAT_MOODLE,
            'customfield_f1' => 'some text',
            'customfield_f2' => 1,
            'customfield_f3' => $now,
            'customfield_f4' => 2,
            'customfield_f5_editor' => ['text' => 'test', 'format' => FORMAT_HTML]];
        $c1 = $dg->create_course($data);

        $data['id'] = $c1->id;
        \local_resourcelibrary\locallib\utils::course_update_fields((object) $data);
        // This should be called when creating the course with data.
        // TODO this must be part of an external API also so we can add the Resource Library field after creation.

        $data = course_handler::create()->export_instance_data_object($c1->id);

        $this->assertEquals('some text', $data->f1);
        $this->assertEquals('Yes', $data->f2);
        $this->assertEquals(userdate($now, get_string('strftimedaydatetime')), $data->f3);
        $this->assertEquals('b', $data->f4);
        $this->assertEquals('test', $data->f5);

        $this->assertEquals(5, count($DB->get_records('customfield_data')));

        delete_course($c1->id, false);

        $this->assertEquals(0, count($DB->get_records('customfield_data')));
    }

    /**
     * Test creating module with resourcelibrary custom fields and retrieving them
     */
    public function test_create_module() {
        global $DB;
        $dg = $this->getDataGenerator();

        $now = time();
        $c1 = $dg->create_course(['shortname' => 'SN', 'fullname' => 'FN',
            'summary' => 'DESC', 'summaryformat' => FORMAT_MOODLE]);

        $data = array('course' => $c1->id,
            'customfield_f1' => 'some text',
            'customfield_f2' => 1,
            'customfield_f3' => $now,
            'customfield_f4' => 2,
            'customfield_f5_editor' => ['text' => 'test', 'format' => FORMAT_HTML]);
        $a1 = $dg->create_module('label', (object) $data);

        $data = coursemodule_handler::create()->export_instance_data_object($a1->cmid);

        $this->assertEquals('some text', $data->f1);
        $this->assertEquals('Yes', $data->f2);
        $this->assertEquals(userdate($now, get_string('strftimedaydatetime')), $data->f3);
        $this->assertEquals('b', $data->f4);
        $this->assertEquals('test', $data->f5);

        $this->assertEquals(5, count($DB->get_records('customfield_data')));

        course_delete_module($a1->cmid, false);

        $this->assertEquals(0, count($DB->get_records('customfield_data')));
    }

    /**
     * Test backup and restore of custom fields
     */
    public function test_restore_course_resourcelibraryfields() {
        global $USER;
        $dg = $this->getDataGenerator();
        $data = [
            'shortname' => 'SN',
            'fullname' => 'FN',
            'summary' => 'DESC',
            'summaryformat' => FORMAT_MOODLE,
            'customfield_f1' => 'some text to backup',
            'customfield_f2' => 1];

        $c1 = $dg->create_course($data);

        $data['id'] = $c1->id;
        \local_resourcelibrary\locallib\utils::course_update_fields((object) $data);
        // This should be called when creating the course with data.
        // TODO this must be part of an external API also so we can add the Resource Library field after creation.

        $backupid = $this->backup_course($c1->id);

        // The information is restored but adapted because names are already taken.
        $c2 = $this->restore_course($backupid, 0, $USER->id);

        $data = course_handler::create()->export_instance_data_object($c1->id);
        $this->assertEquals('some text to backup', $data->f1);
        $this->assertEquals('Yes', $data->f2);
    }

    /**
     * Test backup and restore of custom fields
     */
    public function test_restore_module_resourcelibraryields() {
        global $USER;
        $dg = $this->getDataGenerator();
        $now = time();
        $c1 = $dg->create_course([
            'shortname' => 'SN',
            'fullname' => 'FN',
            'summary' => 'DESC',
            'summaryformat' => FORMAT_MOODLE]);
        $data = array('course' => $c1->id,
            'customfield_f1' => 'some text',
            'customfield_f2' => 1,
            'customfield_f3' => $now,
            'customfield_f4' => 2,
            'customfield_f5_editor' => ['text' => 'test', 'format' => FORMAT_HTML]);
        $a1 = $dg->create_module('label', (object) $data);

        $backupid = $this->backup_course($c1->id);

        // The information is restored but adapted because names are already taken.
        $c2 = $this->restore_course($backupid, 0, $USER->id);
        $labels = get_all_instances_in_course('label', $c1);
        $label = reset($labels); // First one.
        $data = coursemodule_handler::create()->export_instance_data_object($label->coursemodule);
        $this->assertEquals('some text', $data->f1);
        $this->assertEquals('Yes', $data->f2);
    }

    /**
     * Backup a course and return its backup ID.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user doing the backup.
     * @return string
     */
    protected function backup_course($courseid, $userid = 2) {
        $backuptempdir = make_backup_temp_directory('');
        $packer = get_file_packer('application/vnd.moodle.backup');

        $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $userid);
        $bc->execute_plan();

        $results = $bc->get_results();
        $results['backup_destination']->extract_to_pathname($packer, "$backuptempdir/core_course_testcase");

        $bc->destroy();
        unset($bc);
        return 'core_course_testcase';
    }

    /**
     * Restore a course.
     *
     * @param int $backupid The backup ID.
     * @param int $courseid The course ID to restore in, or 0.
     * @param int $userid The ID of the user performing the restore.
     * @return stdClass The updated course object.
     */
    protected function restore_course($backupid, $courseid, $userid) {
        global $DB;

        $target = backup::TARGET_CURRENT_ADDING;
        if (!$courseid) {
            $target = backup::TARGET_NEW_COURSE;
            $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
            $courseid = restore_dbops::create_new_course('Tmp', 'tmp', $categoryid);
        }

        $rc = new restore_controller($backupid, $courseid, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userid, $target);
        $target == backup::TARGET_NEW_COURSE ?: $rc->get_plan()->get_setting('overwrite_conf')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();

        $course = $DB->get_record('course', array('id' => $rc->get_courseid()));

        $rc->destroy();
        unset($rc);
        return $course;
    }
}
