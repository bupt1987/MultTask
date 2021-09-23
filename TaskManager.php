<?php

namespace MultTask;

declare(ticks=1);
TaskManager::startListen();

/**
 * 多任务并发类
 */
class TaskManager {

    //执行后结果
    private $_result = [];
    //检查进程间隔时间(微秒)，默认0.1秒
    private $_checkTime = 100000;
    //命令池
    private $_cmds = [];
    //任务池
    private $_pool = [];
    //并发数
    private $_concurrency = 1;
    //检查是否有新任务的回调函数
    private $_importTaskMethod = null;
    //处理返回结果回调函数
    private $_getResultMethod = null;
    //检查新任务的间隔（次数，为checkTime的次数）
    private $_importTaskInterval = 1;
    //加入运行任务总个数
    private $_totalAdded = 0;
    //一共运行任务个数
    private $_totalFinished = 0;
    //已经加入过的任务
    private $_hadJoinCmd = [];
    //是否显示运行信息
    private $_showLog = 0;
    //显示运行状态
    private $_showRunLog = true;


    /** 是否应该退出（收到过 kill 信号），应在循环里可以安全退出的地方不断检测该值 */
    private static $_bExit = false;

    /**
     * 用来接收信号，看是否是退出信号
     * @param mixed $iSignal
     * @static
     * @access public
     * @return void
     */
    public static function handler($iSignal) {
        if (self::$_bExit) {
            return;
        }
        switch ($iSignal) {
            case SIGTERM:
            case SIGINT:
            case SIGUSR1:
                self::$_bExit = true;
                break;
            default:
                break;
        }
    }

    /**
     * 设定触发函数
     * @return void
     * @see Signal::handler
     * @static
     * @access public
     */
    public static function startListen() {
        static $init = false;
        if ($init) {
            return;
        }
        $init = true;
        // 想使用的话，必须跟 declare(ticks=1); 配套
        pcntl_signal(SIGTERM, [__CLASS__, 'handler']);
        pcntl_signal(SIGINT, [__CLASS__, 'handler']);
        pcntl_signal(SIGUSR1, [__CLASS__, 'handler']);
    }

    /**
     * 重置参数
     */
    public function reset() {
        $this->_result = [];
        $this->_checkTime = 1000000;
        $this->_cmds = [];
        $this->_pool = [];
        $this->_concurrency = 1;
        $this->_importTaskInterval = 1;
        $this->_importTaskMethod = null;
        $this->_getResultMethod = null;
        $this->_totalFinished = 0;
        $this->_hadJoinCmd = [];
        $this->_totalAdded = 0;
    }

    /**
     * 重置运行数据
     */
    public function resetData() {
        $this->_result = [];
        $this->_cmds = [];
        $this->_pool = [];
        $this->_hadJoinCmd = [];
    }

    /**
     * 设置检查是否有新任务的回调函数,可以设置多个（函数返回Cmd类型或string类型）
     * @param $function
     * @param array $param
     * @return boolean
     */
    public function addImportTaskMethod($function, $param = []) {

        if (!$this->checkFunction($function)) {
            return false;
        }
        $this->_importTaskMethod[] = [$function, $param];

        return true;
    }

    public function addGetResultMethod($function) {
        if (!$this->checkFunction($function)) {
            return false;
        }
        $this->_getResultMethod = $function;

        return true;
    }

    /**
     * 设置检查新任务的间隔次数
     * @param int $interval
     * @return boolean
     */
    public function setImportTaskInterval($interval) {
        if (!is_numeric($interval)) {
            return false;
        }
        if (!$interval) {
            $interval = 1;
        }
        $this->_importTaskInterval = $interval;

        return true;
    }

    /**
     * 是否显示运行信息
     * @param $_showLog boolean
     */
    public function setShowLog($_showLog) {
        $this->_showLog = $_showLog;
    }

    /**
     * 是否显示运行状态日志
     * @param $runLog
     */
    public function setRunLog($runLog) {
        $this->_showRunLog = $runLog;
    }

    /**
     * 设置并发数
     * @param int $_concurrency
     */
    public function setConcurrency($_concurrency = 1) {
        $this->_concurrency = $_concurrency;
    }

    /**
     * 设置间隔时间
     * @param int $time 单位微秒
     */
    public function setCheckTime($time) {
        $this->_checkTime = $time;
    }

    /**
     * 添加一个新任务.如果任务池满了,就先消化一个任务池内的任务
     * @param $cmd_php
     * @param int $timeout
     * @param int $resultType 结果类型：1为json，2为文本，3为没有结果；
     * @param int $resultShowType 返回结果显示形式：1为所以队列执行完后显示，2为立即显示
     * @param string $errorLog
     * @param string $cwd
     * @param array $env
     * @return bool
     */
    public function addTask($cmd_php, $resultType = 2, $resultShowType = 2, $timeout = 0, $errorLog = '', $cwd = '', $env = []) {
        $cmd = new Cmd($cmd_php, $timeout, $resultType, $resultShowType, $errorLog, $cwd, $env);
        $this->_cmds[] = $cmd;
        $this->_totalAdded++;

        return true;
    }

