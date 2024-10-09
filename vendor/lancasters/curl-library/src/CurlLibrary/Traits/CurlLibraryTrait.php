<?php

namespace CurlLibrary\Traits;

use CurlLibrary\CurlClient;
use CurlLibrary\Interfaces\CurlLibraryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 *  @TODO: get rid of this trait
 *
 * Class CurlLibraryTrait
 *
 * @package AbstractLibrary\Traits
 */
trait CurlLibraryTrait
{
    /**
     * @var CurlClient
     */
    private $curlClient;

    /**
     * @param string $guardPrivateKey
     */
    public function setGuardPrivateKey($guardPrivateKey)
    {
        $this->curlClient->setGuardPrivateKey($guardPrivateKey);
    }

    /**
     * @param RequestStack $requestStack
     */
    public function setRequestStack(RequestStack $requestStack)
    {
        $this->curlClient->setRequestStack($requestStack);
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->curlClient->setLogger($logger);
    }
}
