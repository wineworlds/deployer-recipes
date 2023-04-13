<?php

namespace Deployer;

use Deployer\Exception\ConfigurationException;

set('sync_from_host', function () {
    throw new ConfigurationException('Please, specify `sync_from_host`.');
});

set('sync_to_host', function () {
    throw new ConfigurationException('Please, specify `sync_to_host`.');
});

task('transfer', ['sync:db:export', 'sync:db:import', 'transfer:files']);

task('transfer:files', function () {
    $syncFromHost = host(get('sync_from_host'));

    on($syncFromHost, function () {
        $syncToHost = host(get('sync_to_host'));
        $syncFromHost = host(get('sync_from_host'));

        $fromDeployPath = $syncFromHost->get('deploy_path');
        $fromHostName = $syncFromHost->getHostname();
        $fromUserName = $syncFromHost->getRemoteUser();

        $toDeployPath = $syncToHost->get('deploy_path');
        $toHostName = $syncToHost->getHostname();
        $toUserName = $syncToHost->getRemoteUser();
        $toSSH = "$toUserName@$toHostName:$toDeployPath";

        $source = parse("{$fromDeployPath}/");

        if ("$fromUserName@$fromHostName" === "$toUserName@$toHostName") {
            $destination = parse("{$toDeployPath}");
        } else {
            $destination = parse("{$toSSH}");
        }

        writeln("<info>Sync...</info>");
        run("rsync -rlDvz $source $destination");
    });
});
