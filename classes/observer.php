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
 * @package    block_edusupport
 * @copyright  2020 Center for Learningmangement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_edusupport;

defined('MOODLE_INTERNAL') || die;

class observer {
    public static function event($event) {
        global $CFG, $DB, $OUTPUT;

        //error_log("OBSERVER EVENT: " . print_r($event, 1));
        $entry = (object)$event->get_data();

        if (substr($entry->eventname, 0, strlen("\\mod_forum\\event\\post_")) == "\\mod_forum\\event\\post_") {
            $post = $DB->get_record("forum_posts", array("id" => $entry->objectid));
            $discussion = $DB->get_record("forum_discussions", array("id" => $post->discussion));
        } else {
            $discussion = $DB->get_record("forum_discussions", array("id" => $entry->objectid));
            $post = $DB->get_record("forum_posts", array("discussion" => $discussion->id, "parent" => 0));
        }
        $forum = $DB->get_record("forum", array("id" => $discussion->forum));
        $course = $DB->get_record("course", array("id" => $forum->course));
        $issue = $DB->get_record('block_edusupport_issues', array('discussionid' => $discussion->id));
        if (empty($issue->id)) return;
        $author = $DB->get_record('user', array('id' => $post->userid));
        // enhance post data.
        $post->wwwroot = $CFG->wwwroot;
        $post->authorfullname = \fullname($author);
        $post->authorlink = $CFG->wwwroot . '/user/profile.php?id=' . $author->id;
        $post->authorpicture = $OUTPUT->user_picture($author, array('size' => 200));
        $post->postdate = strftime('%d. %B %Y, %H:%m', $post->created);

        $post->coursename = $course->fullname;
        $post->forumname = $forum->name;
        $post->discussionname = $discussion->name;

        $post->issuelink = $CFG->wwwroot . '/blocks/edusupport/issue.php?d=' . $discussion->id;
        $post->replylink = $CFG->wwwroot . '/blocks/edusupport/issue.php?d=' . $discussion->id . '&replyto=' . $post->id;

        // Get all subscribers
        $fromuser = \core_user::get_support_user();
        $subscribers = $DB->get_records('block_edusupport_subscr', array('discussionid' => $discussion->id));

        foreach ($subscribers AS $subscriber) {
            $touser = $DB->get_record('user', array('id' => $subscriber->userid));

            // Send notification
            $subject = $discussion->name;
            $mailhtml =  $OUTPUT->render_from_template('block_edusupport/post_mailhtml', $post);
            $mailtext =  $OUTPUT->render_from_template('block_edusupport/post_mailtext', $post);

            \email_to_user($touser, $author, $subject, $mailtext, $mailhtml, "", true);
        }

        return true;
    }
}
