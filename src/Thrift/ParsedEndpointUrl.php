<?php

namespace Romanpravda\Laravel\Tracing\Thrift;

use InvalidArgumentException;

/**
 * Copy of OpenTelemetry\Contrib\Jaeger\ParsedEndpointUrl from open-telemetry/sdk-contrib package
 *
 * For original @see https://github.com/open-telemetry/opentelemetry-php/blob/main/src/Contrib/Jaeger/ParsedEndpointUrl.php
 */
class ParsedEndpointUrl
{
    /**
     * Endpoint.
     *
     * @var string
     */
    private $endpointUrl;

    /**
     * Parsed endpoint.
     *
     * @var array|false|int|string|null
     */
    private $parsedDsn;

    /**
     * ParsedEndpointUrl constructor.
     *
     * @param string $endpointUrl
     */
    public function __construct(string $endpointUrl)
    {
        $this->endpointUrl = $endpointUrl;

        $this->parsedDsn = parse_url($this->endpointUrl);

        if (!is_array($this->parsedDsn)) {
            throw new InvalidArgumentException('Unable to parse provided DSN for url: ' . $this->endpointUrl);
        }
    }

    /**
     * Returns endpoint.
     *
     * @return string
     */
    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    /**
     * Host's validation.
     *
     * @return $this
     */
    public function validateHost(): self
    {
        $this->validateUrlComponent('host');

        return $this;
    }

    /**
     * Returns host of endpoint.
     *
     * @return string
     */
    public function getHost(): string
    {
        $this->validateHost();

        return $this->parsedDsn['host'];
    }

    /**
     * Port's validation.
     *
     * @return $this
     */
    public function validatePort(): self
    {
        $this->validateUrlComponent('port');

        return $this;
    }

    /**
     * Returns port of endpoint.
     *
     * @return int
     */
    public function getPort(): int
    {
        $this->validatePort();

        return $this->parsedDsn['port'];
    }

    /**
     * Validation of url component.
     *
     * @param string $componentName
     *
     * @return void
     */
    private function validateUrlComponent(string $componentName): void
    {
        if (!isset($this->parsedDsn[$componentName])) {
            throw new InvalidArgumentException($this->endpointUrl . ' is missing the ' . $componentName);
        }
    }
}