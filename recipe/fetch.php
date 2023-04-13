<?php

namespace Deployer;

use Deployer\Exception\ConfigurationException;

set('database_url_pattern', '/^mysql:\/\/([^:]+):([^@]+)@([^:]+):([^\/]+)\/(.*)$/i');

set('database_url', function () {
    $databaseRrl = trim(run('echo "$DATABASE_URL"'));

    if (!preg_match(get('database_url_pattern'), $databaseRrl)) {
        throw new ConfigurationException('Mssing valid $DATABASE_URL inside .env');
    }

    return $databaseRrl;
});

set('mysqldump_file', 'dump.sql');

set('mysqldump_options', [
    '--skip-comments',
]);

set('local_path', function () {
    return runLocally('pwd');
});

set('fetch_dirs', []);

set('fetch_files', []);

task('fetch', ['fetch:db', 'fetch:files']);

task('fetch:db', function () {
    preg_match(get('database_url_pattern'), get('database_url'), $matches);

    $user = $matches[1];
    $pass = $matches[2];
    $host = $matches[3];
    $port = $matches[4];
    $name = $matches[5];
    $file = get('mysqldump_file');
    $options = implode(" ", get('mysqldump_options'));

    // Export DB
    run("mysqldump -h {$host} -P {$port} -u {$user} -p{$pass} {$options} {$name} > {{deploy_path}}/{$file}");

    // Download DB
    download("{{deploy_path}}/{$file}", "{{local_path}}/{$file}");

    // Remove DB file from server
    run("rm {{deploy_path}}/{$file}");

    // Import DB in DDEV
    // TODO: red local .env file to detect db
    runLocally("mysql -h db -P 3306 -u root -proot < {{local_path}}/{$file}");

    runLocally("rm {{local_path}}/{$file}");
});

task('fetch:files', function () {
    $localPath = get('local_path');
    $currentPath = get('current_path');

    foreach (get('fetch_dirs') as $dir) {
        // Make sure all path without tailing slash.
        $dir = trim($dir, '/');

        // Check if dir does exist.
        if (test("[ -d $currentPath/$dir ]")) {

            // Check if local dir does not exist.
            if (!testLocally("[ -d $localPath/$dir ]")) {
                // Create local dir if it does not exist.
                runLocally("mkdir -p $localPath/$dir");
            }

            download("$currentPath/$dir/", "$localPath/$dir", [
                'flags' => '-rDzLK',
                'options' => [
                    '--exclude=_processed_',
                    '--exclude=_temp_',
                ]
            ]);
        }
    }

    foreach (get('fetch_files') as $file) {
        // Check if file does exist.
        if (test("[ -f $currentPath/$file ]")) {
            download("$currentPath/$file", "$localPath/$file", [
                'flags' => '-rDzLK',
            ]);
        }
    }
});
