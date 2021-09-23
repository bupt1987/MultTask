<?php

// 实际用的时候因为会autoload， 不需要require文件
require_once(__DIR__ . "/Task.php");
require_once(__DIR__ . "/Cmd.php");
require_once(__DIR__ . "/TaskManager.php");


if (isset($argv[1])) {
    usleep(100000);
    exit();
}

$oTask = new \MultTask\TaskManager();
$oTask->setCheckTime(500000);
$oTask->setConcurrency(8);
$oTask->setRunLog(true);

$file = __DIR__ . '/example.php';
for ($i = 1; $i <= 1000; $i++) {
    $sCmd = 'php ' . $file . ' ' . $i;
    $oTask->addTask($sCmd);
}
$oTask->run();

echo "\nFinished\n";