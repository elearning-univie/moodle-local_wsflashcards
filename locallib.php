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
 * WS flashcards lib
 *
 * @package    local_wsflashcards
 * @copyright  2019 University of Vienna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * encodes the first image of the course to base64 and returns the encoded image
 *
 * @param int $courseid
 * @return string
 * @throws coding_exception
 */
function local_wsflashcards_encode_course_image($courseid) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $fs = get_file_storage();
    $context = context_course::instance($courseid);
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0);

    foreach ($files as $f) {
        if ($f->is_valid_image()) {
            $courseimageb64 = base64_encode($f->get_content());
        }
    }
    return $courseimageb64;
}

/**
 * encodes all images in a question to base64
 *
 * @param string $questiontext
 * @return string|string[]
 */
function local_wsflashcards_encode_question_images($questiontext) {
    preg_match_all('/<img[^>]+>/i', $questiontext, $images);

    if (!empty($images)) {
        foreach ($images[0] as $image) {
            preg_match('/pluginfile.php\/(.*?)"/', $image, $imagesrc);

            if ($imagesrc[1] != '') {
                $urlpath = explode('/', $imagesrc[1]);
                $fs = get_file_storage();

                // /$contextid/$component/$filearea/$filepath/filename
                $fullpath = "/$urlpath[0]/$urlpath[1]/$urlpath[2]/$urlpath[5]/$urlpath[6]";

                if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                    send_file_not_found();
                } else if ($file->is_valid_image()) {
                    $encodedimage = '<img src="data:image/jpeg;charset=utf-8;base64,' .
                            base64_encode($file->get_content()) . '" />';
                    $questiontext = str_replace($image, $encodedimage, $questiontext);
                }
            }
        }
    }

    return $questiontext;
}

/**
 * Deletes records from flashcards_q_stud_rel when the question got deleted
 * @throws dml_exception
 */
function local_wsflashcards_check_for_orphan_or_hidden_questions() {
    global $USER, $DB;

    $sql = "questionid NOT IN (SELECT id FROM {question} WHERE hidden = 0) AND studentid = :userid";

    $DB->delete_records_select('flashcards_q_stud_rel', $sql, array('userid' => $USER->id));
}