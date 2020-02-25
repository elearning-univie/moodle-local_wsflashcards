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
global $CFG;
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
                        'q_amount' => new external_value(PARAM_INT, 'Amount of questions', VALUE_DEFAULT, 0),
                        'a_unique_id' => new external_multiple_structure(
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
                        'activities' => new external_multiple_structure(
                                new external_single_structure([
                                        'a_unique_id' => new external_value(PARAM_INT, 'Activity ID'),
                                        'questions' => new external_multiple_structure(
                                                new external_single_structure([
                                                        'q_unique_id' => new external_value(PARAM_INT, 'Question ID'),
                                                        'q_known' => new external_value(PARAM_INT,
                                                                'Boolean value for the answer. 1 if correct, 0 if wrong'),
                                                        'q_answer_date' => new external_value(PARAM_TEXT, 'Answer date')
                                                ])
                                        )
                                ])
                        )
                )
        );
    }

    /**
     * Returns all courses for the current user, which have flashcards with active questions available.
     *
     * @return int
     * @throws dml_exception
     */
    public static function get_courses() {
        global $DB, $USER;

        $sql = "SELECT c.fullname AS cname, c.id AS cid, f.name AS aname, count(*) AS qcount, f.id AS aid
                  FROM {flashcards} f
                  JOIN {flashcards_q_stud_rel} fs ON f.id = fs.flashcardsid
                  JOIN {course} c ON f.course = c.id
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'flashcards'
                 WHERE fs.studentid = :userid
                   AND c.visible = 1
                   AND cm.visible = 1
              GROUP BY c.fullname, c.id, f.id, f.name";

        $records = $DB->get_recordset_sql($sql, ['userid' => $USER->id]);
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

        if ($courseid != 0) {
            $courses[] = array('c_name' => $cname, 'c_unique_id' => $cid, 'activity_col' => $activities);
        }

        return $courses;
    }

    /**
     * Returns an equal amount of questions from each activity given in the $aid array. If one activity has not enough
     * questions all other activities fill up the missing amount.
     *
     * @param int $qamount
     * @param array $aid activityids
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_questions($qamount, $aid) {
        global $DB, $USER;

        list($insql, $inids) = $DB->get_in_or_equal($aid);
        $sql = "SELECT cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = 'flashcards'
                 WHERE cm.visible = 1
                   AND cm.instance $insql";
        $aid = $DB->get_fieldset_sql($sql, $inids);
        $returnvalues = array();
        $countaid = count($aid);
        $values = array();
        $countaidleft = $countaid;

        if ($qamount > 100 || $qamount <= 0) {
            $qcount = 50;
        } else {
            $qcount = $qamount;
        }

        if ($countaid <= 100) {
            if ($countaid > $qcount) {
                $qcount = $countaid;
            }
        } else {
            throw new exception('Too many activities requested');
        }

        $split = intdiv($qcount, $countaid);
        $moddiff = $qcount % $countaid;

        list($insql, $aids) = $DB->get_in_or_equal($aid, SQL_PARAMS_NAMED);

        $sql = "SELECT fsr.flashcardsid AS fid, count(*) AS qcount
                  FROM {flashcards_q_stud_rel} fsr
                 WHERE fsr.studentid = :userid
                   AND fsr.flashcardsid $insql
              GROUP BY fsr.flashcardsid
              ORDER BY qcount";

        $records = $DB->get_recordset_sql($sql, ['userid' => $USER->id] + $aids);

        foreach ($records as $record) {
            if ($record->qcount <= $split) {
                $values[$record->fid] = $record->qcount;
                $moddiff += $split - $record->qcount;
            } else {
                $sharepool = intdiv($moddiff, $countaidleft);

                if ($record->qcount <= ($split + $sharepool)) {
                    $values[$record->fid] = $record->qcount;
                } else {
                    $values[$record->fid] = ($split + $sharepool);
                    $moddiff -= $sharepool;
                }
            }
            $countaidleft--;
        }

        foreach ($aid as $activityid) {
            $questioncountleft = $values[$activityid];

            $sql = "SELECT q.id AS qid, q.questiontext AS questiontext, qa.answer AS questionanswer, c.fullname AS cname, f.name AS aname
                      FROM {flashcards_q_stud_rel} fsr
                      JOIN {question} q ON fsr.questionid = q.id
                      JOIN {question_answers} qa ON q.id = qa.question
                      JOIN {flashcards} f ON f.id = fsr.flashcardsid
                      JOIN {course} c ON f.course = c.id
                     WHERE fsr.studentid = :userid
                       AND fsr.flashcardsid = :aid";

            $questions = array();
            $cname = null;
            $aname = null;

            $records = $DB->get_recordset_sql($sql, ['userid' => $USER->id, 'aid' => $activityid]);

            foreach ($records as $record) {
                $questions[] = array(
                        'q_unique_id' => $record->qid,
                        'q_front_data' => $record->questiontext,
                        'q_back_data' => $record->questionanswer);

                if (is_null($cname)) {
                    $cname = $record->cname;
                    $aname = $record->aname;
                }

                $questioncountleft--;

                if ($questioncountleft == 0) {
                    break;
                }
            }

            $returnvalues[] =
                    array('c_name' => $cname, 'a_name' => $aname, 'a_unique_id' => $activityid, 'questions' => $questions);
        }

        return $returnvalues;
    }

    /**
     * Moves all questions from box 0 to box 1 for the activity
     *
     * @param int $activities
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function set_answers($activities) {
        global $DB, $USER;

        foreach ($activities as $activity) {
            $correctids = array();
            $wrongids = array();
            $aid = $activity['a_unique_id'];

            foreach ($activity['questions'] as $question) {
                if ($question['q_known'] == 1) {
                    $correctids[] = $question['q_unique_id'];
                } else {
                    $wrongids[] = $question['q_unique_id'];
                }
            }

            if (!empty($correctids)) {
                list($inids, $cqids) = $DB->get_in_or_equal($correctids, SQL_PARAMS_NAMED);
                $sql = "UPDATE {flashcards_q_stud_rel}
                           SET tries = tries+1,
                               currentbox = case when currentbox < 5 then currentbox+1 else 5 end
                         WHERE studentid = :userid AND flashcardsid = :aid AND questionid $inids";
                $DB->execute($sql, ['userid' => $USER->id, 'aid' => $aid] + $cqids);
            }

            if (!empty($wrongids)) {
                list($inids, $wqids) = $DB->get_in_or_equal($wrongids, SQL_PARAMS_NAMED);
                $sql = "UPDATE {flashcards_q_stud_rel}
                           SET tries = tries+1,
                               currentbox = 1,
                               wronganswercount = wronganswercount+1
                         WHERE studentid = :userid AND flashcardsid = :aid AND questionid $inids";
                $DB->execute($sql, ['userid' => $USER->id, 'aid' => $aid] + $wqids);
            }
        }
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
        return new external_multiple_structure(
                new external_single_structure([
                        'c_name' => new external_value(PARAM_TEXT, 'Course name'),
                        'a_unique_id' => new external_value(PARAM_INT, 'Activity ID'),
                        'a_name' => new external_value(PARAM_TEXT, 'Activity name'),
                        'questions' => new external_multiple_structure(
                                new external_single_structure([
                                        'q_unique_id' => new external_value(PARAM_INT, 'Question ID'),
                                        'q_front_data' => new external_value(PARAM_RAW, 'Question text'),
                                        'q_back_data' => new external_value(PARAM_RAW, 'Question answer')
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
    public static function set_answers_returns() {
        return null;
    }
}