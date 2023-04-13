<?php

namespace Deployer;

use Deployer\Exception\ConfigurationException;

set('database_url_pattern', '/^mysql:\/\/([^:]+):([^@]+)@([^:]+):([^\/]+)\/(.*)$/i');

set('database_url', function () {
    $databaseURL = trim(run('echo "$DATABASE_URL"'));

    if (!preg_match(get('database_url_pattern'), $databaseURL)) {
        throw new ConfigurationException('Mssing valid $DATABASE_URL inside .env');
    }

    return $databaseURL;
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

task('fetch:db', ['fetch:db:export', 'fetch:db:import']);

task('fetch:db:export', function () {
    $file = get('mysqldump_file');
    $options = implode(" ", get('mysqldump_options'));
    $databaseURLPatern = get('database_url_pattern');

    // TODO: wird die Methode hinter diesem aufruf jedes mal neu aufgerufen oder passiert das nur einmalig, das wäre wichtig zu klären da es sonst zu problemen kommen könnte innerhalb von dem on() block.
    $databaseURL = get('database_url');
    // $databaseURL = trim(run('echo "$DATABASE_URL"'));

    preg_match($databaseURLPatern, $databaseURL, $matches);

    $user = $matches[1];
    $pass = $matches[2];
    $host = $matches[3];
    $port = $matches[4];
    $name = $matches[5];

    // Export DB
    writeln("<info>Export DB...</info>");
    run("mysqldump -h {$host} -P {$port} -u {$user} -p{$pass} {$options} {$name} > {{deploy_path}}/{$file}");

    // Download DB
    writeln("<info>Download DB...</info>");
    download("{{deploy_path}}/{$file}", "{{local_path}}/{$file}");

    // Remove DB file from server
    writeln("<info>Remove DB file from server...</info>");
    run("rm {{deploy_path}}/{$file}");
});

task('fetch:db:import', function () {
    $file = get('mysqldump_file');
    $options = implode(" ", get('mysqldump_options'));
    $databaseURLPatern = get('database_url_pattern');

    // TODO: wird die Methode hinter diesem aufruf jedes mal neu aufgerufen oder passiert das nur einmalig, das wäre wichtig zu klären da es sonst zu problemen kommen könnte innerhalb von dem on() block.
    $databaseURL = get('database_url');
    // $databaseURL = trim(run('echo "$DATABASE_URL"'));

    preg_match($databaseURLPatern, $databaseURL, $matches);

    $user = $matches[1];
    $pass = $matches[2];
    $host = $matches[3];
    $port = $matches[4];
    $name = $matches[5];

    // Import DB
    writeln("<info>Import DB...</info>");
    runLocally("mysql -h {$host} -P {$port} -u {$user} -p{$pass} -D {$name} < {{deploy_path}}/{$file}");

    // Remove DB file from local
    writeln("<info>Remove DB file from local...</info>");
    runLocally("rm {{deploy_path}}/{$file}");
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
