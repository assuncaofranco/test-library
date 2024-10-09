<?php

namespace CurlLibrary;

use CurlLibrary\Exception\V2Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Blackfire\Client as BlackfireClient;
use Blackfire\ClientConfiguration;
use Blackfire\Bridge\Guzzle\Middleware as BlackfireMiddleware;

/**
 * @method ResponseInterface get($uri, array $options = [])
 * @method ResponseInterface head($uri, array $options = [])
 * @method ResponseInterface put($uri, array $options = [])
 * @method ResponseInterface post($uri, array $options = [])
 * @method ResponseInterface patch($uri, array $options = [])
 * @method ResponseInterface delete($uri, array $options = [])
 *
 * Class GuzzleClient
 *
 * @package CurlLibrary
 */
class GuzzleClient implements ClientInterface
{
    /**
     * @var string
     */
    private $guardPrivateKey;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var int
     */
    private $ttl;

    const V2_REQUEST_IDENTIFIER_HEADER = 'X-V2-Request-Identifier';

    const TIME_THRESHOLDS = [
        LogLevel::DEBUG     => 0,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 5,
        LogLevel::EMERGENCY => 10,
    ];

    /**
     * @var \GuzzleHttp\Client
     */
    private $guzzleClient;

    /**
     * @var array
     */
    private $config;

    /**
     * CurlClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @param string $guardPrivateKey
     */
    public function setGuardPrivateKey($guardPrivateKey)
    {
        $this->guardPrivateKey = $guardPrivateKey;
    }

    /**
     * @param RequestStack $requestStack
     *
     * @return $this
     */
    public function setRequestStack($requestStack)
    {
        $this->requestStack = $requestStack;

        return $this;
    }

    /**
     * @param string $method
     * @param array  $args
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|mixed|ResponseInterface
     * @throws V2Exception
     */
    public function __call($method, $args)
    {
        if (isset($args[1]['guard_protected']) && $args[1]['guard_protected'] === true && $this->guardPrivateKey != '') {
            $argsData = $this->getUriAndHostFromArgs($args);
            $body = $this->getBodyFromArgs($args);
            $secretData = $this->getSecretData($method, $argsData['host'], $argsData['uri'], $body);
            $requestSecret = $this->encodeSecret($secretData, $this->guardPrivateKey);

            $headers            = ['x-guard-secret' => $requestSecret];
            $args[1]['headers'] = isset($args[1]['headers']) ? array_merge($headers, $args[1]['headers']) : $headers;
        }

        $logThresholds = $args[1]['log_thresholds'] ?? null;
        $args[1]['on_stats'] = function (TransferStats $stats) use ($logThresholds) {
            $this->analyzeResponseTime($stats, $logThresholds);
        };

        $headers = $this->requestStack instanceof RequestStack ? $this->handleV2RequestIdentifier() : [];

        $args[1]['headers'] = isset($args[1]['headers']) ? array_merge($headers, $args[1]['headers']) : $headers;

        try {
            $response = $this->getGuzzleClient()->__call($method, $args);
        } catch (BadResponseException $e) {
            $body   = (string) $e->getResponse()->getBody();
            $errors = [$e->getMessage()];

            if ($this->isValidJson($body)) {
                $message = json_decode($body, true);

                if (is_array($message) && isset($message['errors'])) {
                    $errors = $message['errors'];
                    $errors = is_array($errors) ? $errors : [$errors];
                }
            }

            // If not multidimensional array
            if (count($errors) == count($errors, COUNT_RECURSIVE)) {
                throw (new V2Exception(implode(',', $errors), $e->getResponse()->getStatusCode(), $e))->setErrors($errors);
            }

            // If multidimensional array
            throw (new V2Exception('Error', $e->getResponse()->getStatusCode(), $e))->setErrors($errors);
        }

        return $response;
    }

    /**
     * @return Client
     */
    protected function getGuzzleClient()
    {
        if (!$this->guzzleClient) {
            $this->guzzleClient = new Client($this->config);

            /** @var HandlerStack $handlerStack */
            $handlerStack = $this->guzzleClient->getConfig('handler');
            $this->pushBlackfireMiddleware($handlerStack);
        }

        return $this->guzzleClient;
    }