    /**
     * 全部任务添加后的完成阶段
     * @return boolean
     */
    public function run() {
        //导入其他任务
        $this->importTask();
        $rs = $this->importCmdToPoll();
        if (!$rs) {
            //导入任务不成功退出
            echo "导入任务不成功退出\n";

            return false;
        }
        $check_count = 0;
        $showLogCount = 0;
        $checkShowLogCount = floor(1000000 / $this->_checkTime * 5);
        $startTime = microtime(true);
        if ($this->_showRunLog) {
            echo '[' . date('Y-m-d H:i:s') . '] START RUN' . "\n";
        }
        $this->showStatus();
        while (true) {

            if (self::$_bExit) {
                echo "\n", 'loop end, exit', "\n";
                exit;
            }
            usleep(1);

            //执行任务
            $this->doWork();
            usleep($this->_checkTime);
            if ($this->_importTaskMethod !== null) {
                $check_count++;
                //导入其他任务
                if ($this->isEmpty() || $check_count == $this->_importTaskInterval) {
                    $this->importTask();
                    $check_count = 0;
                }
                if ($this->isEmpty()) {
                    $rs = $this->importCmdToPoll();
                    if (!$rs) {
                        //导入任务不成功退出循环
                        break;
                    }
                }
            } else {
                if ($this->isEmpty()) {
                    break;
                }
            }
            if ($showLogCount == $checkShowLogCount) {
                $this->showStatus();
                $showLogCount = 0;
            }
            $showLogCount++;
        }
        if ($this->_showRunLog) {
            echo '[' . date('Y-m-d H:i:s') . '] FINISHED, RUN TIME : ' . round(microtime(true) - $startTime, 2) . "s\n";
        }

        return true;
    }

    public function getResult() {
        return $this->_result;
    }

    private function showStatus() {
        if ($this->_showRunLog) {
            echo '[' . date('Y-m-d H:i:s') . '] total => ' . $this->_totalAdded . ' | finished => ' . $this->_totalFinished . "\n";
        }
    }

    /**
     * 消化一个任务池内的任务,本方法是本程序的核心所在
     * @return bool
     */
    private function doWork() {
        foreach ($this->_pool as $tid => $task) {
            if (!($task instanceof Task)) {
                exit('pool is not Task Object');
            }
            $status = $task->status();
            if ($task->isRunning()) {
                if ($task->isTimeout()) {
                    echo "task {$status['pid']} : {$status['command']} timeout, force closed!\n";
                    $task->terminate();
                    unset($this->_pool[$tid]);
                }
            } else {
                $rs = $task->getResult();
                if ($rs !== null) {
                    if ($this->_getResultMethod === null) {
                        $param_array = $task->getParam();
                        if ($param_array[Cmd::PARAM_KEY_RESULT_TYPE] == 1) {
                            if (!empty($rs)) {
                                if ($param_array[Cmd::PARAM_KEY_RESULT_SHOW_TYPE] == 2) {
                                    echo $rs . "\n";
                                }
                                $rs = json_decode($rs, true);
                            }
                        } else {
                            if ($param_array[Cmd::PARAM_KEY_RESULT_SHOW_TYPE] == 2) {
                                echo $rs;
                            }
                        }
                    } else {
                        $rs = call_user_func_array($this->_getResultMethod, [$rs]);
                    }
                    if (!empty($rs)) {
                        $this->_result[] = $rs;
                    }
                }
                $this->_totalFinished++;
                if ($this->_showLog) {
                    echo "task {$status['pid']} : {$status['command']} is over, running " . round($status ['excute_time'], 2) . "s\n";
                }
                $task->close();
                unset($this->_pool[$tid]);
                //继续加入任务到任务池
                if (!empty($this->_cmds)) {
                    $cmd = array_shift($this->_cmds);
                    $this->addToRun($cmd);
                }
            }
        }

        return true;
    }

    private function addToRun(Cmd $cmd) {
        $md5_key = md5($cmd->getCmd());
        if (isset($this->_hadJoinCmd[$md5_key])) {
            return false;
        } else {
            $this->_hadJoinCmd[$md5_key] = 1;
            $this->_pool [] = new Task($cmd, $this->_showLog);

            return true;
        }
    }

    /**
     * 判断任务池满了
     * @return bool
     */
    private function isFull() {
        return count($this->_pool) >= $this->_concurrency;
    }

    /**
     * 检查该function是否存在
     * @param string|array $function
     * @return boolean
     */
    private function checkFunction($function) {
        if (empty($function) || (!is_string($function)) && !is_array($function)) {
            return false;
        }
        if (is_string($function) && !function_exists($function)) {
            return false;
        }
        if (is_array($function) && !method_exists($function[0], $function[1])) {
            return false;
        }

        return true;
    }

    /**
     * 判断任务池非空
     * @return bool
     */
    private function isEmpty() {
        return empty ($this->_pool);
    }

    /**
     * 在运行过程中插入新的任务
     * @return boolean
     */
    private function importTask() {
        if ($this->_importTaskMethod === null) {
            return false;
        }
        $cmds = [];
        foreach ($this->_importTaskMethod as $task_method) {
            $temp_cmd = call_user_func_array($task_method[0], $task_method[1]);
            if (is_array($temp_cmd)) {
                $cmds = array_merge($cmds, $temp_cmd);
            } else {
                $cmds[] = $temp_cmd;
            }
        }
        if (empty($cmds)) {
            return false;
        }
        $added = false;
        foreach ($cmds as $cmd) {
            if (is_object($cmd) && get_class($cmd) == 'Cmd') {
                array_push($this->_cmds, $cmd);
            } elseif (is_string($cmd)) {
                $cmd = new Cmd($cmd);
                array_push($this->_cmds, $cmd);
            } else {
                continue;
            }
            $added = true;
            $this->_totalAdded++;
        }

        return $added;
    }

    /**
     * 加满任务池
     * @return boolean
     */
    private function importCmdToPoll() {
        if (empty($this->_cmds)) {
            return false;
        }
        foreach ($this->_cmds as $cmd) {
            if ($this->isFull()) {
                break;
            }
            $this->addToRun($cmd);
            array_shift($this->_cmds);
        }

        return true;
    }

}
