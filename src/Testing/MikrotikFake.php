<?php

namespace ZillEAli\MikrotikLaravel\Testing;

use PHPUnit\Framework\Assert;
use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * MikrotikFake
 *
 * Test double for MikrotikManager. Swaps the container binding so all
 * calls via MikroTik:: facade or dependency injection return fake responses
 * without opening a real router connection.
 *
 * Usage in Pest / PHPUnit tests:
 *
 *   $fake = MikrotikFake::fake([
 *       '/ppp/secret/print' => [
 *           ['name' => 'ali-home', 'profile' => '10mbps', 'disabled' => 'false'],
 *       ],
 *   ]);
 *
 *   // Call your application code
 *   app(BillingSuspender::class)->handle();
 *
 *   $fake->assertQueried('/ppp/secret/print');
 *   $fake->assertQueried('/ppp/secret/set');
 *
 * @package ZillEAli\MikrotikLaravel\Testing
 * @author  Zill E Ali <zilleali1245@gmail.com>
 */
final class MikrotikFake extends MikrotikManager
{
    /** @var array<string, list<array<string, string>>> */
    protected array $fakeResponses = [];

    /** @var array<string, list<array<string, string>>> */
    protected array $fakeStreams = [];

    protected ?FakeRouterosClient $fakeClient = null;

    /**
     * Create a fake and swap the Laravel container binding.
     *
     * @param  array<string, list<array<string, string>>> $responses  Pre-loaded responses keyed by RouterOS command
     * @return static
     */
    public static function fake(array $responses = []): MikrotikFake
    {
        $fake = new static(config('mikrotik', ['host' => '127.0.0.1', 'port' => 8728]));
        $fake->fakeResponses = $responses;

        app()->instance(MikrotikManager::class, $fake);
        app()->instance('mikrotik', $fake);

        return $fake;
    }

    /**
     * Pre-load a response for a RouterOS command.
     *
     * @param  string                          $command RouterOS command path, e.g. '/ppp/secret/print'
     * @param  list<array<string, string>>     $rows    Rows to return when this command is queried
     * @return static
     */
    public function forCommand(string $command, array $rows): static
    {
        $this->fakeResponses[$command] = $rows;

        $this->fakeClient?->setResponse($command, $rows);

        return $this;
    }

    /**
     * Pre-load rows for a streamed RouterOS command.
     *
     * @param  string                          $command RouterOS command path
     * @param  list<array<string, string>>     $rows    Rows to yield when this command is streamed
     * @return static
     */
    public function forStream(string $command, array $rows): static
    {
        $this->fakeStreams[$command] = $rows;

        $this->fakeClient?->setStream($command, $rows);

        return $this;
    }

    /**
     * Assert that a specific RouterOS command was sent during the test.
     *
     * @param  string $command RouterOS command path, e.g. '/ppp/secret/print'
     */
    public function assertQueried(string $command): void
    {
        Assert::assertContains(
            $command,
            $this->recordedQueries(),
            "Expected RouterOS command [{$command}] was not sent."
        );
    }

    /**
     * Assert that a specific RouterOS command was NOT sent during the test.
     *
     * @param  string $command RouterOS command path
     */
    public function assertNotQueried(string $command): void
    {
        Assert::assertNotContains(
            $command,
            $this->recordedQueries(),
            "Unexpected RouterOS command [{$command}] was sent."
        );
    }

    /**
     * Assert the total number of RouterOS commands sent.
     *
     * @param  int $count Expected number of commands
     */
    public function assertQueryCount(int $count): void
    {
        Assert::assertCount(
            $count,
            $this->recordedQueries(),
            "Expected {$count} RouterOS commands, got " . count($this->recordedQueries()) . "."
        );
    }

    /**
     * Return all RouterOS commands sent so far.
     *
     * @return list<string>
     */
    public function recordedQueries(): array
    {
        return $this->fakeClient?->recordedQueries() ?? [];
    }

    /**
     * Return the underlying fake client instance.
     */
    public function getFakeClient(): ?FakeRouterosClient
    {
        return $this->fakeClient;
    }

    // =========================================================
    // Internal
    // =========================================================

    protected function getClient(): RouterosClient
    {
        $this->resolveAndResetRouter();

        if ($this->fakeClient === null) {
            $this->fakeClient = new FakeRouterosClient(
                $this->fakeResponses,
                $this->fakeStreams,
            );
        }

        return $this->fakeClient;
    }
}
