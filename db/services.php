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

$functions = array(
    'wsflashcards_get_courses' => array(
        'classname' => 'local_wsflashcards_external',
        'methodname' => 'get_courses',
        'classpath' => 'local/wsflashcards/externallib.php',
        'description' => 'Returns all courses for the current user, which have flashcards with active questions available.',
        'type' => 'read',
        'loginrequired' => true,
        'services' => array('wsflashcards')
    ),
    'wsflashcards_get_questions' => array(
        'classname' => 'local_wsflashcards_external',
        'methodname' => 'get_questions',
        'classpath' => 'local/wsflashcards/externallib.php',
        'description' => 'Returns an equal amount of questions from each activity given.',
        'type' => 'read',
        'loginrequired' => true,
        'services' => array('wsflashcards')
    ),
    'wsflashcards_set_answers' => array(
        'classname' => 'local_wsflashcards_external',
        'methodname' => 'set_answers',
        'classpath' => 'local/wsflashcards/externallib.php',
        'description' => 'Moves questions into their next box.',
        'type' => 'write',
        'loginrequired' => true,
        'services' => array('wsflashcards')
    )
);

$services = array(
    'wsflashcards' => array(
        'functions' => array(
            'wsflashcards_get_courses',
            'wsflashcards_get_questions',
            'wsflashcards_set_answers'
        ),
        'shortname' => 'wsflashcards',
        'requiredcapability' => 'mod/flashcards:webservice',
        'restrictedusers' => 0,
        'enabled' => 1,
    )
);
