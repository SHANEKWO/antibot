<?php

class Logger
{
    private static string $logDir = __DIR__ . '/../logs';

    public static function log(string $type, array $data): void
    {
        $ip = $data['ip'] ?? '';

        // Anti-spam: max 10 logs par IP par minute
        static $logCounts = [];
        $key = $ip . ':' . floor(time() / 60);
        $logCounts[$key] = ($logCounts[$key] ?? 0) + 1;

        if ($logCounts[$key] > 10) {
            return; // Skip, trop de logs pour cette IP
        }

        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }

        $entry = [
            'time' => date('Y-m-d H:i:s'),
            'ts' => time(),
            'type' => $type,
            'ip' => $ip,
            'ua' => substr($data['ua'] ?? '', 0, 150),
            'score' => $data['score'] ?? 0,
            'reasons' => $data['reasons'] ?? [],
            'country' => $data['country'] ?? '',
        ];

        $file = self::$logDir . '/' . date('Y-m-d') . '.json';
        file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function getStats(int $days = 7): array
    {
        $stats = [
            'total' => 0,
            'allowed' => 0,
            'blocked' => 0,
            'challenged' => 0,
            'by_day' => [],
            'by_hour' => array_fill(0, 24, ['allowed' => 0, 'blocked' => 0]),
            'top_blocked_ips' => [],
            'top_reasons' => [],
            'recent' => [],
        ];

        $ipCounts = [];
        $reasonCounts = [];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $file = self::$logDir . '/' . $date . '.json';

            $stats['by_day'][$date] = ['allowed' => 0, 'blocked' => 0, 'challenged' => 0];

            if (!file_exists($file)) continue;

            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry) continue;

                $stats['total']++;
                $type = $entry['type'] ?? '';
                $hour = (int) date('G', strtotime($entry['time']));

                if ($type === 'allowed') {
                    $stats['allowed']++;
                    $stats['by_day'][$date]['allowed']++;
                    $stats['by_hour'][$hour]['allowed']++;
                } elseif ($type === 'blocked') {
                    $stats['blocked']++;
                    $stats['by_day'][$date]['blocked']++;
                    $stats['by_hour'][$hour]['blocked']++;

                    $ip = $entry['ip'] ?? '';
                    $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;

                    foreach ($entry['reasons'] ?? [] as $reason) {
                        $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
                    }
                } elseif ($type === 'challenged') {
                    $stats['challenged']++;
                    $stats['by_day'][$date]['challenged']++;
                }

                // Recent (last 50)
                if (count($stats['recent']) < 50) {
                    $stats['recent'][] = $entry;
                }
            }
        }

        // Sort
        arsort($ipCounts);
        arsort($reasonCounts);

        $stats['top_blocked_ips'] = array_slice($ipCounts, 0, 10, true);
        $stats['top_reasons'] = array_slice($reasonCounts, 0, 10, true);
        $stats['by_day'] = array_reverse($stats['by_day'], true);

        return $stats;
    }
}