    /**
     * @param array $args
     *
     * @return array
     */
    protected function getUriAndHostFromArgs(array $args)
    {
        $fullUri = $args[0];

        if (parse_url($fullUri, PHP_URL_HOST) === null) {
            $host = isset($this->config['base_uri']) ? parse_url($this->config['base_uri'], PHP_URL_HOST) : null;
            $uri  = strpos($args[0], '/') !== 0 ? '/'.$args[0] : $args[0];
        } else {
            $host = parse_url($fullUri, PHP_URL_HOST);
            $uri  = parse_url($fullUri, PHP_URL_PATH);
        }

        return [
            'host' => $host,
            'uri'  => $uri,
        ];
    }

    /**
     * @param array $args
     *
     * @return string
     */
    protected function getBodyFromArgs(array $args)
    {
        $body = '';

        if (isset($args[1]['body'])) {
            $body = $args[1]['body'];
        }
        if (isset($args[1]['form_params'])) {
            $body = http_build_query($args[1]['form_params'], null, '&');
        }

        return $body;
    }

    /**
     * @param TransferStats $transferStats
     * @param array|null    $logThresholds
     *
     * @return null
     */
    protected function analyzeResponseTime(TransferStats $transferStats, ?array $logThresholds)
    {
        $time         = $transferStats->getTransferTime();
        $currentLevel = null;

        if (!is_null($logThresholds)) {
            $timeThresholds = array_replace(self::TIME_THRESHOLDS, array_intersect_key($logThresholds, self::TIME_THRESHOLDS));
        } else {
            $timeThresholds = self::TIME_THRESHOLDS;
        }

        foreach ($timeThresholds as $level => $threshold) {
            if ($time < $threshold && $currentLevel) {
                break;
            } elseif ($time > $threshold) {
                $currentLevel = array_keys($timeThresholds)[array_search($level, array_keys($timeThresholds))];
            }
        }

        return $currentLevel;
    }

    /**
     * @return array
     */
    protected function handleV2RequestIdentifier()
    {
        if (method_exists($this->requestStack, 'getMainRequest')) {
            $mainRequest = $this->requestStack->getMainRequest();
        } else {
            $mainRequest = $this->requestStack->getMasterRequest();
        }

        $request = $mainRequest instanceof Request ? $mainRequest : Request::createFromGlobals();

        if ($request->headers->has(self::V2_REQUEST_IDENTIFIER_HEADER)) {
            $request->attributes->set('v2_request_identifier', $request->headers->get(self::V2_REQUEST_IDENTIFIER_HEADER));
        }

        if (!$request->attributes->has('v2_request_identifier')) {
            $request->attributes->set('v2_request_identifier', uniqid());
        }

        return [
            self::V2_REQUEST_IDENTIFIER_HEADER => $request->attributes->get('v2_request_identifier'),
        ];
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    protected function isValidJson($string)
    {
        json_decode($string);

        return json_last_error() == JSON_ERROR_NONE;
    }

    /**
     * @param HandlerStack $handlerStack
     */
    private function pushBlackfireMiddleware(HandlerStack $handlerStack)
    {
        $clientId = getenv('BLACKFIRE_CLIENT_ID');
        $clientToken = getenv('BLACKFIRE_CLIENT_TOKEN');
        if ($clientId !== false && $clientToken !== false) {
            $clientConfiguration = new ClientConfiguration($clientId, $clientToken);
            $blackfire = new BlackfireClient($clientConfiguration);

            $handlerStack->push(BlackfireMiddleware::create($blackfire), 'blackfire');
        }
    }

    /**
     * @param string      $method
     * @param string|null $host
     * @param string|null $pathInfo
     * @param string|null $content
     *
     * @return string
     * @throws \Exception
     */
    private function getSecretData(string $method, ?string $host, ?string $pathInfo, ?string $content)
    {
        $method = strtoupper($method);

        if (in_array($method, ['GET', 'DELETE'])) {
            return $method.$host.urldecode($pathInfo);
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return $method.$host.urldecode($pathInfo).md5($content);
        } else {
            throw new \Exception('Request method not supported.');
        }
    }

    /**
     * @param string $data
     * @param string $privateKey
     *
     * @return string
     */
    private function encodeSecret(string $data, string $privateKey)
    {
        return hash_hmac('sha256', $data, $privateKey);
    }
}
