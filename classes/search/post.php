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
 * ReactForum posts search area
 *
 * @package    mod_reactforum
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reactforum\search;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/reactforum/lib.php');

/**
 * ReactForum posts search area.
 *
 * @package    mod_reactforum
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post extends \core_search\base_mod {

    /**
     * @var array Internal quick static cache.
     */
    protected $reactforumsdata = array();

    /**
     * @var array Internal quick static cache.
     */
    protected $discussionsdata = array();

    /**
     * @var array Internal quick static cache.
     */
    protected $postsdata = array();

    /**
     * Returns recordset containing required data for indexing reactforum posts.
     *
     * @param int $modifiedfrom timestamp
     * @return moodle_recordset
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;

        $sql = 'SELECT fp.*, f.id AS reactforumid, f.course AS courseid
                  FROM {reactforum_posts} fp
                  JOIN {reactforum_discussions} fd ON fd.id = fp.discussion
                  JOIN {reactforum} f ON f.id = fd.reactforum
                 WHERE fp.modified >= ? ORDER BY fp.modified ASC';
        return $DB->get_recordset_sql($sql, array($modifiedfrom));
    }

    /**
     * Returns the document associated with this post id.
     *
     * @param stdClass $record Post info.
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = array()) {

        try {
            $cm = $this->get_cm('reactforum', $record->reactforumid, $record->courseid);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->subject, false));
        $doc->set('content', content_to_text($record->message, $record->messageformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->courseid);
        $doc->set('userid', $record->userid);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->modified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->created)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

    /**
     * Add the reactforum post attachments.
     *
     * @param document $document The current document
     * @return null
     */
    public function attach_files($document) {
        global $DB;

        $postid = $document->get('itemid');

        try {
            $post = $this->get_post($postid);
        } catch (\dml_missing_record_exception $e) {
            unset($this->postsdata[$postid]);
            debugging('Could not get record to attach files to '.$document->get('id'), DEBUG_DEVELOPER);
            return;
        }

        // Because this is used during indexing, we don't want to cache posts. Would result in memory leak.
        unset($this->postsdata[$postid]);

        $cm = $this->get_cm('reactforum', $post->reactforum, $document->get('courseid'));
        $context = \context_module::instance($cm->id);

        // Get the files and attach them.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_reactforum', 'attachment', $postid, "filename", false);
        foreach ($files as $file) {
            $document->add_stored_file($file);
        }
    }

    /**
     * Whether the user can access the document or not.
     *
     * @throws \dml_missing_record_exception
     * @throws \dml_exception
     * @param int $id ReactForum post id
     * @return bool
     */
    public function check_access($id) {
        global $USER;

        try {
            $post = $this->get_post($id);
            $reactforum = $this->get_reactforum($post->reactforum);
            $discussion = $this->get_discussion($post->discussion);
            $cminfo = $this->get_cm('reactforum', $reactforum->id, $reactforum->course);
            $cm = $cminfo->get_course_module_record();
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        // Recheck uservisible although it should have already been checked in core_search.
        if ($cminfo->uservisible === false) {
            return \core_search\manager::ACCESS_DENIED;
        }

        if (!reactforum_user_can_see_post($reactforum, $discussion, $post, $USER, $cm)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Link to the reactforum post discussion
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        // The post is already in static cache, we fetch it in self::search_access.
        $post = $this->get_post($doc->get('itemid'));
        return new \moodle_url('/mod/reactforum/discuss.php', array('d' => $post->discussion));
    }

    /**
     * Link to the reactforum.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        return new \moodle_url('/mod/reactforum/view.php', array('id' => $contextmodule->instanceid));
    }

    /**
     * Returns the specified reactforum post from its internal cache.
     *
     * @throws \dml_missing_record_exception
     * @param int $postid
     * @return stdClass
     */
    protected function get_post($postid) {
        if (empty($this->postsdata[$postid])) {
            $this->postsdata[$postid] = reactforum_get_post_full($postid);
            if (!$this->postsdata[$postid]) {
                throw new \dml_missing_record_exception('reactforum_posts');
            }
        }
        return $this->postsdata[$postid];
    }

    /**
     * Returns the specified reactforum checking the internal cache.
     *
     * Store minimal information as this might grow.
     *
     * @throws \dml_exception
     * @param int $reactforumid
     * @return stdClass
     */
    protected function get_reactforum($reactforumid) {
        global $DB;

        if (empty($this->reactforumsdata[$reactforumid])) {
            $this->reactforumsdata[$reactforumid] = $DB->get_record('reactforum', array('id' => $reactforumid), '*', MUST_EXIST);
        }
        return $this->reactforumsdata[$reactforumid];
    }

    /**
     * Returns the discussion checking the internal cache.
     *
     * @throws \dml_missing_record_exception
     * @param int $discussionid
     * @return stdClass
     */
    protected function get_discussion($discussionid) {
        global $DB;

        if (empty($this->discussionsdata[$discussionid])) {
            $this->discussionsdata[$discussionid] = $DB->get_record('reactforum_discussions',
                array('id' => $discussionid), '*', MUST_EXIST);
        }
        return $this->discussionsdata[$discussionid];
    }
}