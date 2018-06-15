<?php
namespace Core;


abstract class AbstractWorker
{

    public function __construct()
    {

    }

    /**
     * 执行任务
     * @param  Task $task 任务对象
     * @return void
     */
    public function run($task)
    {
        $this->ignoreSignal();
        $this->execute($task);
    }

    /**
     * 主要执行程序
     * @param  Task $task 任务对象
     * @return void
     */
    abstract protected function execute($task);

    /**
     * 忽略所有信号防止异常停止
     * @return void
     */
    protected function ignoreSignal()
    {
        $signals = array(SIGINT, SIGHUP, SIGQUIT);
        foreach ($signals as $signal) {
            pcntl_signal($signal, SIG_IGN);
        }
    }
}
