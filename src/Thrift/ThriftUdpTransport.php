<?php

namespace Romanpravda\Laravel\Tracing\Thrift;

use Thrift\Exception\TTransportException;
use Thrift\Transport\TTransport;

/**
 * Copy of OpenTelemetry\Contrib\Jaeger\ThriftUdpTransport from open-telemetry/sdk-contrib package
 *
 * For original @see https://github.com/open-telemetry/opentelemetry-php/blob/main/src/Contrib/Jaeger/ThriftUdpTransport.php
 */
class ThriftUdpTransport extends TTransport
{
    // MAX_UDP_PACKET indicates max packet size that could be sent by UDP
    private const MAX_UDP_PACKET = 65000;

    /**
     * Host of Jaeger's server.
     *
     * @var string
     */
    protected $server;

    /**
     * Port of Jaeger's server.
     *
     * @var int
     */
    protected $port;

    /**
     * Socket to Jaeger.
     *
     * @var false|resource|\Socket
     */
    protected $socket;

    /**
     * Internal buffer.
     *
     * @var string
     */
    protected $buffer = '';

    /**
     * ThriftUdpTransport constructor.
     *
     * @param \Romanpravda\Laravel\Tracing\Thrift\ParsedEndpointUrl $parsedEndpoint
     *
     * @throws \Thrift\Exception\TTransportException
     */
    public function __construct(ParsedEndpointUrl $parsedEndpoint)
    {
        $this->server = $parsedEndpoint->getHost();
        $this->port = $parsedEndpoint->getPort();

        // open a UDP socket to somewhere
        if (!($this->socket = \socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))) {
            $errorcode = \socket_last_error();
            $errormsg = \socket_strerror($errorcode);

            error_log("jaeger: transport: Couldn't create socket: [$errorcode] $errormsg");

            throw new TTransportException('unable to open UDP socket', TTransportException::UNKNOWN);
        }
    }

    /**
     * Whether this transport is open.
     *
     * @return boolean true if open
     */
    public function isOpen(): bool
    {
        return $this->socket !== null;
    }

    /**
     * Open the transport for reading/writing
     *
     * Open does nothing as connection is opened on creation
     * Required to maintain thrift.TTransport interface
     */
    public function open(): void
    {
    }

    /**
     * Close the transport.
     */
    public function close(): void
    {
        \socket_close($this->socket);
        $this->socket = null;
    }

    /**
     * Read some data into the array.
     *
     * @param int $len How much to read
     *
     * @return string The data that has been read
     */
    public function read($len): string
    {
        // not implemented
        return '';
    }

    /**
     * Writes the given data out.
     *
     * @param string $buf The data to write
     *
     * @throws TTransportException if writing fails
     */
    public function write($buf): void
    {
        // ensure that the data will still fit in a UDP packeg
        if (strlen($this->buffer) + strlen($buf) > self::MAX_UDP_PACKET) {
            throw new TTransportException('Data does not fit within one UDP packet', TTransportException::UNKNOWN);
        }

        // buffer up some data
        $this->buffer .= $buf;
    }

    /**
     * Flushes buffer to Jaeger.
     *
     * @return void
     */
    public function flush(): void
    {
        // no data to send; don't send a packet
        if ($this->buffer === '') {
            return;
        }

        // TODO(tylerc): This assumes that the whole buffer successfully sent... I believe
        // that this should always be the case for UDP packets, but I could be wrong.

        // flush the buffer to the socket
        if (!\socket_sendto($this->socket, $this->buffer, strlen($this->buffer), 0, $this->server, $this->port)) {
            $errorcode = \socket_last_error();
            $errormsg = \socket_strerror($errorcode);
            error_log("jaeger: transport: Could not flush data: [$errorcode] $errormsg");
        }

        $this->buffer = ''; // empty the buffer
    }
}