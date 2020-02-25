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
 * test for get_questions function
 *
 * @package    local_wsflashcards
 * @copyright  2020 University of Vienna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir.'/clilib.php');
require('wsclientlib.php');

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'domainname' => 'https://moodletest.univie.ac.at/',
    'username' => 'ottoh20',
    'password' => 'Ffe95qGUbel3xec7',
    'activities' => "49,52",
    'amount' => '10',
], [
    'h' => 'help'
]);

$tokenurl = $options['domainname'] . "login/token.php?username=" . $options['username'] . "&password=" . $options['password'] . "&service=wsflashcards";

$result = callws($tokenurl);

// grab URL and pass it to the browser
$token = json_decode($result, true)['token'];

$url = $options['domainname'] . "webservice/rest/server.php?wstoken=$token&wsfunction=wsflashcards_get_questions&moodlewsrestformat=json&q_amount=" . $options['amount'];
$activities = explode(',', $options['activities']);
foreach ($activities as $key => $value ) {
    $url .= "&a_unique_id[$key]=$value";
}
print_object(json_decode(callws($url),true));
print("\n");
