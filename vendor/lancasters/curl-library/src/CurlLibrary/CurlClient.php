<?php

namespace CurlLibrary;

use Blackfire\Bridge\Symfony\BlackfiredHttpClient;
use CurlLibrary\Exception\V2Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\RequestStack;
use Blackfire\Client as BlackfireClient;
use Blackfire\ClientConfiguration;

/**
 * @method ResponseInterface get($uri, array $options = [])
 * @method ResponseInterface head($uri, array $options = [])
 * @method ResponseInterface put($uri, array $options = [])
 * @method ResponseInterface post($uri, array $options = [])
 * @method ResponseInterface patch($uri, array $options = [])
 * @method ResponseInterface delete($uri, array $options = [])
 *
 * Class CurlClient
 *
 * @package CurlLibrary
 */
class CurlClient implements ClientInterface, CurlClientInterface
{
    public const REQUEST_TIME_LIMIT = 25;

    public const EXTENDED_REQUEST_TIME_LIMIT = 45;

    private const MULTIPART_BOUNDARY_MISSING_MESSAGE = "Multipart request must contain the boundary in the headers.";

    public const TIME_THRESHOLDS = [
        LogLevel::DEBUG     => 0,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 5,
        LogLevel::EMERGENCY => 10,
    ];

    /**
     * @var string
     */
    private $guardPrivateKey;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var CurlHttpClient
     */
    private $curlClient;

    /**
     * @var array
     */
    private $config;


    /**
     * CurlClient constructor.
     *
     * @param string $appName
     * @param array  $config
     */
    public function __construct(string $appName, array $config = [])
    {
        $this->config = $config;
        $this->config["headers"] = array_merge($this->config["headers"] ?? [], ["user-agent" => $appName]);
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
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws V2Exception
     */
    public function __call($method, $args)
    {
        $options= ['base_uri' => $this->config['base_uri']];
        $headers = $this->config['headers'] ?? [];
        $body = $this->getBodyFromArgs($args);

        $isMultipart = $this->isRequestMultipart($args);

        if ($isMultipart) {
            $this->validateMultiPart($args);
            $boundary = $this->extractBoundary($args);
        }

        if (isset($args[1]['guard_protected']) && $args[1]['guard_protected'] === true && $this->guardPrivateKey != '') {
            $argsData = $this->getUriAndHostFromArgs($args);
            $secretData = $isMultipart ?
                $this->getSecretData($method, $argsData['host'], $argsData['uri'], $boundary) :
                $this->getSecretData($method, $argsData['host'], $argsData['uri'], $body);
            $requestSecret = $this->encodeSecret($secretData, $this->guardPrivateKey);
            $headers['x-guard-secret'] = $requestSecret;

            $this->validateOptions($body, $method, $options);
        }

        $headers['content-type'] = isset($options['form_params']) ? 'application/x-www-form-urlencoded' : 'application/json';
        $options['headers'] = isset($args[1]['headers']) ? array_merge($headers, $args[1]['headers']) : $headers;
        $options['body'] = $body;
        $options['max_duration'] = isset($args[1]['max_duration']) && is_int($args[1]['max_duration']) ? $args[1]['max_duration'] : self::REQUEST_TIME_LIMIT;

        $logThresholds = $args[1]['log_thresholds'] ?? null;

        try {
            $uri = $this->getUri($args);
            $method = strtoupper($method);
            $response = $this->getCurlClient()->request($method, $uri, $options);

            // Try to get content to run checkStatusCode method and throw ClientException if bad http status
            $response->getContent(true);
            $this->analyzeResponseTime($response, $logThresholds);
        } catch (ClientException|ServerException $e) {
            $originalResponse = $e->getResponse();
            $request = new \GuzzleHttp\Psr7\Request($method, $uri);
            $response = new Response($originalResponse->getStatusCode(), [], $originalResponse->getContent(false));
            $e = new \GuzzleHttp\Exception\ClientException($e->getMessage(), $request, $response);

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
                throw (new V2Exception(implode(',', $errors), $originalResponse->getStatusCode(), $e))->setErrors($errors);
            }

            // If multidimensional array
            throw (new V2Exception('Error', $originalResponse->getStatusCode(), $e))->setErrors($errors);
        } catch (TransportException $e) {
            // Request timeout
            $errorMessage = sprintf(
                'Operation timeout after %d seconds requesting %s/%s',
                self::REQUEST_TIME_LIMIT,
                $this->config['base_uri'],
                $args[0]
            );

            throw new V2Exception($errorMessage, 500, $e);
        }

        return new CommonClientResponse($response);
    }

    private function isRequestMultipart(array $args): bool
    {
        return isset($args[1]['headers']['content-type']) && strpos($args[1]['headers']['content-type'], 'multipart/form-data') !== false;
    }

    private function validateMultiPart(array $args): void
    {
        if (!isset($args[1]['headers']['content-type']) || strpos($args[1]['headers']['content-type'], 'boundary=') === false) {
            throw new \InvalidArgumentException(self::MULTIPART_BOUNDARY_MISSING_MESSAGE);
        }
    }

    private function extractBoundary(array $args): ?string
    {
        $contentTypeHeader = $args[1]['headers']['content-type'] ?? null;
        preg_match('/boundary=(["\']?)([^"\';]+)/', $contentTypeHeader, $matches);
        return $matches[2] ?? null;
    }

    /**
     * @return CurlHttpClient
     */
    protected function getCurlClient()
    {
        if (!$this->curlClient) {
            $this->curlClient = new CurlHttpClient();
            $this->pushBlackfire();
        }

        return $this->curlClient;
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

        if (isset($args[1]['json'])) {
            $body = \GuzzleHttp\json_encode($args[1]['json']);
        }

        return $body;
    }

    /**
     * @param \Symfony\Contracts\HttpClient\ResponseInterface $response
     * @param array|null                                      $logThresholds
     *
     * @return mixed|null
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function analyzeResponseTime(
        \Symfony\Contracts\HttpClient\ResponseInterface $response,
        ?array $logThresholds
    ) {
        $time         = $response->getInfo('total_time');
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
     * @param string $string
     *
     * @return bool
     */
    protected function isValidJson($string)
    {
        json_decode($string);

        return json_last_error() == JSON_ERROR_NONE;
    }

    private function pushBlackfire()
    {
        $clientId = getenv('BLACKFIRE_CLIENT_ID');
        $clientToken = getenv('BLACKFIRE_CLIENT_TOKEN');
        if ($clientId !== false && $clientToken !== false) {
            $clientConfiguration = new ClientConfiguration($clientId, $clientToken);
            $blackfire = new BlackfireClient($clientConfiguration);

            $this->curlClient = new BlackfiredHttpClient($this->curlClient, $blackfire);
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

    private function getUri(array $args): string
    {
        $uri = $args[0];

        if (isset($args[1]['query'])) {
            $query = $args[1]['query'];
            if (is_array($query)) {
                $query = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            }
            if (!is_string($query)) {
                throw new \InvalidArgumentException('query must be a string or array');
            }
            $uri = sprintf('%s?%s', $uri, $query);
        }

        return $uri;
    }

    private function validateOptions(string $body, string $method, array $options): void
    {
        if ('' !== $body && in_array(strtoupper($method), ['PUT', 'PATCH', 'POST'])) {
            if (isset($options['form_params']) && isset($options['multipart'])) {
                throw new \InvalidArgumentException('You cannot use '
                    .'form_params and multipart at the same time. Use the '
                    .'form_params option if you want to send application/'
                    .'x-www-form-urlencoded requests, and the multipart '
                    .'option to send multipart/form-data requests.'
                );
            }
        }
    }
}
