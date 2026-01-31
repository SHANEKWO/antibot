<?php
/**
 * Fingerprint - Token generation and validation
 */

class Fingerprint
{
    private static string $secretKey = 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY_IN_PRODUCTION';

    /**
     * Generate a challenge token
     */
    public static function generateChallenge(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a signed verification token
     */
    public static function generateToken(string $fingerprint, string $ip): string
    {
        $payload = [
            'fp' => hash('sha256', $fingerprint),
            'ip' => hash('sha256', $ip),
            'iat' => time(),
            'exp' => time() + 86400, // 24 hours
        ];

        $data = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $data, self::$secretKey);

        return $data . '.' . $signature;
    }

    /**
     * Validate a verification token
     */
    public static function validateToken(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        [$data, $signature] = $parts;

        // Verify signature
        $expectedSig = hash_hmac('sha256', $data, self::$secretKey);
        if (!hash_equals($expectedSig, $signature)) {
            return false;
        }

        // Decode and validate payload
        $payload = json_decode(base64_decode($data), true);
        if (!$payload) {
            return false;
        }

        // Check expiration
        if (($payload['exp'] ?? 0) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Validate proof of work
     */
    public static function validatePoW(string $challenge, int $nonce, string $hash, int $difficulty): bool
    {
        // Verify the hash
        $data = $challenge . $nonce;
        $expectedHash = hash('sha256', $data);

        if (!hash_equals($expectedHash, $hash)) {
            return false;
        }

        // Check difficulty
        $prefix = str_repeat('0', $difficulty);
        return strpos($hash, $prefix) === 0;
    }

    /**
     * Generate device fingerprint hash from signals
     */
    public static function hashSignals(array $signals): string
    {
        $components = [
            $signals['screen']['width'] ?? 0,
            $signals['screen']['height'] ?? 0,
            $signals['screen']['depth'] ?? 0,
            $signals['timezone'] ?? '',
            $signals['platform'] ?? '',
            $signals['hardwareConcurrency'] ?? 0,
            $signals['canvas'] ?? '',
            $signals['webgl']['renderer'] ?? '',
            $signals['audio'] ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Analyze client signals for anomalies
     */
    public static function analyzeClientSignals(array $signals): array
    {
        $score = 0;
        $reasons = [];

        // Anomalies detected client-side
        $anomalies = $signals['anomalies'] ?? [];
        foreach ($anomalies as $anomaly) {
            switch ($anomaly) {
                case 'webdriver':
                case 'selenium':
                case 'phantom':
                case 'nightmare':
                case 'automation':
                    $score += 50;
                    break;
                case 'fake_chrome':
                case 'no_plugins':
                    $score += 25;
                    break;
                case 'zero_dimensions':
                case 'tiny_screen':
                    $score += 30;
                    break;
                case 'modified_userAgent':
                case 'modified_languages':
                case 'eval_modified':
                    $score += 35;
                    break;
                default:
                    $score += 10;
            }
            $reasons[] = $anomaly;
        }

        // Behavioral score from client
        $behaviorScore = $signals['behaviorScore']['value'] ?? 0;
        $score += $behaviorScore;
        if ($behaviorScore > 0) {
            $reasons = array_merge($reasons, $signals['behaviorScore']['reasons'] ?? []);
        }

        // Timing analysis
        $totalTime = $signals['totalTime'] ?? 0;
        if ($totalTime < 300) {
            $score += 20;
            $reasons[] = 'too_fast_client';
        }

        // Mouse movement analysis
        $mouseMovements = $signals['mouseMovements'] ?? [];
        if (count($mouseMovements) === 0 && $totalTime > 2000) {
            // No mouse movement in 2+ seconds on desktop might be suspicious
            // But we don't penalize heavily as touch devices won't have this
            $score += 5;
            $reasons[] = 'no_mouse';
        }

        // Check for linear/robotic movements
        if (count($mouseMovements) > 5) {
            $linearCount = 0;
            for ($i = 2; $i < count($mouseMovements); $i++) {
                $p1 = $mouseMovements[$i - 2];
                $p2 = $mouseMovements[$i - 1];
                $p3 = $mouseMovements[$i];

                // Check if 3 points are collinear
                $area = abs(
                    ($p1['x'] * ($p2['y'] - $p3['y']) +
                     $p2['x'] * ($p3['y'] - $p1['y']) +
                     $p3['x'] * ($p1['y'] - $p2['y'])) / 2
                );

                if ($area < 5) {
                    $linearCount++;
                }
            }

            // Too many linear movements = robotic
            if ($linearCount > count($mouseMovements) * 0.8) {
                $score += 20;
                $reasons[] = 'robotic_movement';
            }
        }

        return [
            'score' => min(100, $score),
            'reasons' => $reasons,
        ];
    }
}
