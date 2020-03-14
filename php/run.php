<?php
require_once('TwitterAPIExchange.php');
require __DIR__.'/vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

function get_db() {
    $serviceAccount = ServiceAccount::fromJsonFile(__DIR__.'/firebase-credentials.json');

    $firebase = (new Factory)
        ->withServiceAccount($serviceAccount)
        ->withDatabaseUri('https://ayuda-alimentos-coronavirus.firebaseio.com')
        ->create();

    return $firebase->getDatabase();
}

function get_all_tweets_in_db() {
    return get_db()->getReference('tweets')->getValue();
}

function get_boundaries() {
    return get_db()->getReference('boundaries')->getValue();
}

function store_boundary_last($last) {
    get_db()->getReference("boundaries")->getChild('last')->set($last);
}

function store_boundary_first($first) {
    get_db()->getReference("boundaries")->getChild('first')->set($first);
}

function store_tweet_in_db($tid, $nick, $text) {
    get_db()->getReference("tweets")
        ->push([
            'id'      => $tid,
            'nick'    => $nick,
            'message' => $text
        ]);
}

function show_db($config) {
    print_r(get_all_tweets_in_db());
}

function show_last_tweets($config) {
    $url = 'https://api.twitter.com/1.1/search/tweets.json';
    $getfield = '?q=%23ayudaalimentoscoronavirus&count=100';
    $requestMethod = 'GET';

    $twitter = new TwitterAPIExchange(array(
        'oauth_access_token' => $config['oauth']['access_token'],
        'oauth_access_token_secret' => $config['oauth']['access_token_secret'],
        'consumer_key' => $config['oauth']['consumer_key'],
        'consumer_secret' => $config['oauth']['consumer_secret']
    ));
    $results = json_decode($twitter->setGetfield($getfield)
        ->buildOauth($url, $requestMethod)
        ->performRequest());

    foreach($results->statuses as $tweet) {
        $nick = $tweet->user->screen_name;
        $status = $tweet->text;
        if (true || isInterestingTweet($status)) {
            $coloured_status = preg_replace(
                "/(AyudaAlimentosCoronavirus)/i",
                "\033[01;31m".'${1}'."\033[0m",
                $status
            );
            $tid = $tweet->id_str;
            echo " \033[01;37m@${nick}\033[0m said: \033[01;32m\"${coloured_status}\033[0m\"\n     (\033[38;5;14m\033[4mhttps://twitter.com/${nick}/status/${tid}\033[0m)\n\n";
        }
    }
}

function collect_tweets($config) {
    collect_tweets_after_last($config);
    collect_tweets_before_first($config);
}

function collect_tweets_after_last($config) {
    $url = 'https://api.twitter.com/1.1/search/tweets.json';
    $last = get_boundaries()["last"];
    $getfield = '?q="%23AyudaAlimentosCoronavirus"'
        .'&count=100'
        .'&since_id='.$last
    ;
    $requestMethod = 'GET';

    $twitter = new TwitterAPIExchange(array(
        'oauth_access_token' => $config['oauth']['access_token'],
        'oauth_access_token_secret' => $config['oauth']['access_token_secret'],
        'consumer_key' => $config['oauth']['consumer_key'],
        'consumer_secret' => $config['oauth']['consumer_secret']
    ));
    $results = json_decode($twitter->setGetfield($getfield)
        ->buildOauth($url, $requestMethod)
        ->performRequest());

    $tweets = $results->statuses;
    if (count($tweets) < 1) {
        return;
    }

    foreach($tweets as $tweet) {
        $nick = $tweet->user->screen_name;
        $status = $tweet->text;
        if (isInterestingTweet($status)) {
            $coloured_status = preg_replace(
                "/(AyudaAlimentosCoronavirus)/i",
                "\033[01;31m".'${1}'."\033[0m",
                $status
            );
            $tid = $tweet->id_str;
            echo " \033[01;37m@${nick}\033[0m said: \033[01;32m\"${coloured_status}\033[0m\"\n     (\033[38;5;14m\033[4mhttps://twitter.com/${nick}/status/${tid}\033[0m)\n\n";
            store_tweet_in_db($tid, $nick, $status);
        }
    }

    $new_last = $tweets[0]->id_str;
    echo "    LAST updated from $last to $new_last\n";
    store_boundary_last($new_last);
}

function collect_tweets_before_first($config) {
    $url = 'https://api.twitter.com/1.1/search/tweets.json';
    $first = get_boundaries()["first"];
    $getfield = '?q="%23AyudaAlimentosCoronavirus"'
        .'&count=100'
        .'&max_id='.$first
    ;
    $requestMethod = 'GET';

    $twitter = new TwitterAPIExchange(array(
        'oauth_access_token' => $config['oauth']['access_token'],
        'oauth_access_token_secret' => $config['oauth']['access_token_secret'],
        'consumer_key' => $config['oauth']['consumer_key'],
        'consumer_secret' => $config['oauth']['consumer_secret']
    ));
    $results = json_decode($twitter->setGetfield($getfield)
        ->buildOauth($url, $requestMethod)
        ->performRequest());

    $tweets = $results->statuses;
    if (count($tweets) < 2) {
        return;
    }

    foreach($tweets as $tweet) {
        $nick = $tweet->user->screen_name;
        $status = $tweet->text;
        if (isInterestingTweet($status)) {
            $coloured_status = preg_replace(
                "/(AyudaAlimentosCoronavirus)/i",
                "\033[01;31m".'${1}'."\033[0m",
                $status
            );
            $tid = $tweet->id_str;
            echo " \033[01;37m@${nick}\033[0m said: \033[01;32m\"${coloured_status}\033[0m\"\n     (\033[38;5;14m\033[4mhttps://twitter.com/${nick}/status/${tid}\033[0m)\n\n";
            store_tweet_in_db($tid, $nick, $status);
        }
    }

    $new_first = $tweets[count($tweets) - 2]->id_str;
    echo "    FIRST updated from $first to $new_first\n";
    store_boundary_first($new_first);
}

function isInterestingTweet($text) {
    return substr($text, 0, 4) != "RT @";
}

function show_commands($script) {
    echo "    php ".$script." db                 \033[01;33m  - shows database contents\033[0m\n";
    echo "    php ".$script." last               \033[01;33m  - shows last tweets\033[0m\n";
    echo "    php ".$script." collect            \033[01;33m  - collects tweets\033[0m\n";
}

if (isset($_GET) && count($_GET) > 0 && isset($_GET['params'])) {
    $argv = $_GET['params'];
    array_unshift($argv, __FILE__);
}

$script = $argv[0];
$config = require __DIR__ . '/config.php';

if (count($argv) <= 1) {
    echo "\033[01;31mMissing command, try one of the following:\033[0m\n";
    show_commands($script);
    exit;
}

$command = $argv[1];
switch($command) {
    case "db":
        show_db($config);
        break;
    case "last":
        show_last_tweets($config);
        break;
    case "collect":
        collect_tweets($config);
        break;
    default:
        echo "\033[01;31mCommand '${command}' not known, try one of the following:\033[0m\n";
        show_commands($script);
        exit;
}