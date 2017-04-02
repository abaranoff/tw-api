<?php
/**
 * @author Alexey Baranov <alexey.baranov@outlook.com>
 * @date: 18.03.2017 16:27
 */

namespace Teamwox;

use Teamwox\Exception\BadLoggerException;
use Teamwox\Exception\BadBaseUrlException;
use Teamwox\Exception\BadAttachmentsException;
use Teamwox\Exception\AuthorizationException;
use Teamwox\Exception\BadCredentialsException;
use Teamwox\Exception\SessionIdException;
use Teamwox\Exception\ServicedeskPostException;
use Teamwox\Exception\TaskPostException;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;

/**
 * Class Gate
 *
 * @package Teamwox
 */
class Gate
{
    /**
     * Force apply message formatter flag
     *
     * @var bool
     */
    private $forcePrepareContent = true;

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * Session identifier for log purpose
     *
     * @var string
     */
    private $sessionId = null;

    /**
     * Base Teamwox URI
     *
     * @var string
     */
    private $baseUri = null;

    /**
     * Teamwox user
     *
     * @var string
     */
    private $login = null;

    /**
     * Teamwox user's password
     *
     * @var string
     */
    private $password = null;

    /**
     * Verify SSL flag
     *
     * @var string
     */
    private $verifySSL = true;

    /**
     * CURL client
     *
     * @var Client
     */
    private $client = null;

    /**
     * Is already logged in Teamwox
     *
     * @var bool
     */
    private $loggedIn = false;

    /**
     * Gate constructor.
     *
     * Options:
     *     'sessionId'           - obligatory - Identifier for request to simplifier the search in logs etc
     *     'logger'              - obligatory - Logger is obligatory and must be PSR compatible (Psr\Log\LoggerInterface)
     *     'baseUri'             - obligatory - Teamwox base URL
     *     'login'               - obligatory - Teamwox user login
     *     'password'            - obligatory - Teamwox user password
     *
     *     'forcePrepareContent' - Force replace styles in content to Teamwox styles, default: true
     *     'verifySSL'           - Check SSL certs flag, default: true
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);

        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'cookies' => true
        ]);
    }

    /**
     * Adds message as comment to Teamwox task
     *
     * Example of attachment configuration:
     *
     * $attachments = [
     *     [
     *         'contents' => fopen('/path/to/file', 'r') // supports streams
     *     ],
     *     [
     *         'contents' => 'hello', // supports content of the file
     *         'filename' => 'filename.txt'
     *     ]
     * ];
     *
     * @param string $taskId Task ID where to post comment
     * @param string $content Post content
     * @param array $attachments Optional list of attachments
     *
     * @return boolean
     */
    public function comment2task($taskId, $content, array $attachments = [])
    {
        $this->login();

        $content = $this->formatContent($content);

        $this->logger->info($this->sessionId.' - Start posting to task #'.$taskId);

        $response = $this->storeTeamwoxComment(
            '/tasks/view/'.$taskId.'/update_comment',
            [[
                'name'     => 'comment_body',
                'contents' => $content
            ], [
                'name'     => 'comment_id',
                'contents' => '0'
            ]],
            ['comment_attachments' => $attachments]
        );

        $body = $response->getBody();

        if ($body != "OK") {
            $this->logger->error($this->sessionId.' - Posting to task #'.$taskId.' failed.');

            throw new TaskPostException('Posting to task #'.$taskId.' failed.');
        }

        $this->logger->info($this->sessionId.' - Successfully posted to task #'.$taskId.', content length: '.strlen($content));

        return true;
    }

    /**
     * Adds message as comment to Teamwox servicedesk
     *
     * Example of attachment configuration:
     *
     * $attachments = [
     *     [
     *         'contents' => fopen('/path/to/file', 'r') // supports streams
     *     ],
     *     [
     *         'contents' => 'hello', // supports content of the file
     *         'filename' => 'filename.txt'
     *     ]
     * ];
     *
     * @param string $serviceDeskId Servicedesk ID where to post comment
     * @param string $content Post content
     * @param array $attachments Optional list of attachments
     *
     * @return boolean
     */
    public function comment2servicedesk($serviceDeskId, $content, array $attachments = [])
    {
        $this->login();

        $this->logger->info($this->sessionId.' - Start posting to servicedesk #'.$serviceDeskId);

        $content = $this->formatContent($content);

        $response = $this->storeTeamwoxComment(
            '/servicedesk/comment/update/'.$serviceDeskId,
            [[
                'name'     => 'content',
                'contents' => $content
            ]],
            ['attachments' => $attachments]
        );

        $body = $response->getBody();

        if (false === strpos($body, "comment_id")) {
            $this->logger->error($this->sessionId.' - Posting to servicedesk #'.$serviceDeskId.' failed.');

            throw new ServicedeskPostException('Posting to servicedesk #'.$serviceDeskId.' failed.');
        }

        $this->logger->info($this->sessionId.' - Successfully posted to servicedesk #'.$serviceDeskId.', content length: '.strlen($content));

        return true;
    }

