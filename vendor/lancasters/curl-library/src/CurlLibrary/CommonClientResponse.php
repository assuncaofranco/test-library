<?php

namespace CurlLibrary;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use \Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\Response\CurlResponse;

class CommonClientResponse implements ResponseInterface
{
    /**
     * @var CurlResponse
     */
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getBody(bool $throw = false): string
    {
        return $this->response->getContent($throw);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(bool $throw = false): string
    {
        return $this->response->getContent($throw);
    }

    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(bool $throw = true): array
    {
        return $this->response->toArray($throw);
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(): void
    {
        $this->response->cancel();
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo(string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(bool $throw = true): array
    {
        return $this->response->getHeaders($throw);
    }
}
