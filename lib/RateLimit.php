<?php
/**
 * Rate Limiter - Sliding window rate limiting
 */

class RateLimit
{
    private string $dataDir;
    private array $limits = [
        'per_second' => 2,
        'per_minute' => 30,
        'per_hour' => 200,
    ];

    public function __construct()
    {
        $this->dataDir = dirname(__DIR__) . '/data/ratelimit';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * Check rate limit and return risk score addition
     */
    public function check(string $ip): int
    {
        $file = $this->dataDir . '/' . md5($ip) . '.json';
        $now = time();
        $nowMs = microtime(true);

        // Load existing data
        $data = ['requests' => []];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? ['requests' => []];
        }

        // Add current request
        $data['requests'][] = $nowMs;

        // Clean old requests (keep last hour only)
        $data['requests'] = array_values(array_filter(
            $data['requests'],
            fn($t) => $nowMs - $t < 3600
        ));

        // Save
        file_put_contents($file, json_encode($data), LOCK_EX);

        // Calculate rates
        $lastSecond = array_filter($data['requests'], fn($t) => $nowMs - $t < 1);
        $lastMinute = array_filter($data['requests'], fn($t) => $nowMs - $t < 60);
        $lastHour = $data['requests'];

        $score = 0;

        // Per-second limit
        if (count($lastSecond) > $this->limits['per_second']) {
            $score += 30;
        }

        // Per-minute limit
        if (count($lastMinute) > $this->limits['per_minute']) {
            $score += 25;
        } elseif (count($lastMinute) > $this->limits['per_minute'] * 0.7) {
            $score += 10;
        }

        // Per-hour limit
        if (count($lastHour) > $this->limits['per_hour']) {
            $score += 20;
        } elseif (count($lastHour) > $this->limits['per_hour'] * 0.7) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Check if IP is blocked (exceeded hard limits)
     */
    public function isBlocked(string $ip): bool
    {
        $file = $this->dataDir . '/' . md5($ip) . '.json';

        if (!file_exists($file)) {
            return false;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            return false;
        }

        // Check for blocks
        if (isset($data['blocked_until']) && $data['blocked_until'] > time()) {
            return true;
        }

        // Auto-block if exceeded limits severely
        $nowMs = microtime(true);
        $lastMinute = array_filter(
            $data['requests'] ?? [],
            fn($t) => $nowMs - $t < 60
        );

        if (count($lastMinute) > $this->limits['per_minute'] * 3) {
            // Block for 5 minutes
            $data['blocked_until'] = time() + 300;
            file_put_contents($file, json_encode($data), LOCK_EX);
            return true;
        }

        return false;
    }

    /**
     * Clean old rate limit files
     */
    public function cleanup(): void
    {
        $files = glob($this->dataDir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            $mtime = filemtime($file);
            // Delete files older than 2 hours
            if ($now - $mtime > 7200) {
                @unlink($file);
            }
        }
    }
}
