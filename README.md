# tw-api
PHP Web API for Teamwox Groupware by MetaQuotes


```php
$taskId = 1087;

$content = 'Test commet';

$logger        = new Logger('TeamWoxGate');
$streamHandler = new StreamHandler('logs/tw.log', Logger::INFO);
$logger->pushHandler($streamHandler);

$teamwox = new Gate([
        'sessionId'           => uniqid(),
        'logger'   => $logger, 
        'baseUri'  => $config['baseUrl'],
        'login'    => $config['login'],
        'password' => $config['password']
    ]);

    $teamwox->comment2task($taskId, $content,
        [[
            'contents' => 'hello',
            'filename' => 'filename1.txt'
        ], [
            'contents' => fopen(__FILE__, "r")
        ]]
    );
```

See working example in examples/post.php.
