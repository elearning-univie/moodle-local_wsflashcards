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
 * wsflashcards test helper functions
 *
 * @package    local_wsflashcards
 * @copyright  2020 University of Vienna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * calls webservice function
 * @param string $url
 * @return mixed
 */
function callws($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);

    curl_close($ch);
    return $result;
}
/**
 * puts an array to a string in URL workable way
 * @param unknown $data
 * @param unknown $momentstring
 * @return string
 */
function array2string($data, $momentstring) {
    $result = "";
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if ($momentstring) {
                $newmoment = $momentstring . '[' . $key . ']';
                $result .= array2string($value, $newmoment);
            } else {
                $result .= array2string($value, $key);
            }
        } else {
            $result .= "&$momentstring". "[$key]" . "=$value";
        }
    }
    return $result;
}
