<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/../../../config.php');
global $CFG;
require_once($CFG->libdir.'/clilib.php');

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