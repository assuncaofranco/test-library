<?php

namespace CurlLibrary;

use CurlLibrary\Exception\V2Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * @method ResponseInterface get($uri, array $options = [])
 * @method ResponseInterface head($uri, array $options = [])
 * @method ResponseInterface put($uri, array $options = [])
 * @method ResponseInterface post($uri, array $options = [])
 * @method ResponseInterface patch($uri, array $options = [])
 * @method ResponseInterface delete($uri, array $options = [])
 *
 * Class ClientInterface
 *
 * @package CurlLibrary
 */
interface ClientInterface
{
    /**
     * @param string $method
     * @param array  $args
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|mixed|ResponseInterface
     * @throws V2Exception
     */
    public function __call($method, $args);
}
