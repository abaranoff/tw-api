<?php
/**
 * @author Alexey Baranov <alexey.baranov@outlook.com>
 * @date: 18.03.2017 16:27
 */

date_default_timezone_set('Europe/Moscow');

require __DIR__.'/../vendor/autoload.php';

use Teamwox\Gate;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$config = include "config.php";

$taskId = 1087;
$serviceDeskId = 7375;
$content = '<table><thead><tr><th>asd <br></th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th></tr></thead><tbody><tr><td><br></td><td>asd<br></td><td><br></td><td><br></td></tr><tr><td><br></td><td><br></td><td><br></td><td><br></td></tr></tbody></table><br>';

try {
    $logger        = new Logger('TeamWoxGate');
    $streamHandler = new StreamHandler($config['logPath'], Logger::INFO);
    $logger->pushHandler($streamHandler);

    $teamwox = new Gate([
        'sessionId'           => uniqid(),
        'forcePrepareContent' => true,

        'logger'   => $logger, // logger is obligatory and must be PSR compatible
        'baseUri'  => $config['baseUrl'],
        'login'    => $config['login'],
        'password' => $config['password'],
        'verifySSL' => false
    ]);

    $teamwox->comment2task($taskId, $content,
        [[
            'contents' => 'hello',
            'filename' => 'filename1.txt'
        ], [
            'contents' => fopen(__FILE__, "r")
        ]]
    );

    $teamwox->comment2servicedesk($serviceDeskId, $content,
        [[
            'contents' => 'hello',
            'filename' => 'filename1.txt'
        ], [
            'contents' => fopen(__FILE__, "r")
        ]]
    );
}
catch (Exception $e) {
    echo "ERROR: ".$e->getMessage();

    $logger->error($e->getMessage());

    $fileName = date('Ymd His-').$taskId;
    $content4restore = "===Start Content #$taskId===\n".
                        $content.
                        "\n===End Content #$taskId===";

    file_put_contents($config['failedPostingsPath'].'/'.$fileName, $content4restore, FILE_APPEND);
}



