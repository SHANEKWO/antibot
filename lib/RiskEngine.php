<?php
/**
 * Risk Engine - Server-side risk scoring
 * Analyzes HTTP headers, IP reputation, and behavioral patterns
 */

class RiskEngine
{
    private string $dataDir;
    private array $botSignatures;
    private array $datacenterASNs;

    public function __construct()
    {
        $this->dataDir = dirname(__DIR__) . '/data';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $this->loadSignatures();
    }

    private function loadSignatures(): void
    {
        // Known bot User-Agent patterns with risk scores
        $this->botSignatures = [
            // High risk - Known bots
            ['pattern' => '/googlebot/i', 'score' => 80, 'name' => 'Googlebot'],
            ['pattern' => '/bingbot/i', 'score' => 80, 'name' => 'Bingbot'],
            ['pattern' => '/yandexbot/i', 'score' => 80, 'name' => 'Yandexbot'],
            ['pattern' => '/baiduspider/i', 'score' => 80, 'name' => 'Baiduspider'],
            ['pattern' => '/duckduckbot/i', 'score' => 80, 'name' => 'DuckDuckBot'],
            ['pattern' => '/slurp/i', 'score' => 80, 'name' => 'Yahoo Slurp'],
            ['pattern' => '/facebookexternalhit/i', 'score' => 70, 'name' => 'Facebook'],
            ['pattern' => '/twitterbot/i', 'score' => 70, 'name' => 'Twitter'],
            ['pattern' => '/linkedinbot/i', 'score' => 70, 'name' => 'LinkedIn'],
            ['pattern' => '/whatsapp/i', 'score' => 60, 'name' => 'WhatsApp'],
            ['pattern' => '/telegrambot/i', 'score' => 70, 'name' => 'Telegram'],

            // Critical - Scraping tools
            ['pattern' => '/curl/i', 'score' => 95, 'name' => 'curl'],
            ['pattern' => '/wget/i', 'score' => 95, 'name' => 'wget'],
            ['pattern' => '/python-requests/i', 'score' => 95, 'name' => 'Python Requests'],
            ['pattern' => '/python-urllib/i', 'score' => 95, 'name' => 'Python urllib'],
            ['pattern' => '/python/i', 'score' => 85, 'name' => 'Python'],
            ['pattern' => '/scrapy/i', 'score' => 100, 'name' => 'Scrapy'],
            ['pattern' => '/httpclient/i', 'score' => 90, 'name' => 'HTTPClient'],
            ['pattern' => '/java\//i', 'score' => 85, 'name' => 'Java'],
            ['pattern' => '/libwww/i', 'score' => 90, 'name' => 'libwww'],
            ['pattern' => '/axios/i', 'score' => 85, 'name' => 'Axios'],
            ['pattern' => '/node-fetch/i', 'score' => 85, 'name' => 'node-fetch'],
            ['pattern' => '/go-http-client/i', 'score' => 85, 'name' => 'Go HTTP'],
            ['pattern' => '/ruby/i', 'score' => 80, 'name' => 'Ruby'],
            ['pattern' => '/perl/i', 'score' => 80, 'name' => 'Perl'],
            ['pattern' => '/php\//i', 'score' => 85, 'name' => 'PHP'],

            // Headless browsers
            ['pattern' => '/headlesschrome/i', 'score' => 100, 'name' => 'Headless Chrome'],
            ['pattern' => '/phantomjs/i', 'score' => 100, 'name' => 'PhantomJS'],
            ['pattern' => '/slimerjs/i', 'score' => 100, 'name' => 'SlimerJS'],
            ['pattern' => '/selenium/i', 'score' => 100, 'name' => 'Selenium'],
            ['pattern' => '/puppeteer/i', 'score' => 100, 'name' => 'Puppeteer'],
            ['pattern' => '/playwright/i', 'score' => 100, 'name' => 'Playwright'],

            // SEO tools
            ['pattern' => '/semrush/i', 'score' => 90, 'name' => 'SEMrush'],
            ['pattern' => '/ahrefs/i', 'score' => 90, 'name' => 'Ahrefs'],
            ['pattern' => '/moz/i', 'score' => 85, 'name' => 'Moz'],
            ['pattern' => '/majestic/i', 'score' => 85, 'name' => 'Majestic'],
            ['pattern' => '/dotbot/i', 'score' => 85, 'name' => 'DotBot'],
            ['pattern' => '/mj12bot/i', 'score' => 85, 'name' => 'MJ12Bot'],
            ['pattern' => '/petalbot/i', 'score' => 85, 'name' => 'PetalBot'],

            // Generic bot patterns
            ['pattern' => '/bot[^a-z]/i', 'score' => 70, 'name' => 'Generic bot'],
            ['pattern' => '/spider/i', 'score' => 70, 'name' => 'Generic spider'],
            ['pattern' => '/crawler/i', 'score' => 70, 'name' => 'Generic crawler'],
            ['pattern' => '/scraper/i', 'score' => 90, 'name' => 'Generic scraper'],
            ['pattern' => '/scan/i', 'score' => 75, 'name' => 'Generic scanner'],
        ];

        // Datacenter ASN patterns
        $this->datacenterASNs = [
            'amazon', 'aws', 'ec2',
            'google cloud', 'gcp',
            'microsoft', 'azure',
            'digitalocean', 'linode', 'vultr',
            'ovh', 'hetzner', 'contabo',
            'cloudflare', 'fastly', 'akamai',
        ];
    }

