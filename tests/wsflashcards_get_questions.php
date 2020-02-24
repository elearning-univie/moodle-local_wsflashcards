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
