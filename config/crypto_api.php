<?php
// config/crypto_api.php - Funktionierende Version für echte Live-Preise

class CryptoAPI
{
    private $base_url = 'https://api.coingecko.com/api/v3';
    private $cache_file = 'cache/crypto_prices.json';
    private $cache_duration = 300; // 5 Minuten Cache
    private $last_error = '';

    public function __construct()
    {
        // Cache-Directory erstellen falls nicht vorhanden
        $cache_dir = dirname(__DIR__ . '/' . $this->cache_file);
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
    }

    /**
     * Get current price for multiple cryptocurrencies
     * @param array $symbols Array of symbols like ['BTC', 'ETH', 'XRP']
     * @return array|false Returns price data or false if API unavailable
     */
    public function getCurrentPrices($symbols)
    {
        if (empty($symbols)) {
            return false;
        }

        $cache_key = 'prices_' . md5(implode(',', $symbols));

        // Prüfe Cache zuerst
        $cached_data = $this->getFromCache($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Konvertiere Symbole zu CoinGecko IDs
        $converted_symbols = array_map([$this, 'convertSymbolToId'], $symbols);
        $symbols_string = implode(',', $converted_symbols);

        $url = "{$this->base_url}/simple/price?ids={$symbols_string}&vs_currencies=eur&include_24hr_change=true";

        $response = $this->makeRequest($url);

        if ($response === false) {
            $this->last_error = 'Crypto-Preise konnten nicht abgerufen werden. API nicht erreichbar.';
            return false;
        }

        // Format response for easier access
        $formatted_response = [];
        foreach ($response as $id => $data) {
            if (isset($data['eur'])) {
                $formatted_response[$id] = $data['eur'];
                $formatted_response[$id . '_change'] = $data['eur_24h_change'] ?? 0;
            }
        }

        if (empty($formatted_response)) {
            $this->last_error = 'Keine gültigen Preisdaten erhalten.';
            return false;
        }

        $this->saveToCache($cache_key, $formatted_response);
        return $formatted_response;
    }

    /**
     * Get single cryptocurrency current price
     * @param string $symbol Like 'BTC', 'ETH'
     * @return float|false Current price or false if not available
     */
    public function getCurrentPrice($symbol)
    {
        $prices = $this->getCurrentPrices([$symbol]);
        if ($prices === false) {
            return false;
        }

        $converted_symbol = $this->convertSymbolToId($symbol);
        return $prices[$converted_symbol] ?? false;
    }

    /**
     * Get last error message
     * @return string
     */
    public function getLastError()
    {
        return $this->last_error;
    }

    /**
     * Check if API is currently available
     * @return bool
     */
    public function isApiAvailable()
    {
        $test_response = $this->makeRequest($this->base_url . '/simple/price?ids=bitcoin&vs_currencies=eur');
        return $test_response !== false;
    }

    /**
     * Convert common symbols to CoinGecko IDs
     * @param string $symbol
     * @return string
     */
    public function convertSymbolToId($symbol)
    {
        $symbol_map = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'XRP' => 'ripple',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'DOT' => 'polkadot',
            'LTC' => 'litecoin',
            'LINK' => 'chainlink',
            'BCH' => 'bitcoin-cash',
            'XLM' => 'stellar',
            'USDT' => 'tether',
            'USDC' => 'usd-coin',
            'BNB' => 'binancecoin',
            'SOL' => 'solana',
            'MATIC' => 'matic-network',
            'AVAX' => 'avalanche-2',
            'TRX' => 'tron'
        ];

        return $symbol_map[strtoupper($symbol)] ?? strtolower($symbol);
    }

    /**
     * Make HTTP request to API using cURL
     * @param string $url
     * @return array|false
     */
    private function makeRequest($url)
    {
        $this->last_error = '';

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'StreamNet Finance/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false, // Für lokale Entwicklung
            CURLOPT_SSL_VERIFYHOST => false  // Für lokale Entwicklung
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false || !empty($error)) {
            $this->last_error = "cURL Fehler: " . $error;
            return false;
        }

        if ($http_code !== 200) {
            $this->last_error = "HTTP Fehler: $http_code";
            return false;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = "JSON Fehler: " . json_last_error_msg();
            return false;
        }

        return $data;
    }

    /**
     * Get data from cache
     * @param string $key
     * @param int $max_age
     * @return mixed|false
     */
    private function getFromCache($key, $max_age = null)
    {
        $max_age = $max_age ?? $this->cache_duration;
        $cache_file = dirname(__DIR__) . '/' . $this->cache_file;

        if (!file_exists($cache_file)) {
            return false;
        }

        $cache_data = @json_decode(file_get_contents($cache_file), true);
        if (!$cache_data) {
            return false;
        }

        if (isset($cache_data[$key])) {
            $cached_item = $cache_data[$key];
            if (time() - $cached_item['timestamp'] < $max_age) {
                return $cached_item['data'];
            }
        }

        return false;
    }

    /**
     * Save data to cache
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     */
    private function saveToCache($key, $data, $ttl = null)
    {
        $ttl = $ttl ?? $this->cache_duration;
        $cache_file = dirname(__DIR__) . '/' . $this->cache_file;

        $cache_data = [];
        if (file_exists($cache_file)) {
            $cache_data = @json_decode(file_get_contents($cache_file), true) ?: [];
        }

        $cache_data[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];

        // Alte Cache-Einträge löschen (älter als 1 Tag)
        $cache_data = array_filter($cache_data, function ($item) {
            return time() - $item['timestamp'] < 86400;
        });

        @file_put_contents($cache_file, json_encode($cache_data));
    }
}
