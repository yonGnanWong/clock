<?php
namespace Core;

use Server\Worker;
use Server\TaskList;

/**
 * 引入composer自动加载
 * @var file
 */
require_once 'vendor/autoload.php';

$server = new Server(new Worker, new TaskList);
$server->runAll();
