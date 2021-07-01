#!/usr/bin/env php
<?php

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
ini_set('memory_limit', '1G');

error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Lib\Timer;

if (!file_exists(BASE_PATH . '/config/config.php')) {
    echo "config.php not exist \n";
    exit;
}

// worker
$nacosConfigAgentWorker = new Worker();
$nacosConfigAgentWorker->name = 'nacos_config_agent';
$nacosConfigAgentWorker->onWorkerStart = function() {
    $appConfig = require_once BASE_PATH . '/config/config.php';
    $nacosConfigClient = new Nacos\Config($appConfig);
    Timer::add($appConfig['config_reload_interval'], function () use ($appConfig, $nacosConfigClient) {
        foreach ($appConfig['listener_config'] as $item) {
            try {
                $configModel = new Nacos\Model\ConfigModel($item);
                $configContent = $nacosConfigClient->get($configModel);
                $filePath = sprintf('%s/%s/%s', rtrim($appConfig['config_save_path'], '/'), $item['group'], $item['data_id']);
                $file = new SplFileInfo($filePath);
                if (!is_dir($file->getPath())) {
                    mkdir($file->getPath(), 0755, true);
                }
                file_put_contents($filePath, $configContent);
            } catch (Exception $e) {
                echo $e->getMessage() . "\n";
            }
        }
    });
};

$nacosServiceAgentWorker = new Worker();
$nacosServiceAgentWorker->name = 'nacos_service_agent';
$nacosServiceAgentWorker->onWorkerStart = function () {
    $appConfig = require_once BASE_PATH . '/config/config.php';
    // service
    $exist = false;
    $serviceModel = new Nacos\Model\ServiceModel($appConfig['service']);
    try {
        $nacosServiceClient = new Nacos\Service($appConfig);
        $exist = $nacosServiceClient->detail($serviceModel);
    } catch (Exception $e) {
        echo $e->getMessage() . "\n";
    }
    if (! $exist && ! $nacosServiceClient->create($serviceModel)) {
        echo "nacos register service fail \n";
    }
    echo "nacos register service success \n";

    // instance
    $nacosInstanceClient = new Nacos\Instance($appConfig);
    $instanceModel = new Nacos\Model\InstanceModel($appConfig['client']);
    if (! $nacosInstanceClient->register($instanceModel)) {
        echo "nacos register instance fail: \n";
    }
    echo "nacos register instance success \n";

    // beat
    Timer::add($appConfig['client']['beat_interval'], function () use ($appConfig, $nacosInstanceClient, $serviceModel, $instanceModel) {
        try {
            $nacosInstanceClient->beat($serviceModel, $instanceModel);
            echo "beat \n";
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    });
};

$FileMonitorWorker = new Worker();
$FileMonitorWorker->name = 'FileMonitor';
$FileMonitorWorker->reloadable = false;
$last_reload_time = time();
$monitor_dir = BASE_PATH . '/config';

$FileMonitorWorker->onWorkerStart = function() {
    global $monitor_dir;
    // watch files only in daemon mode
    if(!Worker::$daemonize) {
        // chek mtime of files per second
        Timer::add(10, 'check_files_change', array($monitor_dir));
    }
};

// check files func
function check_files_change($monitor_dir) {
    global $last_reload_time;
    // recursive traversal directory
    $dir_iterator = new RecursiveDirectoryIterator($monitor_dir);
    $iterator = new RecursiveIteratorIterator($dir_iterator);
    foreach ($iterator as $file) {
        // only check php files
        if(pathinfo($file, PATHINFO_EXTENSION) != 'php') {
            continue;
        }
        // check mtime
        if($last_reload_time < $file->getCTime()) {
            echo $file." update and reload\n";
            // send SIGUSR1 signal to master process for reload
            posix_kill(posix_getppid(), SIGUSR1);
            // $last_reload_time = $file->getMTime();
            $last_reload_time = time();
            break;
        }
    }
}

// 运行worker
Worker::runAll();

