<?php

namespace Deployer;

desc('Sync previous release with target release');
task('deploy:release:copy', function () {
    $releasesList = get('releases_list');

    if (isset($releasesList[1])) {
        run('rsync -a {{previous_release}}/ {{release_path}}');
    }
});

after('deploy:release', 'deploy:release:copy');
