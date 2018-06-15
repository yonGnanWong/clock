<?php
namespace Core;

class Server
{

    /**
     * 任务队列
     * @var object
     */
    protected static $taskList;

    /**
     * 执行work的对象
     * @var object
     */
    protected static $worker;

    /**
     * 当前存活的woker进程数量
     * @var int
     */
    protected static $workerNum = 0;

    /**
     * masterPid文件位置
     * @var string
     */
    protected static $pidFile = null;

    /**
     * masterPid
     * @var int
     */
    protected static $masterPid;

    /**
     * 标准输入输入位置
     * @var string
     */
    protected static $stdoutFile = '/dev/null';

    /**
     * 守护模式
     * @var bool
     */
    protected static $daemonize = false;

    /**
     * server状态 false时将停止事件循环
     * @var bool
     */
    protected static $status = false;

    /**
     * 标记当前进程是否是master进程
     * @var bool
     */
    protected static $master = true;

    /**
     * 异步信号处理
     * php 7.1 及以上支持
     * @var bool
     */
    protected static $asyncSignals = false;

    /**
     * 注入worker对象和tasklist对象
     * @param Worker $worker   worker对象
     * @param TaskList $taskList 任务队列对象
     */
    public function __construct($worker, $taskList)
    {
        if (!is_subclass_of($worker, '\Core\AbstractWorker')) {
            throw new \Exception('worker 不是AbstractWorker的子类');
        }

        if (!is_subclass_of($taskList, '\Core\AbstractTaskList')) {
            throw new \Exception('worker 不是AbstractTaskList的子类');
        }

        if (is_null(self::$pidFile)) {
            self::$pidFile = getcwd(). '/Temp/pid.pid';
        }
        self::$worker = $worker;
        self::$taskList = $taskList;
    }

    /**
     * 运行
     * @return void
     */
    public static function runAll()
    {
        self::checkSapiEnv();
        self::parseCommand();
        self::daemonize();
        self::installSignal();
        self::saveMasterPid();
        self::resetStd();
        self::masterLoop();
    }

    /**
     * master事件循环
     * @return void
     */
    protected static function masterLoop()
    {
        while (self::$status) {
            // 获取任务
            $task = self::$taskList->getTask();
            // 如果获取到任务
			if (false !== $task) {
                // 开始处理这个任务
                self::execute($task);
			}
            if (!self::$asyncSignals) {
                // 调用信号处理(不再使用ticks)http://rango.swoole.com/archives/364
                pcntl_signal_dispatch();
            }
		}
    }

    /**
     * 主要处理程序
     * @param   $task 任务对象
     */
    protected static function execute($task)
    {
        // 创建work进程
        $pid = pcntl_fork();
        if ($pid === 0) {
            // 这里全部为work进程
            // 改变自己状态为非master(标注自己是work进程)
			self::$master = false;
            // 交给worker对象去处理这次请求
			self::$worker->run($task);
            // 处理完成后退出work进程
			exit(0);
		}else {
            // 这里为master进程
            // 增加work进程数(当前有多少个work在工作)
            ++self::$workerNum;
		}
    }

    /**
     * 输入参数
     * @return void
     */
    protected static function parseCommand()
    {
        // 获取脚本执行参数
        global $argv;
        // 如果没有设置启动参数 或者启动参数不正确 则退出脚本
        if (!isset($argv[1]) || !in_array($argv[1], array('start', 'stop'))) {
           if (isset($argv[1])){
             exit("未知的命令: $argv[1] \n");
           }
            exit("没有提供任何启动参数,目前支持start stop. \n");
        }

        // 获取启动参数
        $command  = trim($argv[1]);
        $command2 = isset($argv[2]) ? $argv[2] : '';

        // 获取主进程pid
        $masterPid = is_file(static::$pidFile) ? file_get_contents(static::$pidFile) : 0;
        // 检查主进程是否存活
        $masterIsAlive = $masterPid && @posix_kill($masterPid, 0) && posix_getpid() != $masterPid;
        if ($masterIsAlive) {
            // 存活状态下 start 将被无视
            if ($command === 'start') {
                exit("服务已经启动\n");
            }
        // 未存活状态 start restart 以外的将被无视
        } elseif ($command !== 'start' && $command !== 'restart') {
            exit("尚未启动服务\n");
        }

        // 命令解析
        switch ($command) {
            case 'start':
                // 是否以守护进程运行
                $daemonize = $command2 === '--d' ? true : false;
                self::start($daemonize);
                break;
            case 'stop':
                // 给master发送停止信号
                posix_kill($masterPid, SIGINT);
                break;
            default :
            // 非正确命令参数则输出提示 并退出
    		if (isset($command)){
                exit("未知的命令: $command \n");
            }
        }
    }

