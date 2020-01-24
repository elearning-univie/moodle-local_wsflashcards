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
 * External service definition
 *
 * @package    local_wsflashcards
 * @copyright  2020 University of Vienna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * Class local_wsflashcards_external
 *
 * @copyright  2020 University of Vienna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_wsflashcards_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_courses_parameters() {
        return new external_function_parameters(
                array()
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_questions_parameters() {
        return new external_function_parameters(
            array(
                'q_amount' => new external_value(PARAM_INT,'Amount of questions'),
                'a_unique_id'=> new external_multiple_structure(
                    new external_value(PARAM_INT, 'Activity ID'), 'Array of Activity IDs which should be loaded.'
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function set_answers_parameters() {
        return new external_function_parameters(
                array(
                        'flashcardsid' => new external_value(PARAM_INT, 'id of activity')
                )
        );
    }

    /**
     * Moves the question into the next box if the answer was correct, otherwise to box 1
     * @return int
     * @throws dml_exception
     */
    public static function get_courses() {
        global $DB, $USER;

        $sql = "SELECT c.fullname AS cname, c.id AS cid, f.name AS aname, count(*) AS qcount, f.id AS aid " .
                "FROM mdl_flashcards f " .
                "INNER JOIN mdl_flashcards_q_stud_rel fs ON f.id = fs.flashcardsid " .
                "INNER JOIN mdl_course c ON f.course = c.id " .
                "WHERE fs.studentid = :userid " .
                "GROUP BY c.fullname, c.id, f.id, f.name";

        $records =  $DB->get_recordset_sql($sql, ['userid' => $USER->id]);
        $courseid = 0;
        $courses = array();
        $activities = array();
        $cname = "";
        $cid = "";

        foreach ($records as $record) {
            if ($courseid != $record->cid) {
                if ($courseid != 0) {
                    $courses[] = array('c_name' => $cname, 'c_unique_id' => $cid, 'activity_col' => $activities);
                    $activities = array();
                }

                $cname = $record->cname;
                $cid = $record->cid;
                $courseid = $record->cid;
            }

            $activity = array('a_name' => $record->aname, 'a_quest_count' => $record->qcount, 'a_unique_id' => $record->aid);
            $activities[] = $activity;
        }

        $courses[] = array('c_name' => $cname, 'c_unique_id' => $cid, 'activity_col' => $activities);

        return $courses;
    }

    /**
     * Moves all questions from box 0 to box 1 for the activity
     *
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_questions() {
        return 1;
    }

    /**
     * Moves all questions from box 0 to box 1 for the activity
     *
     * @param int $flashcardsid
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function set_answers($flashcardsid) {
        return $flashcardsid;
    }

    /**
     * Returns return value description
     *
     * @return external_value
     */
    public static function get_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                    'c_name' => new external_value(PARAM_TEXT, 'Course name'),
                    'c_unique_id' => new external_value(PARAM_INT, 'Course ID'),
                    'activity_col' => new external_multiple_structure(
                    new external_single_structure([
                        'a_name' => new external_value(PARAM_TEXT, 'Activity name'),
                        'a_quest_count' => new external_value(PARAM_INT, 'Activity question count'),
                        'a_unique_id' => new external_value(PARAM_INT, 'Activity ID')
                    ])
                )
            ])
        );
    }

    /**
     * Returns return value description
     *
     * @return external_value
     */
    public static function get_questions_returns() {
        return new external_value(PARAM_INT, 'new question');
    }

    /**
     * Returns return value description
     *
     * @return external_value
     */
    public static function set_answers_returns() {
        return new external_value(PARAM_INT, 'new question');
    }
}