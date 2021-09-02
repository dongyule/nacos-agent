#!/usr/bin/env php
<?php

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
ini_set('memory_limit', '128M');

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
    $config = require_once BASE_PATH . '/config/config.php';
    $nacosConfigClient = new Nacos\Config($config);
    Timer::add($config['config_reload_interval'], function () use ($config, $nacosConfigClient) {
        foreach ($config['listener_config'] as $item) {
            try {
                $item['tenant'] = $config['tenant'];
                $configModel = new Nacos\Model\ConfigModel($item);
                $configContent = $nacosConfigClient->get($configModel);
                $filePath = sprintf('%s/%s', rtrim($config['config_save_path'], '/'), $item['data_id']);
                if (!empty($item['target'])) {
                    $filePath = sprintf('%s/%s', rtrim($config['config_save_path'], '/'), $item['target']);
                }

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

