<?php
namespace Server;

use Core\AbstractWorker;


class Worker extends AbstractWorker
{
    protected function execute($task)
    {
        $task->executeTask();
    }
}
