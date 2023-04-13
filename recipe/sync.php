<?php

namespace Deployer;

use Deployer\Exception\ConfigurationException;

set('sync_from_host', function () {
    throw new ConfigurationException('Please, specify `sync_from_host`.');
});

set('sync_to_host', function () {
    throw new ConfigurationException('Please, specify `sync_to_host`.');
});

set('sync_dirs', function () {
    throw new ConfigurationException('Please, specify `sync_dirs`.');
});

task('sync', ['sync:db', 'sync:files']);

task('sync:db', ['sync:db:export', 'sync:db:import']);

task('sync:db:export', function () {
    $syncFromHost = host(get('sync_from_host'));

    on($syncFromHost, function () {
        $file = 'dump.sql';
        $options = implode(" ", [
            '--skip-comments',
        ]);
        $databaseRrl = trim(run('echo "$DATABASE_URL"'));

        preg_match('/^mysql:\/\/([^:]+):([^@]+)@([^:]+):([^\/]+)\/(.*)$/i', $databaseRrl, $matches);

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
});

task('sync:db:import', function () {
    $syncToHost = host(get('sync_to_host'));

    on($syncToHost, function () {
        $file = 'dump.sql';
        $databaseRrl = trim(run('echo "$DATABASE_URL"'));

        preg_match('/^mysql:\/\/([^:]+):([^@]+)@([^:]+):([^\/]+)\/(.*)$/i', $databaseRrl, $matches);

        $user = $matches[1];
        $pass = $matches[2];
        $host = $matches[3];
        $port = $matches[4];
        $name = $matches[5];

        // Upload DB
        writeln("<info>Upload DB...</info>");
        upload("{{local_path}}/{$file}", "{{deploy_path}}/{$file}");

        // Import DB
        writeln("<info>Import DB...</info>");
        run("mysql -h {$host} -P {$port} -u {$user} -p{$pass} -D {$name} < {{deploy_path}}/{$file}");

        // Remove DB file from server
        writeln("<info>Remove DB file from server...</info>");
        run("rm {{deploy_path}}/{$file}");
    });
});

task('sync:files', function () {
    $syncFromHost = host(get('sync_from_host'));

    on($syncFromHost, function () {
        $syncToHost = host(get('sync_to_host'));
        $syncFromHost = host(get('sync_from_host'));

        $fromCurrentPath = $syncFromHost->get('current_path');
        $fromHostName = $syncFromHost->getHostname();
        $fromUserName = $syncFromHost->getRemoteUser();

        $toCurrentPath = $syncToHost->get('current_path');
        $toHostName = $syncToHost->getHostname();
        $toUserName = $syncToHost->getRemoteUser();
        $toSSH = "$toUserName@$toHostName:$toCurrentPath";

        foreach (get('sync_dirs') as $dir) {
            // Make sure all path without tailing slash.
            $dir = trim($dir, '/');

            if ("$fromUserName@$fromHostName" === "$toUserName@$toHostName") {
                $source = parse("{$fromCurrentPath}/$dir/");
                $destination = parse("{$toCurrentPath}/$dir");
            } else {
                $source = parse("{$fromCurrentPath}/$dir/");
                $destination = parse("{$toSSH}/$dir");
            }

            writeln("<info>Sync $dir...</info>");
            run("rsync -rlDvz $source $destination");
        }
    });
});
