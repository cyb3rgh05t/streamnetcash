<?php
// config/crypto_api.php

class CryptoAPI
{
    private $base_url = 'https://api.coingecko.com/api/v3';
    private $cache_file = 'cache/crypto_prices.json';
    private $cache_duration = 300; // 5 Minuten Cache

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
     * @param array $symbols Array of symbols like ['bitcoin', 'ripple', 'ethereum']
     * @return array
     */
    public function getCurrentPrices($symbols)
    {
        $cache_key = 'prices_' . md5(implode(',', $symbols));

        // Prüfe Cache
        $cached_data = $this->getFromCache($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        try {
            $symbols_string = implode(',', $symbols);
            $url = "{$this->base_url}/simple/price?ids={$symbols_string}&vs_currencies=eur&include_24hr_change=true";

            $response = $this->makeRequest($url);

            if ($response) {
                $this->saveToCache($cache_key, $response);
                return $response;
            }
        } catch (Exception $e) {
            error_log("Crypto API Error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Get single cryptocurrency current price
     * @param string $symbol Like 'bitcoin', 'ripple'
     * @return array|false
     */
    public function getCurrentPrice($symbol)
    {
        $prices = $this->getCurrentPrices([$symbol]);
        return isset($prices[$symbol]) ? $prices[$symbol] : false;
    }

    /**
     * Search for cryptocurrency by name or symbol
     * @param string $query
     * @return array
     */
    public function searchCrypto($query)
    {
        $cache_key = 'search_' . md5($query);

        $cached_data = $this->getFromCache($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        try {
            $url = "{$this->base_url}/search?query=" . urlencode($query);
            $response = $this->makeRequest($url);

            if ($response && isset($response['coins'])) {
                $this->saveToCache($cache_key, $response['coins'], 3600); // 1 Stunde Cache
                return $response['coins'];
            }
        } catch (Exception $e) {
            error_log("Crypto Search Error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Get list of supported cryptocurrencies
     * @return array
     */
    public function getSupportedCryptos()
    {
        $cache_key = 'supported_cryptos';

        $cached_data = $this->getFromCache($cache_key, 86400); // 24 Stunden Cache
        if ($cached_data !== false) {
            return $cached_data;
        }

        try {
            $url = "{$this->base_url}/coins/list";
            $response = $this->makeRequest($url);

            if ($response) {
                $this->saveToCache($cache_key, $response, 86400);
                return $response;
            }
        } catch (Exception $e) {
            error_log("Crypto List Error: " . $e->getMessage());
        }

        return [];
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
            'BNB' => 'binancecoin'
        ];

        return $symbol_map[strtoupper($symbol)] ?? strtolower($symbol);
    }

    /**
     * Make HTTP request to API
     * @param string $url
     * @return array|false
     */
    private function makeRequest($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'StreamNet Finance/1.0'
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return false;
        }

        return json_decode($response, true);
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

        $cache_data = json_decode(file_get_contents($cache_file), true);
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
            $cache_data = json_decode(file_get_contents($cache_file), true) ?: [];
        }

        $cache_data[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];

        // Alte Cache-Einträge löschen (älter als 1 Tag)
        $cache_data = array_filter($cache_data, function ($item) {
            return time() - $item['timestamp'] < 86400;
        });

        file_put_contents($cache_file, json_encode($cache_data));
    }
}
