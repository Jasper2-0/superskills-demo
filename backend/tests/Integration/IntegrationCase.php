<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

abstract class IntegrationCase extends TestCase
{
    /** @var resource|null */
    private $process = null;
    private string $dbPath = '';
    private int $port = 0;

    protected function setUp(): void
    {
        $this->port = $this->findFreePort();
        $this->dbPath = sys_get_temp_dir() . '/snippet-it-' . uniqid('', true) . '.sqlite';
        $docRoot = dirname(__DIR__, 2) . '/public';
        $router = $docRoot . '/index.php';

        $cmd = sprintf(
            'SNIPPET_DB=%s php -S 127.0.0.1:%d -t %s %s',
            escapeshellarg($this->dbPath),
            $this->port,
            escapeshellarg($docRoot),
            escapeshellarg($router),
        );
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($this->process)) {
            self::fail('Failed to start php -S');
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $sock = @stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 0.2);
            if ($sock) { fclose($sock); return; }
            usleep(50_000);
        }
        self::fail('php -S did not become ready');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['pid']) {
                @posix_kill($status['pid'], SIGTERM);
            }
            proc_terminate($this->process);
            proc_close($this->process);
        }
        if ($this->dbPath !== '' && file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{status: int, body: array<mixed>|null, raw: string}
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init("http://127.0.0.1:{$this->port}{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = $raw === '' ? null : json_decode($raw, true);
        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null, 'raw' => (string) $raw];
    }

    private function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
