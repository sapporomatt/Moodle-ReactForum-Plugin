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
 * Tests for mod_reactforum_backup_reactforum_activity_task.
 *
 * @package    mod_reactforum
 * @category   test
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

require_once($CFG->dirroot . '/backup/moodle2/backup_stepslib.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_activity_task.class.php');
require_once($CFG->dirroot . '/mod/reactforum/backup/moodle2/backup_reactforum_activity_task.class.php');

/**
 * Tests for mod_reactforum_backup_reactforum_activity_task.
 *
 * @package    mod_reactforum
 * @category   test
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_reactforum_backup_reactforum_activity_task_testcase extends advanced_testcase {

    /**
     * Test the encoding of reactforum content links.
     *
     * @param string $content       The incoming content
     * @param string $expectation   The expected result
     *
     * @dataProvider encode_content_links_provider
     */
    public function test_encode_content_links($content, $expectation) {
        $this->assertEquals($expectation, backup_reactforum_activity_task::encode_content_links($content));
    }

    public function encode_content_links_provider() {
        global $CFG;
        $altwwwroot = 'http://invalid.example.com/';
        return [
            'Link to the list of reactforums for current wwwroot' => [
                sprintf('%s/mod/reactforum/index.php?id=42', $CFG->wwwroot),
                '$@REACTFORUMINDEX*42@$',
            ],
            'Link to reactforum view by moduleid for current wwwroot' => [
                sprintf('%s/mod/reactforum/view.php?id=29', $CFG->wwwroot),
                '$@REACTFORUMVIEWBYID*29@$',
            ],
            'Link to reactforum view by reactforumid for current wwwroot' => [
                sprintf('%s/mod/reactforum/view.php?f=31', $CFG->wwwroot),
                '$@REACTFORUMVIEWBYF*31@$',
            ],
            'Link to reactforum discussion with parent syntax for current wwwroot' => [
                sprintf('%s/mod/reactforum/discuss.php?d=26&parent=99', $CFG->wwwroot),
                '$@REACTFORUMDISCUSSIONVIEWPARENT*26*99@$',
            ],
            'Link to reactforum discussion with parent syntax for current wwwroot encoded' => [
                sprintf('%s/mod/reactforum/discuss.php?d=26&amp;parent=99', $CFG->wwwroot),
                '$@REACTFORUMDISCUSSIONVIEWPARENT*26*99@$',
            ],
            'Link to reactforum discussion with relative syntax for current wwwroot' => [
                sprintf('%s/mod/reactforum/discuss.php?d=1040#9930', $CFG->wwwroot),
                '$@REACTFORUMDISCUSSIONVIEWINSIDE*1040*9930@$',
            ],
            'Link to reactforum discussion by discussionid for current wwwroot' => [
                sprintf('%s/mod/reactforum/discuss.php?d=9304', $CFG->wwwroot),
                '$@REACTFORUMDISCUSSIONVIEW*9304@$',
            ],
            'Link to the list of reactforums for other wwwroot' => [
                sprintf('%s/mod/reactforum/index.php?id=42', $altwwwroot),
                sprintf('%s/mod/reactforum/index.php?id=42', $altwwwroot),
            ],
            'Link to reactforum view by moduleid for other wwwroot' => [
                sprintf('%s/mod/reactforum/view.php?id=29', $altwwwroot),
                sprintf('%s/mod/reactforum/view.php?id=29', $altwwwroot),
            ],
            'Link to reactforum view by reactforumid for other wwwroot' => [
                sprintf('%s/mod/reactforum/view.php?f=31', $altwwwroot),
                sprintf('%s/mod/reactforum/view.php?f=31', $altwwwroot),
            ],
            'Link to reactforum discussion with parent syntax for other wwwroot' => [
                sprintf('%s/mod/reactforum/discuss.php?d=26&parent=99', $altwwwroot),
                sprintf('%s/mod/reactforum/discuss.php?d=26&parent=99', $altwwwroot),
            ],
            'Link to reactforum discussion with parent syntax for other wwwroot encoded' => [
                sprintf('%s/mod/reactforum/discuss.php?d=26&amp;parent=99', $altwwwroot),
                sprintf('%s/mod/reactforum/discuss.php?d=26&amp;parent=99', $altwwwroot),
            ],
            'Link to reactforum discussion with relative syntax for other wwwroot' => [
                sprintf('%s/mod/reactforum/discuss.php?d=1040#9930', $altwwwroot),
                sprintf('%s/mod/reactforum/discuss.php?d=1040#9930', $altwwwroot),
            ],
            'Link to reactforum discussion by discussionid for other wwwroot' => [
                sprintf('%s/mod/reactforum/discuss.php?d=9304', $altwwwroot),
                sprintf('%s/mod/reactforum/discuss.php?d=9304', $altwwwroot),
            ],
        ];
    }
}
