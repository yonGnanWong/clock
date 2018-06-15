# Clock(闹钟)

## 它是什么？
Clock 是一个多进程任务处理框架，通过TaskList->getTask()来获取任务,然后投递给Worker进程执行，
构建它主要是为了解决业务中一些随时投递的定时请求、处理的任务，不过能做的事情不仅局限于此，你可以
自定义worker和TaskList以及task来安排他们去做其他有趣的事情。

## 现在的情况
现在只是一个非常非常初始的版本，后续我会继续完善他，目前只能说能运行起来，哈哈哈；
未来我变强了会支持非阻塞，甚至预派生模式，其实就是个小型workerman啦～

## 环境要求  
 * PHP 5.6及以上(理论上5.3也可以，我没测试过，希望有人能提供反馈)
 * 必须是类Unix系统
 * 启用PCNTL 和 POSIX 扩展
 * 依赖Composer 自动加载机制 务必执行composer install

## 工作原理  
 此框架是类似php-fpm的机制，参考(抄袭)了workerman 和 Mpass；
 当server进入loop时，会不停的尝试获取TaskList中的任务，一但获取到task则fork出一个worker进程
 并将task交给worker处理；当worker进程处理完成后，会自动退出，master会回收worker进程的资源。

## 现在的问题
由于getTask()是阻塞的，所以目前效率还是有问题，后期会改为非阻塞的(尚不会)，希望大家能教教我这个新人。

## 如何使用
 我在项目中提供了一个 `clock.php` 作为示例， 事实上他非常简单，你只需要简单的继承AbstractWorker
 和AbstractTaskList 并实现其中的抽象方法就可以，非常简单 对吧。