    /**
     * Logins user to Teamwox
     *
     * @return void
     */
    public function login()
    {
        if ($this->loggedIn) {
            return;
        }

        $this->logger->info($this->sessionId.' - Login to '.$this->baseUri);

        $response = $this->client->post(
            '/server/login',
            [
                'verify' => $this->verifySSL,
                'form_params' => [
                    'auth'     => 'Login',
                    'login'    => $this->login,
                    'password' => $this->password,
                ],
                'headers'  => [
                    'X-Requested-With' => 'XMLHttpRequest'
                ]
            ]
        );

        $headers = $response->getHeaders();

        if (!isset($headers["Set-Cookie"]) || !isset($headers["Set-Cookie"][0])) {
            $message = 'Authorization to '.$this->baseUri.' failed with login: '.$this->login;
            $this->logger->error($this->sessionId.' - '.$message);
            throw new AuthorizationException($message);
        }

        $this->loggedIn = true;

        $this->logger->info($this->sessionId.' - Successfully logged into '.$this->baseUri.' as '.$this->login);
    }

    /**
     * Makes POST request to Teamwox and stores comment
     *
     * @param string $url
     * @param array $formParams
     * @param array $attachments
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function storeTeamwoxComment($url, array $formParams, array $attachments = [])
    {
        // Guzzle is smart, cookies shared through requests
        $params = [
            'verify' => $this->verifySSL,
            'headers'  => [
                'X-Requested-With' => 'XMLHttpRequest'
            ]
        ];

        $params['multipart'] = $formParams;

        if (reset($attachments)) {
            list($fieldName, $files) = each($attachments);

            foreach ($files as $a) {

                if (!isset($a['contents'])) {
                    throw new BadAttachmentsException("One of attachments doesn't have an \"contents\" attribute");
                }

                $a['name'] = $fieldName;

                $params['multipart'][] = $a;
            }
        }

        return $response = $this->client->post(
            $url,
            $params
        );
    }

    /**
     * Prepares content to posting to Teamwox
     *
     * @param string $content
     * @return string
     */
    private function formatContent($content)
    {
        if ($this->forcePrepareContent) {
            $formatter = new Formatter();
            $content = $formatter->format($content);
        }

        return $content;
    }

    /**
     * Applies options, initializes the object
     *
     * @param array $options
     * @return void
     */
    private function setOptions(array $options = [])
    {
        if (!isset($options['sessionId']) || !is_string($options['sessionId'])) {
            throw new SessionIdException('Session ID is obligatory and must be string');
        }
        $this->sessionId = $options['sessionId'];

        $this->baseUri = $options['baseUri'];        if (!isset($options['baseUri']) || !is_string($options['baseUri'])) {
            throw new BadBaseUrlException('Base URI is obligatory and must be string');
        }
        $this->baseUri = $options['baseUri'];

        if (!isset($options['logger']) || !($options['logger'] instanceof LoggerInterface)) {
            throw new BadLoggerException('Logger is obligatory and must be PSR Compatible');
        }
        $this->logger = $options['logger'];

        if (!isset($options['login']) || !is_string($options['login'])) {
            throw new BadCredentialsException('Login is incorrect');
        }
        $this->login = $options['login'];

        if (!isset($options['password']) || !is_string($options['password'])) {
            throw new BadCredentialsException('Password is incorrect');
        }
        $this->password = $options['password'];

        if (isset($options['forcePrepareContent']) && is_bool($options['forcePrepareContent'])) {
            $this->forcePrepareContent = $options['forcePrepareContent'];
        }

        if (isset($options['verifySSL']) && is_bool($options['verifySSL'])) {
            $this->verifySSL = $options['verifySSL'];
        }
    }
}