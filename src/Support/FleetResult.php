<?php

namespace ZillEAli\MikrotikLaravel\Support;

/**
 * FleetResult
 *
 * Outcome of running one operation across several routers. Successful
 * results and failures are kept separately, keyed by router name, so one
 * unreachable site never hides the data from the rest of the fleet.
 *
 * Usage:
 *  $result = MikroTik::fleet()->health();
 *  $result->successful();          // ['main' => [...], 'branch' => [...]]
 *  $result->failedRouters();       // ['tower-3']
 *  $result->error('tower-3');      // Throwable
 *
 * @template TValue
 * @implements \IteratorAggregate<string, TValue>
 *
 * @package ZillEAli\MikrotikLaravel\Support
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class FleetResult implements \Countable, \IteratorAggregate
{
    /**
     * @param array<string, TValue>      $results Successful results keyed by router name
     * @param array<string, \Throwable>  $errors  Failures keyed by router name
     */
    public function __construct(
        protected array $results = [],
        protected array $errors = [],
    ) {
    }

    /**
     * Get all successful results keyed by router name.
     *
     * @return array<string, TValue>
     */
    public function successful(): array
    {
        return $this->results;
    }

    /**
     * Get all failures keyed by router name.
     *
     * @return array<string, \Throwable>
     */
    public function failed(): array
    {
        return $this->errors;
    }

    /**
     * Get the result for one router, or $default if it failed or was not run.
     *
     * @param  string $router
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $router, mixed $default = null): mixed
    {
        return array_key_exists($router, $this->results) ? $this->results[$router] : $default;
    }

    /**
     * Get the exception thrown for one router, if any.
     *
     * @param  string $router
     * @return \Throwable|null
     */
    public function error(string $router): ?\Throwable
    {
        return $this->errors[$router] ?? null;
    }

    /**
     * Whether the operation succeeded on the given router.
     *
     * @param  string $router
     * @return bool
     */
    public function succeeded(string $router): bool
    {
        return array_key_exists($router, $this->results);
    }

    /**
     * Whether any router failed.
     *
     * @return bool
     */
    public function hasFailures(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Whether every router succeeded.
     *
     * @return bool
     */
    public function allSucceeded(): bool
    {
        return $this->errors === [];
    }

    /**
     * Names of routers that succeeded.
     *
     * @return list<string>
     */
    public function succeededRouters(): array
    {
        return array_map('strval', array_keys($this->results));
    }

    /**
     * Names of routers that failed.
     *
     * @return list<string>
     */
    public function failedRouters(): array
    {
        return array_map('strval', array_keys($this->errors));
    }

    /**
     * Apply a callback to every successful result, keeping router keys.
     *
     * @template TMapped
     * @param  callable(TValue, string): TMapped $callback
     * @return FleetResult<TMapped>
     */
    public function map(callable $callback): FleetResult
    {
        $mapped = [];

        foreach ($this->results as $router => $value) {
            $mapped[$router] = $callback($value, (string) $router);
        }

        return new FleetResult($mapped, $this->errors);
    }

    /**
     * Plain array for JSON responses, cache or dashboards.
     *
     * @return array{results: array<string, TValue>, errors: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'results' => $this->results,
            'errors' => array_map(fn (\Throwable $e) => $e->getMessage(), $this->errors),
        ];
    }

    /**
     * Number of routers the operation ran on (successes + failures).
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->results) + count($this->errors);
    }

    /**
     * Iterate over successful results keyed by router name.
     *
     * @return \ArrayIterator<string, TValue>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->results);
    }
}
