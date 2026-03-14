<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is the path to the asset, relative to the importmap.php file
 * - "entrypoint" is used to indicate that this is the "main" entry
 * - "url" is used to load a remote asset
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
];
