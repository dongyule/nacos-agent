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

// watch Applications catalogue
$monitor_dir = realpath(__DIR__.'/../config');

// worker
$worker = new Worker();
$worker->name = 'FileMonitor';
$worker->reloadable = false;
$last_reload_time = time();

$worker->onWorkerStart = function()
{
    global $monitor_dir;
    // watch files only in daemon mode
    //if(!Worker::$daemonize) {
        // chek mtime of files per second
        Timer::add(1, 'check_files_change', array($monitor_dir));
    //}
};

// check files func
function check_files_change($monitor_dir)
{
    global $last_reload_time;
    // recursive traversal directory
    $dir_iterator = new RecursiveDirectoryIterator($monitor_dir);
    $iterator = new RecursiveIteratorIterator($dir_iterator);
    foreach ($iterator as $file)
    {
        // only check php files
        if(pathinfo($file, PATHINFO_EXTENSION) != 'php') {
            continue;
        }
        // check mtime
        if($last_reload_time < filemtime($file)) {
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

