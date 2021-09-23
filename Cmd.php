<?php

namespace MultTask;

/**
 * 命令类
 */

class Cmd {

    private $cmd;
    private $param;

    //指定任务运行所需的环境变量
    const PARAM_KEY_ENV = 'env';
    //指定任务运行所需的当前路径
    const PARAM_KEY_CWD = 'cwd';
    //超时时间,单位为秒,默认为0,值为0时,永不超时.
    const PARAM_KEY_TIMEOUT = 'timeout';
    //错误日志的路径
    const PARAM_KEY_ERROR_LOG = 'error_log';
    //结果类型：1为json，2为文本，3为没有结果；
    const PARAM_KEY_RESULT_TYPE = 'result_type';
    //返回结果显示形式：1为所以队列执行完后显示，2为立即显示
    const PARAM_KEY_RESULT_SHOW_TYPE = 'result_show_type';

    public function __construct($cmd, $timeout = 0, $resultType = 3, $resultShowType = 2, $errorLog = '', $cwd = '', $env = []) {
        $this->cmd = $cmd;
        $this->param = [
            self::PARAM_KEY_CWD => $cwd,
            self::PARAM_KEY_ENV => $env,
            self::PARAM_KEY_TIMEOUT => $timeout,
            self::PARAM_KEY_ERROR_LOG => $errorLog,
            self::PARAM_KEY_RESULT_TYPE => $resultType,
            self::PARAM_KEY_RESULT_SHOW_TYPE => $resultShowType,
        ];
    }

    public function getCmd() {
        return $this->cmd;
    }

    public function getParam() {
        return $this->param;
    }

}