<?php

namespace Deployer;

require 'recipe/typo3.php';
require 'contrib/rsync.php';
require __DIR__ . '/fetch.php';
require __DIR__ . '/sync.php';

set('typo3_webroot', 'public');

// TODO: die options sollten grundsätzlich dynamisch sein und nicht hart codiert.
set('rsync_chmod', 'u+rw,g+r,o+r');
set('rsync_use_chmod', false);

set('rsync', function () {
    $rsyncUseChmod = get('rsync_use_chmod');

    $rsync = [
        'exclude' => [
            '/.*',
            '/composer.*',
            '/deploy.yaml',
            '/{{typo3_webroot}}/fileadmin',
            '/{{typo3_webroot}}/typo3temp',
            '/{{typo3_webroot}}/uploads',
            '/{{typo3_webroot}}/typo3conf/AdditionalConfiguration.php',
            '/var',
        ],
        'exclude-file' => false,
        'include' => [],
        'include-file' => false,
        'filter' => [
            'protect .env'
        ],
        'filter-file' => false,
        'filter-perdir' => false,
        'flags' => 'rlDvz',
        'options' => ['recursive', 'delete'],
        'timeout' => 3600,
    ];

    if ($rsyncUseChmod) {
        $rsyncChmod = get('rsync_chmod');
        $rsync['exclude']['options'][] = "chmod=$rsyncChmod";
    }

    return $rsync;
});

set('default_timeout', 3600);
set('rsync_src', './');

set('shared_files', [
    '.env',
    '{{typo3_webroot}}/typo3conf/AdditionalConfiguration.php'
]);

add('shared_dirs', [
    'var/charset',
    'var/lock',
    'var/log',
    'var/session',
]);

set('fetch_dirs', [
    'config',
    '{{typo3_webroot}}/fileadmin',
    '{{typo3_webroot}}/typo3temp',
    '{{typo3_webroot}}/uploads'
]);

set('sync_dirs', [
    '{{typo3_webroot}}/fileadmin',
    '{{typo3_webroot}}/typo3temp',
    '{{typo3_webroot}}/uploads'
]);

desc('Deploys your TYPO3 project');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:release:copy',
    'rsync',
    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
    't3:prepare',
    'deploy:success',
]);

desc('Deploys your TYPO3 project and fetches the database');
task('t3:prepare', static function () {
    run('cd {{release_path}} && bin/typo3cms database:updateschema');
    run('cd {{release_path}} && bin/typo3cms install:fixfolderstructure');
    run('cd {{release_path}} && bin/typo3cms cache:flush');
    run('cd {{release_path}} && bin/typo3cms cache:warmup');
});

// ToDo: sync:db:export & sync:db:import fehlen noch, diese müssen auch noch an TYPO3 angepasst werden.

task('fetch:db:export', function () {
    // TODO: der bin Pfad sollte konfigurierbar sein
    // TODO: gibt es eine bessere lösung anstatt cd im run aufzurufen?

    run("cd {{current_path}} && bin/typo3cms database:export -e '*cache*' -e 'sys_log' -e 'fe_sessions' -e 'be_sessions' > ./dump.sql");

    download("{{current_path}}/dump.sql", "{{local_path}}/dump.sql");

    run("rm {{current_path}}/dump.sql");
});

task('fetch:db:import', function () {
    runLocally("cd {{local_path}} && cat ./dump.sql | bin/typo3cms database:import");

    runLocally("rm {{local_path}}/dump.sql");
});

desc('Sync previous release with target release');
task('deploy:release:copy', function () {
    $releasesList = get('releases_list');

    if (isset($releasesList[1])) {
        run('rsync -a {{previous_release}}/ {{release_path}}');
    }
});
