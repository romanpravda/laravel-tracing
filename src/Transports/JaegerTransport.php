<?php

namespace Romanpravda\Laravel\Tracing\Transports;

use Jaeger\Jaeger;
use Jaeger\JaegerThrift;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Batch;
use Jaeger\Thrift\Process;
use Jaeger\Transport\Transport as TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Romanpravda\Laravel\Tracing\Thrift\ParsedEndpointUrl;
use Romanpravda\Laravel\Tracing\Thrift\ThriftUdpTransport;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TCompactProtocol;

class JaegerTransport implements TransportInterface
{
    // DEFAULT_AGENT_HOST_PORT indicates the default Jaeger's host and port
    private const DEFAULT_AGENT_HOST_PORT = 'localhost:6831';

    // DEFAULT_BUFFER_SIZE indicates the default maximum buffer size, or the size threshold
    // at which the buffer will be flushed to the agent.
    private const DEFAULT_BUFFER_SIZE = 1;

    /**
     * Transport to Jaeger
     *
     * @var \Romanpravda\Laravel\Tracing\Thrift\ThriftUdpTransport
     */
    private $transport;

    /**
     * Jaeger's client.
     *
     * @var \Jaeger\Thrift\Agent\AgentClient
     */
    private $client;

    /**
     * Internal buffer.
     *
     * @var array
     */
    private $buffer = [];

    /**
     * Process tag.
     *
     * @var \Jaeger\Thrift\Process
     */
    private $process;

    /**
     * Span's buffer size.
     *
     * @var int
     */
    private $maxBufferSize;

    /**
     * Converter to Jaeger Thrift format.
     *
     * @var \Jaeger\JaegerThrift
     */
    private $jaegerThrift;

    /**
     * Logger service.
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * JaegerTransport constructor.
     *
     * @param string $agentHostPort
     * @param int $maxBufferSize
     * @param \Psr\Log\LoggerInterface|null $logger
     *
     * @throws \Thrift\Exception\TTransportException
     */
    public function __construct(string $agentHostPort = self::DEFAULT_AGENT_HOST_PORT, int $maxBufferSize = self::DEFAULT_BUFFER_SIZE, ?LoggerInterface $logger = null)
    {
        $parsedEndpoint = (new ParsedEndpointUrl($agentHostPort))
            ->validateHost() //This is because the host is required downstream
            ->validatePort(); //This is because the port is required downstream

        $this->transport = new ThriftUdpTransport($parsedEndpoint);

        $p = new TCompactProtocol($this->transport);
        $this->client = new AgentClient($p, $p);

        $this->maxBufferSize = ($maxBufferSize > 0 ? $maxBufferSize : self::DEFAULT_BUFFER_SIZE);

        $this->jaegerThrift = new JaegerThrift();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Appending spans to buffer.
     *
     * @param \Jaeger\Jaeger $jaeger
     *
     * @return void
     */
    public function append(Jaeger $jaeger): void
    {
        /** @var \Jaeger\Span $span */
        foreach ($jaeger->spans as $span) {
            $processTags = [];
            foreach ($span->tags as $tagName => $tagValue) {
                if (stripos($tagName, 'process.') === 0) {
                    $processTags[$tagName] = $tagValue;
                    unset($span->tags[$tagName]);
                }
            }
            if (isset($span->tags['service.major'], $span->tags['service.minor'])) {
                $serviceName = sprintf('%s-%s', $span->tags['service.major'], $span->tags['service.minor']);
                unset($span->tags['service.major'], $span->tags['service.minor']);
            } else {
                $serviceName = $jaeger->serviceName;
            }
            $this->process = new Process([
                'serviceName' => $serviceName,
                'tags' => $this->jaegerThrift->buildTags($processTags),
            ]);

            $spanThrift = $this->jaegerThrift->buildSpanThrift($span);
            $this->buffer[] = $spanThrift;

            $this->flush();
        }
    }

    /**
     * Flushing buffer.
     *
     * @return void
     */
    public function flush(): void
    {
        $spans = count($this->buffer);

        // buffer not full yet
        if ($spans < $this->maxBufferSize) {
            return;
        }

        // no spans to flush
        if ($spans <= 0) {
            return;
        }

        try {
            if (!$this->transport->isOpen()) {
                $this->transport->open();
            }

            // emit a batch
            $this->client->emitBatch(new Batch([
                'process' => $this->process,
                'spans' => $this->buffer,
            ]));

            // flush the UDP data
            $this->transport->flush();

            // reset the internal buffer
            $this->buffer = [];

            // reset the process tag
            $this->process = null;
        } catch (TTransportException $e) {
            $this->logger->error('jaeger: transport failure: ' . $e->getMessage());

            return;
        }
    }

    /**
     * Closing transport.
     *
     * @return void
     */
    public function close(): void
    {
        $this->flush(); // flush all remaining data
        $this->transport->close();
    }
}