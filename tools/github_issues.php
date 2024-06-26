<?php

declare(strict_types=1);

// A) requirements
//
// curl -s http://getcomposer.org/installer | php
// php composer.phar require knplabs/github-api php-http/guzzle6-adapter
//
// B) usage
//
// php gh.php --milestone=2.8.4 --html

$opt = getopt('', ['milestone:', 'html']);

$mandatory_fields = ['milestone'];
foreach ($mandatory_fields as $field) {
    if (! isset($opt[$field])) {
        die('missing --' . $field . "\n");
    }
}

// This file is generated by Composer
require_once __DIR__ . '/vendor/autoload.php';

$client = new \Github\Client();
$milestones = $client->api('issue')
    ->milestones()
    ->all('Piwigo', 'Piwigo');

$milestone_number = null;

foreach ($milestones as $milestone) {
    if ($milestone['title'] == $opt['milestone']) {
        $milestone_number = $milestone['number'];
    }
}

if ($milestone_number === null) {
    die('milestone ' . $opt['milestone'] . ' not found');
}

$issues = $client->api('issue')
    ->all('Piwigo', 'Piwigo', [
        'milestone' => $milestone_number,
        'state' => 'closed',
    ]);

foreach ($issues as $issue) {
    if (isset($opt['html'])) {
        echo '<li><a href="' . $issue['html_url'] . '">#' . $issue['number'] . '</a>: ' . $issue['title'] . '</li>' . "\n";
    } else {
        echo '#' . $issue['number'] . ' ' . $issue['title'] . "\n";
    }
}
