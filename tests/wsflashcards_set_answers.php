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

function array2string($data,$momentstring){
    $result = "";
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if($momentstring) {
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

$activity = [ 
    'activities' =>[
        0 => [
            'a_unique_id' => $options['activity'],
            'questions' => [
                0 => [
                    'q_unique_id' => $options['question'],
                    'q_known' => $options['known'],
                    'q_answer_date' => time(),
                ]
            ]
        ]
    ]
];
$tokenurl = $options['domainname'] . "login/token.php?username=" . $options['username'] . "&password=". $options['password'] . "&service=wsflashcards";

$result = callws($tokenurl);

// grab URL and pass it to the browser
$token = json_decode($result, true)['token'];

$url = $options['domainname'] . "webservice/rest/server.php?wstoken=$token&wsfunction=wsflashcards_set_answers&moodlewsrestformat=json";
$url .= array2string($activity,"");

print($url);
print_object(json_decode(callws($url),true));
print("\n");