    /**
     * Analyze server-side signals and return risk score
     */
    public function analyzeServer(array $data): array
    {
        $score = 0;
        $reasons = [];

        // 1. User-Agent analysis
        $uaScore = $this->analyzeUserAgent($data['user_agent']);
        $score += $uaScore['score'];
        if ($uaScore['match']) {
            $reasons[] = 'ua:' . $uaScore['match'];
        }

        // 2. Header anomalies
        $headerScore = $this->analyzeHeaders($data);
        $score += $headerScore['score'];
        $reasons = array_merge($reasons, $headerScore['reasons']);

        // 3. IP reputation
        $ipScore = $this->analyzeIP($data['ip']);
        $score += $ipScore['score'];
        $reasons = array_merge($reasons, $ipScore['reasons']);

        // 4. TLS/JA3 fingerprint (if available via proxy)
        // This would require integration with nginx/haproxy

        // 5. Request pattern analysis
        $patternScore = $this->analyzeRequestPattern($data['ip']);
        $score += $patternScore['score'];
        $reasons = array_merge($reasons, $patternScore['reasons']);

        return [
            'total' => min(100, $score),
            'reasons' => $reasons,
            'components' => [
                'user_agent' => $uaScore['score'],
                'headers' => $headerScore['score'],
                'ip' => $ipScore['score'],
                'pattern' => $patternScore['score'],
            ]
        ];
    }

    /**
     * Analyze User-Agent for bot signatures
     */
    private function analyzeUserAgent(string $ua): array
    {
        if (empty($ua)) {
            return ['score' => 50, 'match' => 'empty_ua'];
        }

        // Check against signatures
        foreach ($this->botSignatures as $sig) {
            if (preg_match($sig['pattern'], $ua)) {
                return ['score' => $sig['score'], 'match' => $sig['name']];
            }
        }

        // Suspicious patterns
        $score = 0;
        $match = null;

        // Very short UA
        if (strlen($ua) < 30) {
            $score += 20;
            $match = 'short_ua';
        }

        // No version numbers (suspicious)
        if (!preg_match('/\d+\.\d+/', $ua)) {
            $score += 15;
            $match = 'no_version';
        }

        // Inconsistent browser claims
        if (preg_match('/Chrome/', $ua) && preg_match('/Firefox/', $ua)) {
            $score += 30;
            $match = 'inconsistent_browser';
        }

        // Very old browser versions
        if (preg_match('/Chrome\/[1-6]\d\./', $ua)) {
            $score += 10;
            $match = 'old_chrome';
        }

        return ['score' => $score, 'match' => $match];
    }

