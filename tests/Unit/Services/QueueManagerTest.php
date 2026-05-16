<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\QueueManager;

// ─── Helper ───────────────────────────────────────────────────

function makeQueueClient(array $responses = []): RouterosClient
{
    return new class ($responses) extends RouterosClient {
        public function __construct(private array $responses)
        {
            parent::__construct(host: '127.0.0.1');
        }

        public function query(string $command, array $params = [], array $queries = []): array
        {
            return $this->responses[$command] ?? [];
        }

        public function send(array $words): array
        {
            return [];
        }
        public function isConnected(): bool
        {
            return true;
        }
    };
}

// ─── getSimpleQueues ──────────────────────────────────────────

it('returns all simple queues', function () {
    $client = makeQueueClient([
        '/queue/simple/print' => [
            ['name' => 'ali-home',   'target' => '10.0.0.45/32', 'max-limit' => '10M/10M'],
            ['name' => 'zain-fiber', 'target' => '10.0.0.82/32', 'max-limit' => '20M/20M'],
        ],
    ]);

    $manager = new QueueManager($client);
    $queues = $manager->getSimpleQueues();

    expect($queues)->toHaveCount(2)
        ->and($queues[0]['name'])->toBe('ali-home')
        ->and($queues[1]['max-limit'])->toBe('20M/20M');
});

it('returns empty array when no queues exist', function () {
    $client = makeQueueClient(['/queue/simple/print' => []]);
    $manager = new QueueManager($client);

    expect($manager->getSimpleQueues())->toBeEmpty();
});

// ─── getSimpleQueue ───────────────────────────────────────────

it('returns single queue by name', function () {
    $client = makeQueueClient([
        '/queue/simple/print' => [
            ['name' => 'ali-home', 'target' => '10.0.0.45/32', 'max-limit' => '10M/10M'],
        ],
    ]);

    $manager = new QueueManager($client);
    $queue = $manager->getSimpleQueue('ali-home');

    expect($queue)->not->toBeNull()
        ->and($queue['name'])->toBe('ali-home')
        ->and($queue['target'])->toBe('10.0.0.45/32');
});

it('returns null when queue not found', function () {
    $client = makeQueueClient(['/queue/simple/print' => []]);
    $manager = new QueueManager($client);

    expect($manager->getSimpleQueue('nonexistent'))->toBeNull();
});

// ─── createSimpleQueue ────────────────────────────────────────

it('creates simple queue without throwing', function () {
    $client = makeQueueClient();
    $manager = new QueueManager($client);

    expect(fn () => $manager->createSimpleQueue([
        'name' => 'new-user',
        'target' => '10.0.0.100/32',
        'max-limit' => '10M/10M',
    ]))->not->toThrow(\Exception::class);
});

// ─── updateQueue ──────────────────────────────────────────────

it('updates queue without throwing', function () {
    $client = makeQueueClient();
    $manager = new QueueManager($client);

    expect(fn () => $manager->updateQueue('ali-home', ['max-limit' => '20M/20M']))
        ->not->toThrow(\Exception::class);
});

// ─── deleteQueue ──────────────────────────────────────────────

it('deletes queue without throwing', function () {
    $client = makeQueueClient();
    $manager = new QueueManager($client);

    expect(fn () => $manager->deleteQueue('ali-home'))
        ->not->toThrow(\Exception::class);
});

// ─── setLimit ─────────────────────────────────────────────────

it('sets bandwidth limit without throwing', function () {
    $client = makeQueueClient();
    $manager = new QueueManager($client);

    expect(fn () => $manager->setLimit('ali-home', '10M', '10M'))
        ->not->toThrow(\Exception::class);
});

// ─── bulkSetLimit ─────────────────────────────────────────────

it('bulk sets limit for multiple queues', function () {
    $client = makeQueueClient();
    $manager = new QueueManager($client);

    $users = [
        ['name' => 'ali-home',   'ul' => '10M', 'dl' => '10M'],
        ['name' => 'zain-fiber', 'ul' => '20M', 'dl' => '20M'],
    ];

    expect(fn () => $manager->bulkSetLimit($users))
        ->not->toThrow(\Exception::class);
});

// ─── enableQueue / disableQueue ───────────────────────────────

it('enables queue without throwing', function () {
    $client = makeQueueClient();
    $manager = new QueueManager($client);

    expect(fn () => $manager->enableQueue('ali-home'))
        ->not->toThrow(\Exception::class);
});

it('disables queue without throwing', function () {
    $client = makeQueueClient();
    $manager = new QueueManager($client);

    expect(fn () => $manager->disableQueue('ali-home'))
        ->not->toThrow(\Exception::class);
});

// ─── getTreeQueues ────────────────────────────────────────────

it('returns tree queues', function () {
    $client = makeQueueClient([
        '/queue/tree/print' => [
            ['name' => 'parent-queue', 'parent' => 'global', 'max-limit' => '100M'],
        ],
    ]);

    $manager = new QueueManager($client);
    $queues = $manager->getTreeQueues();

    expect($queues)->toHaveCount(1)
        ->and($queues[0]['name'])->toBe('parent-queue');
});
