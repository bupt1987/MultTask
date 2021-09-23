<?php

namespace MultTask;
/**
 * 任务类
 */
class Task {

    private $showLog = false;
    //任务的命令
    private $cmd;
    //任务句柄
    private $handle;
    //任务开始时间
    private $start_time;
    //命令管道,包括 0=>输入 1=>输出 2=>错误
    private $pipes = [];
    //任务状态
    private $status = [];
    //命令参数
    private $param_array = [
        Cmd::PARAM_KEY_CWD => '', //指定任务运行所需的当前路径
        Cmd::PARAM_KEY_ENV => [], //指定任务运行所需的环境变量
        Cmd::PARAM_KEY_ERROR_LOG => '', //错误日志的路径
        Cmd::PARAM_KEY_RESULT_SHOW_TYPE => 2, //返回结果显示形式：1为所以队列执行完后显示，2为立即显示
        Cmd::PARAM_KEY_RESULT_TYPE => 3, //结果类型：1为json，2为文本，3为没用返回结果；
        Cmd::PARAM_KEY_TIMEOUT => 0 //超时时间,单位为秒,默认为0,值为0时,永不超时.
    ];

    public function __construct(Cmd $cmd_m, $showLog = false) {
        $this->showLog = $showLog;
        $this->cmd = $cmd_m;
        $cmd = $cmd_m->getCmd();
        $param = $cmd_m->getParam();
        // 只接受合法的参数
        $param_keys = array_keys($this->param_array);
        foreach ($param_keys as $validparam) {
            if (isset ($param [$validparam])) {
                $this->param_array[$validparam] = $param [$validparam];
            }
        }
        if ($this->param_array[Cmd::PARAM_KEY_ERROR_LOG]) {
            $desc = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['file', $this->param_array[Cmd::PARAM_KEY_ERROR_LOG], 'a'],
            ];
        } else {
            $desc = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
            ];
        }
        // 执行命令
        $this->handle = proc_open($cmd, $desc, $this->pipes);
        // 把输出设成非阻塞
        stream_set_blocking($this->pipes [1], 0);
        $this->start_time = microtime(true);
        if ($this->showLog) {
            $status = $this->status();
            echo "Task {$status['pid']} : " . $cmd . " addToRun.\n";
        }
    }

    /**
     * 任务超时的判断,调用本方法前应先调用 $task->status()方法
     */
    public function isTimeout() {
        return $this->param_array[Cmd::PARAM_KEY_TIMEOUT] ? $this->status ['excute_time'] >= $this->param_array[Cmd::PARAM_KEY_TIMEOUT] : false;
    }

    /**
     * 判断任务是否在执行,调用本方法前应先调用 $task->status()方法
     */
    public function isRunning() {
        return $this->status ['running'];
    }

    /**
     * 正常结束任务
     */
    public function close() {
        if (is_resource($this->handle)) {
            proc_close($this->handle);
        }
    }

    /**
     * 获取运行结果
     */
    public function getResult() {
        if ($this->param_array[Cmd::PARAM_KEY_RESULT_TYPE] == 3) {
            return null;
        }
        $result = null;
        if (is_resource($this->handle)) {
            $result = stream_get_contents($this->pipes[1]);
        }

        return $result;
    }

    public function getParam() {
        return $this->param_array;
    }

    /**
     * 强行终止超时的任务
     */
    public function terminate() {
        if (is_resource($this->handle)) {
            proc_terminate($this->handle);
            proc_close($this->handle);
        }
    }

    /**
     * 获取任务状态
     */
    public function status() {
        $status = &$this->status;
        // 获取进程句柄的状态
        $status = proc_get_status($this->handle);
        $status ['start_time'] = $this->start_time;
        $status ['excute_time'] = microtime(true) - $this->start_time;

        return $status;
    }
}