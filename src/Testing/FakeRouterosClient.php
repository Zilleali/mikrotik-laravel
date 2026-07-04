<?php

namespace ZillEAli\MikrotikLaravel\Testing;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * FakeRouterosClient
 *
 * Test double for RouterosClient. Intercepts all query methods
 * and returns pre-loaded responses without opening a real socket.
 *
 * Used internally by MikrotikFake — not intended for direct use.
 *
 * @package ZillEAli\MikrotikLaravel\Testing
 * @author  Zill E Ali <zilleali1245@gmail.com>
 */
class FakeRouterosClient extends RouterosClient
{
    /** @var list<string> */
    private array $recorded = [];

    /**
     * @param array<string, list<array<string, string>>> $responses  Command responses keyed by command path
     * @param array<string, list<array<string, string>>> $streams    Stream responses keyed by command path
     */
    public function __construct(
        private array $responses,
        private array $streams,
    ) {
        parent::__construct(host: '127.0.0.1');
    }

    public function connect(): static
    {
        $this->connected = true;

        return $this;
    }

    public function isConnected(): bool
    {
        return true;
    }

    /** @param string[] $words */
    public function send(array $words): array
    {
        return [];
    }

    /** @return list<array<string, string>> */
    public function query(string $command, array $params = [], array $queries = []): array
    {
        $this->recorded[] = $command;

        return $this->responses[$command] ?? [];
    }

    /** @return \Generator<int, array<string, string>> */
    public function queryStream(string $command, array $params = [], array $queries = []): \Generator
    {
        $this->recorded[] = $command;

        foreach ($this->streams[$command] ?? [] as $row) {
            yield $row;
        }
    }

    /** @return list<string> */
    public function sendRaw(string $command, array $params = []): array
    {
        $this->recorded[] = $command;

        return [];
    }

    /** @param list<array<string, string>> $rows */
    public function setResponse(string $command, array $rows): void
    {
        $this->responses[$command] = $rows;
    }

    /** @param list<array<string, string>> $rows */
    public function setStream(string $command, array $rows): void
    {
        $this->streams[$command] = $rows;
    }

    /** @return list<string> */
    public function recordedQueries(): array
    {
        return $this->recorded;
    }
}
