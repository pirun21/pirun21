<?php

namespace Deployer;

require 'recipe/common.php';

// Use timestamp for release name
set('release_name', function () {
    return date('YmdHis');
});

set('repository','');

set('disable_modules', [
    'SampleData',
    'Magento_TwoFactorAuth',
]);

set('shared_files', [
    'app/etc/env.php',
    'var/.maintenance.ip',
    'auth.json',
    'pub/robots.txt',
    'nginx.conf.sample',
]);

set('shared_dirs', [
    'var/composer_home',
    'var/log',
    'var/export',
    'var/import',
    'var/report',
    'var/import_history',
    'var/session',
    'var/report',
    'var/importexport',
    'var/backups',
    'var/tmp',
    'pub/sitemaps',
    'pub/media',
]);

set('writable_dirs', [
    'app/etc/',
    'var',
    'var/page_cache',
    'var/view_preprocessed',
    'pub/static',
    'pub/media',
    'generated'
]);

set('clear_paths', [
    'generated/*',
    'pub/static/_cache/*',
    'pub/static/frontend/*',
    'pub/static/adminhtml/*',
    'var/generation/*',
    'var/cache/*',
    'var/page_cache/*',
    'var/view_preprocessed/*'
]);

set('writable_mode','chmod');

set('writable_use_sudo', true);

set('writable_chmod_mode', 775);

set('keep_releases', 2);

set('default_timeout', 1000);

inventory('hosts.yml');

host('pg.kemana.dev')
    ->set('http_user', 'planet');

desc('Enable All Modules');
task('magento:module:enable', function () {
    run("{{bin/php}} {{release_path}}/bin/magento module:enable --all");
});

desc('Disable Module(s)');
task('magento:module:disable', function() {
    $disabled_modules = get('disable_modules');
    if(!empty($disabled_modules)) {
        foreach($disabled_modules as $module) {
            run("{{bin/php}} {{release_path}}/bin/magento module:disable Magento_TwoFactorAuth");
            run("{{release_path}}/bin/magento module:status | grep $module | xargs {{release_path}}/bin/magento module:disable");
        }
    }
});

desc('Create Magento 2 env file for Magento2');
task('deploy:prepare:env', function() {
    upload(__DIR__ . "/env.php", "{{release_path}}/app/etc/");
})->once();

desc('Create Magento 2 auth json file for Magento2');
task('deploy:prepare:authjson', function() {
    upload(__DIR__ . "/auth.json", "{{release_path}}");
})->once();

task('magento:robots:creating', function () {
    upload(__DIR__ . "/robots.txt", "{{release_path}}/pub/");
})->once();

task
('magento:remove:authjson', function () {
    cd("{{deploy_path}}");
    run('rm -rf shared/auth.json');
});

desc('Deploy assets');
task('magento:deploy:assets', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy -f ");
});

desc('Run Magento Upgrade');
task('magento:upgrade', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:upgrade");
});

task('magento:fix:ownership', function () {
    cd("{{deploy_path}}");
    run('sudo chown -Rf planet:www-data .');
});

desc('Settup 777 permisson for pub/ and var/ dir');
task('magento:fix:ownership2', function () {
    cd("{{release_path}}");
    run('sudo chmod -Rf 777 pub/ var/ generated/');
});

desc('Run Magento Compile');
task('magento:compile', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:di:compile");
});
desc('Run MagentoMiantenace Enable');
task('magento:maintenance:enable', function () {
    run("{{bin/php}} {{release_path}}/bin/magento maintenance:enable");
});
desc('Run Magento Maintenance Disable');
task('magento:maintenance:disable', function () {
    run("{{bin/php}} {{release_path}}/bin/magento maintenance:disable");
});
desc('Run Magento Cache Flush');
task('magento:cache:flush', function () {
    run("{{bin/php}} {{release_path}}/bin/magento cache:flush");
});

desc('Run Magento Compile');
task('magento:reindex', function () {
    run("{{bin/php}} {{release_path}}/bin/magento indexer:reindex");
});

task('magento:run:htp', function () {
    cd("{{release_path}}");
    run("chmod 775 confightp.sh && ./confightp.sh && rm confightp.sh");
});

task('magento:prepare:htp', function() {
    upload(__DIR__ . "/confightp.sh", "{{release_path}}");
})->once();


task('restart:php-fpm', function () {
    run('sudo /usr/sbin/service php7.4-fpm reload');
});

task('restart:nginx', function () {
    run('sudo /usr/sbin/service nginx reload');

});




desc('Magento2 deployment operations');
task('deploy:magento', [
    'magento:module:enable',
    'magento:maintenance:enable',
    'magento:module:disable',
    'magento:upgrade',
    'magento:compile',
    'magento:deploy:assets',
    'magento:reindex',
    'magento:cache:flush',
    'magento:fix:ownership',
    'magento:fix:ownership2',
    'magento:remove:authjson',
//    'magento:prepare:htp',
//    'magento:run:htp',
    'magento:robots:creating',
    'magento:maintenance:disable'
]);

desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:prepare:authjson',
    'deploy:vendors',
    'deploy:prepare:env',
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:magento',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

after('deploy:failed','deploy:unlock');
after('deploy', 'restart:php-fpm');
after('deploy', 'restart:nginx');
