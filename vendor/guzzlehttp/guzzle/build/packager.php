<?php
require __DIR__ . '/Burgomaster.php';

$stageDirectory = __DIR__ . '/artifacts/staging';
$projectRoot = __DIR__ . '/../';
$packager = new \Burgomaster($stageDirectory, $projectRoot);

// Copy basic files to the stage directory. Note that we have chdir'd onto
// the $projectRoot directory, so use relative paths.
foreach (['README.md', 'LICENSE'] as $file) {
    $packager->deepCopy($file, $file);
}

// Copy each dependency to the staging directory. Copy *.php and *.pem files.
$packager->recursiveCopy('src', 'GuzzleHttpV6', ['php']);
$packager->recursiveCopy('vendor/guzzlehttp/promises/src', 'GuzzleHttpV6/Promise');
$packager->recursiveCopy('vendor/guzzlehttp/psr7/src', 'GuzzleHttpV6/Psr7');
$packager->recursiveCopy('vendor/psr/http-message/src', 'Psr/Http/Message');

$packager->createAutoloader([
    'GuzzleHttpV6/functions.php',
    'GuzzleHttpV6/Psr7/functions.php',
    'GuzzleHttpV6/Promise/functions.php',
]);

$packager->createPhar(__DIR__ . '/artifacts/guzzle.phar');
$packager->createZip(__DIR__ . '/artifacts/guzzle.zip');
