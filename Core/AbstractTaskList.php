<?php
namespace Core;

abstract class AbstractTaskList
{
    /**
     * 获取任务
     * 没有任务则返回false
     * 有任务则返回Task任务对象
     * @return false|Task
     */
    abstract public function getTask();
}
