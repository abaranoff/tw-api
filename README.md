# tw-api

![Image of Teamwox logo](http://www.teamwox.com/b/logo.gif)

**tw-api** is PHP Web API for [Teamwox Groupware](http://www.teamwox.com) from [MetaQuotes](https://www.metaquotes.net).

Usage:

```php
$taskId = 1087;

$content = 'Test comment';

$logger        = new Logger('TeamWoxGate');
$streamHandler = new StreamHandler('logs/tw.log', Logger::INFO);
$logger->pushHandler($streamHandler);

$teamwox = new Gate([
    'sessionId' => uniqid(),
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

# Changelog:

v.0.1
Initial release. 
- Supports posting to Teamwox tasks and servicedesks as comments.
- Supports attachments to posts


TEAMWOX is a trademark of METAQUOTES SOFTWARE CORP.