    /**
     * 守护进程
     * @return void
     */
    protected static function daemonize()
    {
        if (!static::$daemonize) {
            return;
        }
        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new \Exception("setsid fail");
        }
        // 再次fork 防止重新获取终端控制权
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }

    /**
     * 检查运行环境
     * @return void
     */
    protected static function checkSapiEnv()
    {
        // 检查扩展是否启用
        if (!extension_loaded("pcntl")) {
			exit("需要加载pcntl扩展");
		}

        // 必须以cli模式运行
		if (substr(php_sapi_name(), 0, 3) !== 'cli') {
			exit("需要以cli模式运行");
		}

        // php7.1以上允许异步信号处理
        if (phpversion() >= 7.1) {
            pcntl_async_signals(true);
            self::$asyncSignals = true;
        }
    }

    /**
     * 注册信号处理
     * @return void
     */
    protected static function installSignal()
    {
        // 设置信号
        $signals = array(
            SIGCHLD => "SIGCHLD", // worker exit
			SIGCLD	=> "SIGCLD",
            SIGINT  => "SIGINT", // stop
            SIGHUP  => "SIGHUP", // 终端退出
            SIGQUIT => "SIGQUIT" // 中止
        );
        // 将信号处理全部注册为self::signalHandler处理
        foreach ($signals as $signal => $name) {
			if (!pcntl_signal($signal, [self::class, 'signalHandler'])) {
				exit("注册 {$name} 信号失败");
			}
		}
    }

    /**
     * 启动模式
     * @param  boolean $daemonize 是否以守护模式启动
     * @return void
     */
    protected static function start($daemonize = false)
    {
        self::$status = true;
        if ($daemonize) {
            self::$daemonize = true;
            Trigger::debug(false);
        }
    }

    /**
     * 停止Server
     * @return void
     */
    protected static function stop()
    {
        // work进程返回(work进程不执行以下代码)
		if (!self::$master) {
			return;
		}

        // 终止master事件循环
		self::$status = false;

        // 当前work还有未执行完毕的任务
        while (self::$workerNum > 0) {
            // 进程终止或者停止时(等待work执行完毕), 释放该work进程资源
            self::cleanupWorker();
		}

        Trigger::log("work进程清理完毕");
        exit(0);
    }

    /**
     * 重定向文件输入输出流
     * @return void
     */
    public static function resetStd()
    {
        // 非守护模式不执行
        if (!static::$daemonize) {
            return;
        }
        global $STDOUT, $STDERR;
        $handle = fopen(static::$stdoutFile, "a");
        if ($handle) {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen(static::$stdoutFile, "a");
            $STDERR = fopen(static::$stdoutFile, "a");
        } else {
            throw new \Exception('不能打开标准输入输出流文件: ' . static::$stdoutFile);
        }
    }

    /**
     * 保存master进程id
     * @return void
     */
    protected static function saveMasterPid()
    {
        static::$masterPid = posix_getpid();
        if (false === file_put_contents(static::$pidFile, static::$masterPid)) {
            throw new \Exception('不能保存主进程pid到' . static::$pidFile);
        }
    }

    /**
     * 信号处理
     * @return void
     */
    public static function signalHandler($signo)
    {
        switch(intval($signo)) {
		case SIGCLD:
            // 子进程状态改变信号
		case SIGCHLD:
            // 进程终止或者停止时, 释放该worker进程资源
            self::cleanupWorker();
            break;
        case SIGINT:
            // 中断信号
        case SIGQUIT:
            // 退出信号
        case SIGHUP:
            // 挂起信号
            // 以上三个信号都退出
            self::stop();
            break;
		default:
			break;
		}
    }

    /**
     * 回收work进程资源
     * @return void
     */
    protected static function cleanupWorker()
    {
        while( ($pid = pcntl_wait($status, WNOHANG | WUNTRACED)) > 0 ) {
            if (false === pcntl_wifexited($status)) {
                Trigger::warn("{$pid} 进程异常退出 code: {$status}");
            } else {
                Trigger::log("{$pid} 进程正常退出");
            }
            // 当前进程数量--
            self::$workerNum--;
        }
    }
}
