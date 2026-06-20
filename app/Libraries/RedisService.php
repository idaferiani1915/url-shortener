<?php

namespace App\Libraries;

use Predis\Client;
use Exception;

class RedisService
{
    private $client = null;
    private $enabled = false;

    public function __construct()
    {
        $host = env('redis.host', '127.0.0.1');
        $port = env('redis.port', 6379);
        $pass = env('redis.password', null);
        if ($pass === 'null' || $pass === '') {
            $pass = null;
        }

        try {
            $this->client = new Client([
                'scheme'             => 'tcp',
                'host'               => $host,
                'port'               => $port,
                'password'           => $pass,
                'connection_timeout' => 1.0, // Fail fast if Redis is down
                'read_write_timeout' => 1.0,
            ]);
            // Test connection
            $this->client->ping();
            $this->enabled = true;
        } catch (Exception $e) {
            $this->enabled = false;
            log_message('warning', 'Redis connection failed: ' . $e->getMessage() . '. Caching and queueing disabled.');
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get value from cache
     */
    public function get(string $key)
    {
        if (!$this->enabled) return null;
        try {
            return $this->client->get($key);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Set value in cache with optional TTL (in seconds)
     */
    public function set(string $key, string $value, int $ttl = null): bool
    {
        if (!$this->enabled) return false;
        try {
            if ($ttl) {
                $this->client->setex($key, $ttl, $value);
            } else {
                $this->client->set($key, $value);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Delete value from cache
     */
    public function del(string $key): bool
    {
        if (!$this->enabled) return false;
        try {
            $this->client->del($key);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Increment key
     */
    public function incr(string $key): int
    {
        if (!$this->enabled) return 0;
        try {
            return $this->client->incr($key);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Set expire on key
     */
    public function expire(string $key, int $seconds): bool
    {
        if (!$this->enabled) return false;
        try {
            $this->client->expire($key, $seconds);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Push to a list (for click logs queue)
     */
    public function lpush(string $key, string $value): bool
    {
        if (!$this->enabled) return false;
        try {
            $this->client->lpush($key, [$value]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Pop from a list
     */
    public function rpop(string $key)
    {
        if (!$this->enabled) return null;
        try {
            return $this->client->rpop($key);
        } catch (Exception $e) {
            return null;
        }
    }
}