    /**
     * Analyze HTTP headers for anomalies
     */
    private function analyzeHeaders(array $data): array
    {
        $score = 0;
        $reasons = [];

        // Missing Accept-Language (browsers always send this)
        if (empty($data['accept_language'])) {
            $score += 25;
            $reasons[] = 'no_accept_language';
        }

        // Missing Accept-Encoding
        if (empty($data['accept_encoding'])) {
            $score += 15;
            $reasons[] = 'no_accept_encoding';
        }

        // Accept-Encoding without gzip (all modern browsers support gzip)
        if (!empty($data['accept_encoding']) && stripos($data['accept_encoding'], 'gzip') === false) {
            $score += 15;
            $reasons[] = 'no_gzip';
        }

        // Check for Connection header anomalies
        if (!empty($data['connection']) && stripos($data['connection'], 'close') !== false) {
            $score += 5; // Not suspicious, just noted
        }

        // Chrome UA but no sec-ch-ua headers (modern Chrome always sends these)
        if (preg_match('/Chrome\/(\d+)/', $data['user_agent'], $m)) {
            $chromeVersion = (int) $m[1];
            if ($chromeVersion >= 90) {
                // Modern Chrome should have client hints
                // This would require checking additional headers
            }
        }

        // Header order analysis would go here (requires raw headers)

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * Analyze IP reputation
     */
    private function analyzeIP(string $ip): array
    {
        $score = 0;
        $reasons = [];

        // Skip for private IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['score' => 0, 'reasons' => ['private_ip']];
        }

        // Check local cache first
        $cached = $this->getCachedIPInfo($ip);
        if ($cached) {
            return $cached;
        }

        // Query IP reputation API
        $ipInfo = $this->queryIPReputation($ip);
        if ($ipInfo) {
            // Datacenter detection
            if ($ipInfo['is_datacenter'] ?? false) {
                $score += 40;
                $reasons[] = 'datacenter';
            }

            // VPN/Proxy detection
            if ($ipInfo['is_vpn'] ?? false) {
                $score += 25;
                $reasons[] = 'vpn';
            }

            if ($ipInfo['is_proxy'] ?? false) {
                $score += 30;
                $reasons[] = 'proxy';
            }

            if ($ipInfo['is_tor'] ?? false) {
                $score += 45;
                $reasons[] = 'tor';
            }

            // Known bad IP
            if ($ipInfo['threat_score'] ?? 0 > 50) {
                $score += 30;
                $reasons[] = 'bad_reputation';
            }

            // Cache the result
            $this->cacheIPInfo($ip, ['score' => $score, 'reasons' => $reasons]);
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * Query IP reputation service
     */
    private function queryIPReputation(string $ip): ?array
    {
        // Using ip-api.com with hosting flag
        $url = "http://ip-api.com/json/{$ip}?fields=status,hosting,proxy";

        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'user_agent' => 'AntiBot/2.0'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        return [
            'is_datacenter' => $data['hosting'] ?? false,
            'is_proxy' => $data['proxy'] ?? false,
        ];
    }

    /**
     * Analyze request patterns from this IP
     */
    private function analyzeRequestPattern(string $ip): array
    {
        $score = 0;
        $reasons = [];

        $patternFile = $this->dataDir . '/patterns/' . md5($ip) . '.json';

        if (!file_exists($patternFile)) {
            // First visit
            $this->recordPattern($ip, $patternFile);
            return ['score' => 0, 'reasons' => []];
        }

        $pattern = json_decode(file_get_contents($patternFile), true);
        if (!$pattern) {
            return ['score' => 0, 'reasons' => []];
        }

        $now = time();
        $visits = $pattern['visits'] ?? [];

        // Count recent visits
        $recentVisits = array_filter($visits, fn($t) => $now - $t < 60); // Last minute
        $hourlyVisits = array_filter($visits, fn($t) => $now - $t < 3600); // Last hour

        // High frequency
        if (count($recentVisits) > 10) {
            $score += 30;
            $reasons[] = 'high_frequency';
        } elseif (count($recentVisits) > 5) {
            $score += 15;
            $reasons[] = 'moderate_frequency';
        }

        // Very high hourly rate
        if (count($hourlyVisits) > 100) {
            $score += 40;
            $reasons[] = 'very_high_hourly';
        }

        // Update pattern
        $this->recordPattern($ip, $patternFile, $pattern);

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * Record visit pattern
     */
    private function recordPattern(string $ip, string $file, ?array $existing = null): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pattern = $existing ?? ['visits' => []];
        $pattern['visits'][] = time();

        // Keep only last 200 visits
        $pattern['visits'] = array_slice($pattern['visits'], -200);
        $pattern['last_seen'] = time();

        file_put_contents($file, json_encode($pattern), LOCK_EX);
    }

    /**
     * Get cached IP info
     */
    private function getCachedIPInfo(string $ip): ?array
    {
        $file = $this->dataDir . '/ip_cache/' . md5($ip) . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data || ($data['expires'] ?? 0) < time()) {
            @unlink($file);
            return null;
        }

        return $data['info'];
    }

    /**
     * Cache IP info
     */
    private function cacheIPInfo(string $ip, array $info): void
    {
        $dir = $this->dataDir . '/ip_cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . md5($ip) . '.json';
        $data = [
            'info' => $info,
            'expires' => time() + 3600, // 1 hour cache
        ];

        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Log access for analysis
     */
    public function log(string $ip, string $ua, array $score): void
    {
        $logFile = dirname(__DIR__) . '/logs/' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $entry = sprintf(
            "[%s] IP: %s | Score: %d | Reasons: %s | UA: %s\n",
            date('H:i:s'),
            $ip,
            $score['total'],
            implode(',', $score['reasons']),
            substr($ua, 0, 100)
        );

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
