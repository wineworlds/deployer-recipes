<?php

namespace Deployer;

use Symfony\Component\Yaml\Yaml;

require 'recipe/shopware.php';
require 'contrib/rsync.php';
require __DIR__ . '/fetch.php';
require __DIR__ . '/sync.php';
require __DIR__ . '/transfer.php';

set('rsync_src', './');

// TODO: die options sollten grundsätzlich dynamisch sein und nicht hart codiert.
set('rsync_chmod', 'u+rw,g+r,o+r');
set('rsync_use_chmod', false);
set('dotenv', '{{current_path}}/.env');
set('dotenv_local', '{{local_path}}/.env');

set('rsync', function () {
    $rsyncUseChmod = get('rsync_use_chmod');

    $rsync = [
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
    ];

    if ($rsyncUseChmod) {
        $rsyncChmod = get('rsync_chmod');
        $rsync['options'][] = "chmod=$rsyncChmod";
    }

    return $rsync;
});

set('shared_files', [
    ".env",
    ".uniqueid.txt",
    "auth.json"
]);

add('shared_dirs', [
    "config/jwt",
    "files",
    "custom/plugins",
    "var/log",
    "public/media",
    "public/thumbnail",
    "public/sitemap"
]);

set('fetch_dirs', [
    "config/jwt",
    "files",
    "custom/plugins",
    "public/media",
    "public/thumbnail",
    "public/sitemap"
]);

set('fetch_files', [
    // ".env",
    ".uniqueid.txt",
    "auth.json"
]);

set('sync_dirs', [
    // "config/jwt",
    "custom/plugins",
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
    'sw-build-without-db',
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

task('sw-build-without-db', [
    'sw-build-without-db:set-theme-config',
    'sw-build-without-db:get-remote-config',
    'sw-build-without-db:build',
    'sw-build-without-db:remove-theme-config',
]);

task('sw-build-without-db:build', static function () {
    runLocally('CI=1 SHOPWARE_SKIP_NPM=1 SHOPWARE_SKIP_BUNDLE_DUMP=1 SHOPWARE_SKIP_THEME_COMPILE=1 PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=1 ./bin/build-js.sh');
});

task('sw:deploy', [
    'sw:database:migrate',
    'sw:plugin:refresh',
    'sw:cache:clear',
    'sw:plugin:update:all',
    'sw:cache:clear',
    'sw:database:migrate',
]);

task('sw-build-without-db:set-theme-config', static function () {
    $data = [
        'storefront' => [
            'theme' => [
                'config_loader_id' => 'Shopware\Storefront\Theme\ConfigLoader\StaticFileConfigLoader',
                'available_theme_provider' => 'Shopware\Storefront\Theme\ConfigLoader\StaticFileAvailableThemeProvider'
            ]
        ]
    ];

    $content = Yaml::dump($data, 3, 4);
    $escapedContent = escapeshellarg($content);
    $filePath = './config/packages/storefront.yaml';

    runLocally("echo $escapedContent > $filePath");
});

task('sw-build-without-db:remove-theme-config', static function () {
    $filePath = './config/packages/storefront.yaml';

    runLocally("if [ -f $filePath ]; then rm $filePath; fi");
});

task('sw-build-without-db:get-remote-config', static function () {
    if (!test('[ -d {{current_path}} ]')) {
        return;
    }
    within('{{deploy_path}}/current', function () {
        run('{{bin/php}} ./bin/console bundle:dump');
        download('{{deploy_path}}/current/var/plugins.json', './var/');

        run('{{bin/php}} ./bin/console theme:dump');
        download('{{deploy_path}}/current/files/theme-config', './files/');
        download('{{deploy_path}}/current/custom/plugins/', './custom/plugins');
    });
});
