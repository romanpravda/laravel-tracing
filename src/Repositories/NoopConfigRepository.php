<?php

declare(strict_types=1);

namespace Romanpravda\Laravel\Tracing\Repositories;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class NoopConfigRepository implements ConfigRepository
{
    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return false;
    }

    /**
     * Get the specified configuration value.
     *
     * @param array|string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null): mixed
    {
        return $default;
    }

    /**
     * Get all of the configuration items for the application.
     *
     * @return array
     */
    public function all(): array
    {
        return [];
    }

    /**
     * Set a given configuration value.
     *
     * @param array|string $key
     * @param mixed $value
     *
     * @return void
     */
    public function set($key, $value = null): void
    {
    }

    /**
     * Prepend a value onto an array configuration value.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function prepend($key, $value): void
    {
    }

    /**
     * Push a value onto an array configuration value.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function push($key, $value): void
    {
    }
}