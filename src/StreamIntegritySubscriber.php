<?php

namespace GuzzleHttp\Subscriber\MessageIntegrity;

use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Event\HeadersEvent;
use GuzzleHttp\Message\ResponseInterface;

/**
 * Verifies the message integrity of a response only after the entire response
 * body has been read.
 */
class StreamIntegritySubscriber implements SubscriberInterface
{
    private $hash;
    private $expectedFn;

    /**
     * @param array $config Associative array of configuration options.
     * @see GuzzleHttp\Subscriber\ResponseSubscriber::__construct for a
     *     list of available configuration options.
     */
    public function __construct(array $config)
    {
        ResponseSubscriber::validateOptions($config);
        $this->expectedFn = $config['expected'];
        $this->hash = $config['hash'];
    }

    public static function getSubscribedEvents()
    {
        return ['headers' => ['onHeaders', -1]];
    }

    public function onHeaders(HeadersEvent $event)
    {
        $response = $event->getResponse();
        if (!($expected = $this->getExpected($response))) {
            return;
        }

        $request = $event->getRequest();
        $response->setBody(new ReadIntegrityStream(
            $response->getBody(),
            $this->hash,
            $expected,
            function ($result, $expected) use ($request, $response) {
                throw new MessageIntegrityException(
                    sprintf('Message integrity check failure. Expected '
                        . '"%s" but got "%s"', $expected, $result),
                    $request,
                    $response
                );
            }
        ));
    }

    private function getExpected(ResponseInterface $response)
    {
        if (!($body = $response->getBody())) {
            return false;
        } elseif ($response->hasHeader('Transfer-Encoding') ||
            $response->hasHeader('Content-Encoding')
        ) {
            // Currently does not support un-gzipping or inflating responses
            return false;
        }

        return call_user_func($this->expectedFn, $response);
    }
}
