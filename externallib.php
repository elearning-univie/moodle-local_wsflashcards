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

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/locallib.php');

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

        local_wsflashcards_check_for_orphan_or_hidden_questions();

        $sql = "SELECT c.fullname AS cname, c.id AS cid, f.name AS aname, count(fs.id) AS qcount, f.id AS aid, cm.id AS cmid
                  FROM {course} c
                  JOIN {flashcards} f ON c.id = f.course
                  JOIN {course_modules} cm ON f.id = cm.instance
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'flashcards'
             LEFT JOIN {flashcards_q_stud_rel} fs ON f.id = fs.flashcardsid AND fs.studentid = ?
                 WHERE c.id IN (SELECT DISTINCT e.courseid FROM {user_enrolments} ue JOIN {enrol} e ON ue.enrolid = e.id WHERE ue.userid = ?)
                   AND c.visible = 1
                   AND cm.visible = 1
              GROUP BY c.fullname, c.id, f.id, f.name, cm.id";

        $records = $DB->get_recordset_sql($sql, [$USER->id, $USER->id]);
        $courseid = 0;
        $courses = array();
        $activities = array();
        $cname = "";

        foreach ($records as $record) {
            $context = context_module::instance($record->cmid, MUST_EXIST);

            if (!has_capability('mod/flashcards:view', $context)) {
                continue;
            }

            if ($courseid != $record->cid) {
                if ($courseid != 0) {
                    $courseimageb64 = local_wsflashcards_encode_course_image($courseid);
                    $courses[] = array('c_name' => $cname, 'c_unique_id' => $courseid, 'c_image' => $courseimageb64, 'activity_col' => $activities);
                    $activities = array();
                }

                $cname = $record->cname;
                $courseid = $record->cid;
            }

            $activity = array('a_name' => $record->aname, 'a_quest_count' => $record->qcount, 'a_unique_id' => $record->aid, 'cm_id' => $record->cmid);
            $activities[] = $activity;
        }

        if ($courseid != 0) {
            $courseimageb64 = local_wsflashcards_encode_course_image($courseid);
            $courses[] = array('c_name' => $cname, 'c_unique_id' => $courseid, 'c_image' => $courseimageb64, 'activity_col' => $activities);
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
        global $DB, $USER, $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $params = self::validate_parameters(self::get_questions_parameters(), array('q_amount' => $qamount, 'a_unique_id' => $aid));
        local_wsflashcards_check_for_orphan_or_hidden_questions();

        list($insql, $inids) = $DB->get_in_or_equal($params['a_unique_id']);
        $sql = "SELECT cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = 'flashcards'
                  JOIN {course} c ON c.id = cm.course
                 WHERE c.visible = 1
                   AND cm.visible = 1
                   AND cm.instance $insql";
        $aid = $DB->get_fieldset_sql($sql, $inids);

        $returnvalues = array();
        $values = array();
        $countaid = count($aid);

        if ($countaid == 0) {
            return array();
        }

        if ($params['q_amount'] > 100 || $params['q_amount'] <= 0) {
            $qcount = 50;
        } else {
            $qcount = $params['q_amount'];
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
                $sharepool = intdiv($moddiff, $countaid);

                if ($record->qcount <= ($split + $sharepool)) {
                    $values[$record->fid] = $record->qcount;
                } else {
                    $values[$record->fid] = ($split + $sharepool);
                    $moddiff -= $sharepool;
                }
            }
            $countaid--;
        }

        foreach ($aid as $activityid) {
            $questioncountleft = $values[$activityid];
            $questions = array();
            $cname = null;
            $aname = null;

            $sql = "SELECT fsr.questionid AS qid, c.fullname AS cname, f.name AS aname
                      FROM {flashcards_q_stud_rel} fsr
                      JOIN {flashcards} f ON f.id = fsr.flashcardsid
                      JOIN {course} c ON f.course = c.id
                     WHERE fsr.studentid = :userid
                       AND fsr.flashcardsid = :aid";

            $records = $DB->get_recordset_sql($sql, ['userid' => $USER->id, 'aid' => $activityid]);
            $cm = get_coursemodule_from_instance("flashcards", $activityid);
            $context = context_module::instance($cm->id, MUST_EXIST);

            try {
                self::validate_context($context);
                require_capability('mod/flashcards:studentview', $context);
            } catch (Exception $e) {
                $exceptionparam = new stdClass();
                $exceptionparam->message = $e->getMessage();
                throw new moodle_exception('errorcoursecontextnotvalid', 'webservice', '', $exceptionparam);
            }

            $quba = question_engine::make_questions_usage_by_activity('mod_flashcards', $context);
            $quba->set_preferred_behaviour('immediatefeedback');
            $options = new question_display_options();
            $options->marks = question_display_options::MAX_ONLY;
            $options->markdp = 2;
            $options->feedback = question_display_options::HIDDEN;
            $options->generalfeedback = question_display_options::HIDDEN;

            foreach ($records as $record) {

                $question = question_bank::load_question($record->qid);
                $quba->add_question($question, 1);

                $qids[] = $record->qid;

                if (is_null($cname)) {
                    $cname = $record->cname;
                    $aname = $record->aname;
                }

                $questioncountleft--;

                if ($questioncountleft == 0) {
                    break;
                }
            }

            $quba->start_all_questions();
            question_engine::save_questions_usage_by_activity($quba);

            $dom = new DOMDocument();

            for ($i = 1; $i <= $values[$activityid]; $i++) {
                $questiontext = utf8_decode($quba->render_question($i, $options));
                $questiontext = local_wsflashcards_encode_question_images($questiontext);

                $dom->loadHtml($questiontext);
                $xpath = new DOMXpath($dom);

                $query = './/div[contains(concat(" ", normalize-space(@class), " "), " qflashcard-question ")]/child::*';
                $div = $xpath->evaluate($query);

                $question = '';
                for ($j = 0; $j < $div->length; $j++) {
                    $question .= $dom->saveHTML($div->item($j));
                }

                $query = './/div[contains(concat(" ", normalize-space(@class), " "), " qflashcard-answer ")]/child::*';
                $div = $xpath->evaluate($query);

                $questionanswer = '';
                for ($j = 0; $j < $div->length; $j++) {
                    $questionanswer .= $dom->saveHTML($div->item($j));
                }

                $questions[] = array(
                    'q_unique_id' => $qids[$i - 1],
                    'q_front_data' => $question,
                    'q_back_data' => $questionanswer);
            }

            $returnvalues[] =
                array('c_name' => $cname, 'a_name' => $aname, 'a_unique_id' => $activityid, 'questions' => $questions);
        }

        return $returnvalues;
    }

    /**
     * Moves questions into their next box.
     *
     * @param int $activities
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function set_answers($activities) {
        global $DB, $USER;
        $params = self::validate_parameters(self::set_answers_parameters(), array('activities' => $activities));

        foreach ($params['activities'] as $activity) {
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
                'c_image' => new external_value(PARAM_TEXT, 'Course image'),
                'activity_col' => new external_multiple_structure(
                    new external_single_structure([
                        'a_name' => new external_value(PARAM_TEXT, 'Activity name'),
                        'a_quest_count' => new external_value(PARAM_INT, 'Activity question count'),
                        'a_unique_id' => new external_value(PARAM_INT, 'Activity ID'),
                        'cm_id' => new external_value(PARAM_INT, 'Context module ID of the activity')
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