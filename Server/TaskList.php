<?php
namespace Server;

use Core\AbstractTaskList;

class TaskList extends AbstractTaskList
{
    public $taskNum = 2;

    public function getTask()
    {
        if (0 < $this->taskNum) {
            $this->taskNum--;
            return new Task();
        }else {
            return false;
        }
    }
}
