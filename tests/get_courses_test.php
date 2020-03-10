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
 * wsflashcards get_courses function
 *
 * @package    local_wsflashcards
 * @copyright  2020 University of Vienna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('CLI_SCRIPT') || die ();
defined('MOODLE_INTERNAL') || die ();
require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir.'/clilib.php');
require('wsclientlib.php');


list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'domainname' => 'https://moodletest.univie.ac.at/',
    'username' => 'ottoh20',
    'password' => 'Ffe95qGUbel3xec7',
    'activity' => 49,
    'question' => 3238201,
    'known' => 1,
], [
    'h' => 'help'
]);


$domainname = optional_param('domainname', 'https://moodletest.univie.ac.at/', PARAM_URL);
$username = optional_param('username', 'ottoh20', PARAM_USERNAME);
$password = optional_param('password', 'Ffe95qGUbel3xec7', PARAM_TEXT);

$tokenurl = $domainname . "login/token.php?username=$username&password=$password&service=wsflashcards";

$result = callws($tokenurl);

// grab URL and pass it to the browser
$token = json_decode($result, true)['token'];

$url = $domainname . "webservice/rest/server.php?wstoken=$token&wsfunction=wsflashcards_get_courses&moodlewsrestformat=json";

print(callws($url) . "\n");