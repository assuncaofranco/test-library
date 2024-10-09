<?php

namespace CurlLibrary;

use CurlLibrary\Exception\V2Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use \LogicException;

/**
 * @method ResponseInterface get($uri, array $options = [])
 * @method ResponseInterface head($uri, array $options = [])
 * @method ResponseInterface put($uri, array $options = [])
 * @method ResponseInterface post($uri, array $options = [])
 * @method ResponseInterface patch($uri, array $options = [])
 * @method ResponseInterface delete($uri, array $options = [])
 *
 * Class ClientTest
 *
 * @package CurlLibrary
 */
class ClientTest implements ClientInterface, CurlClientInterface
{
    /**
     * @var array
     */
    public static array $mockedData = [];

    /**
     * @var array
     */
    private array $mockCounter = [];

    /**
     * @var array
     */
    private $config;

    /**
     * @var string|null
     */
    private $pathToMockDataFile;

    /**
     * ClientTest constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @param string $method
     * @param array  $args
     *
     * @return ResponseInterface
     * @throws V2Exception
     */
    public function __call($method, $args)
    {
        if (!in_array(strtolower($method), ['get', 'head', 'put', 'post', 'patch', 'delete'])) {
            return;
        }

        $url = $this->config['base_uri'].$args[0];

        $url = sprintf(
            '%s/%s',
            rtrim($this->config['base_uri'], '/'),
            ltrim($args[0], '/')
        );

        $mockedDatas = !is_null($this->pathToMockDataFile) ? unserialize(file_get_contents($this->pathToMockDataFile)) : self::$mockedData;

        foreach (array_reverse($mockedDatas) as $mockedData) {
            if (strcasecmp($mockedData['method'], $method) === 0 &&
                strcasecmp($mockedData['url'], $url) === 0
            ) {
                if (isset($mockedData['times'])) {
                    $key = mb_strtolower($mockedData['method'].$mockedData['url']);

                    if (!isset($this->mockCounter[$key])) {
                        $this->mockCounter[$key] = 0;
                    }

                    $this->mockCounter[$key]++;

                    if ($this->mockCounter[$key] > $mockedData['times']) {
                        continue;
                    }
                }

                if ($mockedData['code'] >= 400) {
                    $this->processErrors($mockedData['method'], $url, $mockedData['code'], $mockedData['data']);
                }

                return new Response(
                    $mockedData['code'],
                    [],
                    $mockedData['data'],
                    '1.1',
                );
            }
        }

        throw new LogicException("Please specify a mock data for '".strtoupper($method)." $url'.");
    }

    /**
     * @param string $path
     */
    public function setPathToMockDataFile($path)
    {
        $this->pathToMockDataFile = $path;
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
     * @param mixed $method
     * @param mixed $uri
     * @param mixed $code
     * @param mixed $body
     *
     * @throws V2Exception
     */
    private function processErrors($method, $uri, $code, $body)
    {
        $e = new BadResponseException("", new Request($method, $uri), new Response($code, [], $body));

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
}
