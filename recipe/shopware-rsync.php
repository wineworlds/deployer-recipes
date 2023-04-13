<?php

namespace Deployer;

require 'recipe/shopware.php';
require 'contrib/rsync.php';
require __DIR__ . '/fetch.php';
require __DIR__ . '/sync.php';
require __DIR__ . '/transfer.php';

set('rsync_src', './');

set('rsync', [
    'exclude' => [
        '/.ddev',
        '/.deployer',
        '/.git',
        '/.github',
        '/.gitignore',
        '/.thunder-client',
        '/deploy.yaml',
        '/config/jwt',
        '/files',
        '/var/log',
        '/var/cache',
        '/public/media',
        '/public/thumbnail',
        '/public/sitemap'
    ],
    'exclude-file' => false,
    'include' => [],
    'include-file' => false,
    'filter' => [
        'protect .env .uniqueid.txt auth.json'
    ],
    'filter-file' => false,
    'filter-perdir' => false,
    'flags' => 'rlDz',
    'options' => ['recursive', 'delete'],
    'timeout' => 3600,
]);

set('shared_files', [
    ".env",
    ".uniqueid.txt",
    "auth.json"
]);

add('shared_dirs', [
    "config/jwt",
    "files",
    "var/log",
    "public/media",
    "public/thumbnail",
    "public/sitemap"
]);

set('fetch_dirs', [
    "config/jwt",
    "files",
    "public/media",
    "public/thumbnail",
    "public/sitemap"
]);

set('fetch_files', [
    ".env",
    ".uniqueid.txt",
    "auth.json"
]);

set('sync_dirs', [
    // "config/jwt",
    "files",
    "public/media",
    "public/thumbnail",
    "public/sitemap"
]);

desc('Deploys your TYPO3 project');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'sw-build-without-db:build',
    'rsync',
    'deploy:shared',
    'deploy:writable',
    'sw:deploy',
    'deploy:clear_paths',
    'sw:cache:warmup',
    'sw:writable:jwt',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
]);

task('sw-build-without-db:build', static function () {
    runLocally('CI=1 SHOPWARE_SKIP_BUNDLE_DUMP=1 SHOPWARE_SKIP_THEME_COMPILE=1 PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=1 ./bin/build-js.sh');
});

task('sw:deploy', [
    'sw:database:migrate',
    'sw:plugin:refresh',
    'sw:cache:clear',
    'sw:plugin:update:all',
    'sw:pwa:dump-plugins',
    'sw:cache:clear',
    'sw:database:migrate',
]);

task('sw:pwa:dump-plugins', function () {
    run('cd {{release_path}} && bin/console pwa:dump-plugins');
});