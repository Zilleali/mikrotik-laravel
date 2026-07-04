<?php

namespace ZillEAli\MikrotikLaravel\Services;

use Spatie\Ssh\Ssh;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

/**
 * ExportManager
 *
 * Fetches RouterOS configuration via SSH (/export command) and provides
 * section parsing, targeted section export, and config diffing.
 *
 * Extended beyond the upstream library with:
 *  - parseConfig(): breaks raw export text into named section arrays
 *  - exportSection(): exports only a specific subsystem (e.g. /ip/firewall)
 *  - diffConfigs(): line-by-line diff of two config snapshots
 *
 * Requirements:
 *  - SSH service enabled on the router (IP → Services → ssh)
 *  - SSH private key auth configured (MIKROTIK_SSH_KEY env var)
 *  - spatie/ssh package installed
 *
 * Usage:
 *  MikroTik::export()->exportFull()
 *  MikroTik::export()->exportSection('/ip/firewall/filter')
 *  MikroTik::export()->diffConfigs($snapshot1, $snapshot2)
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ExportManager
{
    public function __construct(
        protected string $host,
        protected int    $sshPort = 22,
        protected string $sshUser = 'admin',
        protected string $sshPrivateKey = '~/.ssh/id_rsa',
        protected int    $sshTimeout = 30,
    ) {
    }

    // =========================================================
    // Export
    // =========================================================

    /**
     * Export the full router configuration as a text string.
     *
     * Equivalent to running '/export' in the RouterOS terminal.
     *
     * @param  string|null $flags Optional export flags: 'compact', 'verbose', 'file=name'
     * @return string             Raw RouterOS export text
     *
     * @throws ConnectionException If SSH connection fails
     */
    public function exportFull(?string $flags = null): string
    {
        $cmd = '/export' . ($flags !== null ? ' ' . $flags : '');

        return $this->runSshCommand($cmd);
    }

    /**
     * Export a specific section of the router configuration.
     *
     * Example:
     *  $manager->exportSection('/ip/firewall/filter')
     *  $manager->exportSection('/ppp/secret')
     *  $manager->exportSection('/ip/address', 'compact')
     *
     * @param  string      $section RouterOS path, e.g. '/ip/firewall/filter'
     * @param  string|null $flags   Optional: 'compact', 'verbose'
     * @return string
     *
     * @throws ConnectionException
     */
    public function exportSection(string $section, ?string $flags = null): string
    {
        $section = '/' . ltrim($section, '/');
        $cmd = $section . '/export' . ($flags !== null ? ' ' . $flags : '');

        return $this->runSshCommand($cmd);
    }

    // =========================================================
    // Parsing
    // =========================================================

    /**
     * Parse a raw RouterOS export string into a structured array.
     *
     * Groups lines by section header (lines starting with '/ ').
     * Each section contains an array of command strings.
     *
     * Example:
     *  [
     *    '/ip address' => [
     *      'add address=192.168.88.1/24 interface=ether1',
     *      'add address=10.0.0.1/24 interface=ether2',
     *    ],
     *    '/ip route' => [
     *      'add dst-address=0.0.0.0/0 gateway=192.168.1.1',
     *    ],
     *  ]
     *
     * @param  string $raw Raw export text from exportFull() or exportSection()
     * @return array<string, string[]>
     */
    public function parseConfig(string $raw): array
    {
        $sections = [];
        $currentSection = null;

        foreach (explode("\n", $raw) as $line) {
            $line = rtrim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Section header: starts with '/' and is not an 'add' or 'set' command
            if (str_starts_with($line, '/') && ! str_starts_with($line, '/add') && ! str_starts_with($line, '/set')) {
                $currentSection = $line;
                if (! isset($sections[$currentSection])) {
                    $sections[$currentSection] = [];
                }
                continue;
            }

            // Command line under current section ($line is non-empty after the guard above)
            if ($currentSection !== null) {
                $sections[$currentSection][] = ltrim($line);
            }
        }

        return $sections;
    }

    // =========================================================
    // Diff
    // =========================================================

    /**
     * Compare two router config snapshots and return what changed.
     *
     * Returns an array with three keys:
     *  - 'added':   lines present in $after but not in $before
     *  - 'removed': lines present in $before but not in $after
     *  - 'sections_changed': list of section names that have any change
     *
     * Workflow:
     *  $before = MikroTik::export()->exportFull();
     *  // ... make changes on router ...
     *  $after  = MikroTik::export()->exportFull();
     *  $diff   = MikroTik::export()->diffConfigs($before, $after);
     *
     * @param  string $before Config text snapshot (earlier)
     * @param  string $after  Config text snapshot (later)
     * @return array{added: string[], removed: string[], sections_changed: string[]}
     */
    public function diffConfigs(string $before, string $after): array
    {
        $beforeSections = $this->parseConfig($before);
        $afterSections  = $this->parseConfig($after);

        $added   = [];
        $removed = [];
        $changed = [];

        $allSections = array_unique(array_merge(array_keys($beforeSections), array_keys($afterSections)));

        foreach ($allSections as $section) {
            $beforeLines = $beforeSections[$section] ?? [];
            $afterLines  = $afterSections[$section] ?? [];

            $sectionAdded   = array_diff($afterLines, $beforeLines);
            $sectionRemoved = array_diff($beforeLines, $afterLines);

            if (! empty($sectionAdded) || ! empty($sectionRemoved)) {
                $changed[] = $section;
            }

            foreach ($sectionAdded as $line) {
                $added[] = "[{$section}] + {$line}";
            }
            foreach ($sectionRemoved as $line) {
                $removed[] = "[{$section}] - {$line}";
            }
        }

        return [
            'added'            => $added,
            'removed'          => $removed,
            'sections_changed' => $changed,
        ];
    }

    // =========================================================
    // SSH
    // =========================================================

    /**
     * Run a command on the router via SSH and return its output.
     *
     * @param  string $command RouterOS command string
     * @return string
     *
     * @throws ConnectionException
     */
    protected function runSshCommand(string $command): string
    {
        // RouterOS SSH appends '+etc' to the username for terminal profile
        $sshUser = rtrim($this->sshUser, '+etc') . '+etc';

        try {
            $ssh = Ssh::create($sshUser, $this->host, $this->sshPort)
                ->disableStrictHostKeyChecking()
                ->disablePasswordAuthentication()
                ->usePrivateKey($this->sshPrivateKey)
                ->setTimeout($this->sshTimeout);

            $process = $ssh->execute($command);

            if (! $process->isSuccessful()) {
                throw new ConnectionException(
                    "SSH export command failed on {$this->host}: " . $process->getErrorOutput()
                );
            }

            return $process->getOutput();
        } catch (ConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException(
                "SSH connection to {$this->host}:{$this->sshPort} failed: " . $e->getMessage()
            );
        }
    }
}
