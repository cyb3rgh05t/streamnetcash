<?php
// config/crypto_api.php - Erweiterte Version mit Suchfunktion

class CryptoAPI
{
    private $base_url = 'https://api.coingecko.com/api/v3';
    private $cache_file = 'cache/crypto_prices.json';
    private $cache_duration = 300; // 5 Minuten Cache
    private $search_cache_duration = 1800; // 30 Minuten Cache für Suchergebnisse
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
     * Search for cryptocurrencies by name or symbol
     * @param string $query Search term
     * @param int $limit Maximum number of results (default: 20)
     * @return array|false Array of crypto results or false on error
     */
    public function searchCryptocurrencies($query, $limit = 20)
    {
        if (empty($query) || strlen($query) < 2) {
            return [];
        }

        $query = strtolower(trim($query));
        $cache_key = 'search_' . md5($query . '_' . $limit);

        // Prüfe Cache zuerst
        $cached_data = $this->getFromCache($cache_key, $this->search_cache_duration);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Falls API nicht verfügbar, nutze lokale Fallback-Liste
        if (!$this->isApiAvailable()) {
            return $this->getLocalSearchResults($query, $limit);
        }

        // CoinGecko Search API verwenden
        $url = "{$this->base_url}/search?query=" . urlencode($query);
        $response = $this->makeRequest($url);

        if ($response === false) {
            // Fallback auf lokale Daten
            return $this->getLocalSearchResults($query, $limit);
        }

        $results = [];
        $count = 0;

        // Coins verarbeiten
        if (isset($response['coins']) && is_array($response['coins'])) {
            foreach ($response['coins'] as $coin) {
                if ($count >= $limit) break;

                $results[] = [
                    'id' => $coin['id'],
                    'symbol' => strtoupper($coin['symbol']),
                    'name' => $coin['name'],
                    'market_cap_rank' => $coin['market_cap_rank'] ?? 999999
                ];
                $count++;
            }
        }

        // Nach Market Cap Rank sortieren (niedrigere Zahlen = höherer Rang)
        usort($results, function ($a, $b) {
            return $a['market_cap_rank'] <=> $b['market_cap_rank'];
        });

        // Cache speichern
        $this->saveToCache($cache_key, $results, $this->search_cache_duration);

        return $results;
    }

    /**
     * Get local fallback search results when API is unavailable
     * @param string $query
     * @param int $limit
     * @return array
     */
    private function getLocalSearchResults($query, $limit = 20)
    {
        $popularCryptos = [
            ['id' => 'bitcoin', 'symbol' => 'BTC', 'name' => 'Bitcoin', 'market_cap_rank' => 1],
            ['id' => 'ethereum', 'symbol' => 'ETH', 'name' => 'Ethereum', 'market_cap_rank' => 2],
            ['id' => 'tether', 'symbol' => 'USDT', 'name' => 'Tether', 'market_cap_rank' => 3],
            ['id' => 'binancecoin', 'symbol' => 'BNB', 'name' => 'BNB', 'market_cap_rank' => 4],
            ['id' => 'solana', 'symbol' => 'SOL', 'name' => 'Solana', 'market_cap_rank' => 5],
            ['id' => 'ripple', 'symbol' => 'XRP', 'name' => 'XRP', 'market_cap_rank' => 6],
            ['id' => 'usd-coin', 'symbol' => 'USDC', 'name' => 'USDC', 'market_cap_rank' => 7],
            ['id' => 'staked-ether', 'symbol' => 'STETH', 'name' => 'Lido Staked Ether', 'market_cap_rank' => 8],
            ['id' => 'cardano', 'symbol' => 'ADA', 'name' => 'Cardano', 'market_cap_rank' => 9],
            ['id' => 'dogecoin', 'symbol' => 'DOGE', 'name' => 'Dogecoin', 'market_cap_rank' => 10],
            ['id' => 'tron', 'symbol' => 'TRX', 'name' => 'TRON', 'market_cap_rank' => 11],
            ['id' => 'avalanche-2', 'symbol' => 'AVAX', 'name' => 'Avalanche', 'market_cap_rank' => 12],
            ['id' => 'chainlink', 'symbol' => 'LINK', 'name' => 'Chainlink', 'market_cap_rank' => 13],
            ['id' => 'polkadot', 'symbol' => 'DOT', 'name' => 'Polkadot', 'market_cap_rank' => 14],
            ['id' => 'matic-network', 'symbol' => 'MATIC', 'name' => 'Polygon', 'market_cap_rank' => 15],
            ['id' => 'litecoin', 'symbol' => 'LTC', 'name' => 'Litecoin', 'market_cap_rank' => 16],
            ['id' => 'bitcoin-cash', 'symbol' => 'BCH', 'name' => 'Bitcoin Cash', 'market_cap_rank' => 17],
            ['id' => 'stellar', 'symbol' => 'XLM', 'name' => 'Stellar', 'market_cap_rank' => 18],
            ['id' => 'ethereum-classic', 'symbol' => 'ETC', 'name' => 'Ethereum Classic', 'market_cap_rank' => 19],
            ['id' => 'monero', 'symbol' => 'XMR', 'name' => 'Monero', 'market_cap_rank' => 20]
        ];

        $results = array_filter($popularCryptos, function ($crypto) use ($query) {
            return (
                stripos($crypto['symbol'], $query) !== false ||
                stripos($crypto['name'], $query) !== false ||
                stripos($crypto['id'], $query) !== false
            );
        });

        return array_slice(array_values($results), 0, $limit);
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
            'TRX' => 'tron',
            'XMR' => 'monero'  // ← FIX: XMR (Monero) hinzugefügt
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

        $cache_data = json_decode(file_get_contents($cache_file), true);
        if (!$cache_data || !isset($cache_data[$key])) {
            return false;
        }

        $data = $cache_data[$key];
        if (!isset($data['timestamp']) || !isset($data['data'])) {
            return false;
        }

        // Check if cache is still valid
        if (time() - $data['timestamp'] > $max_age) {
            return false;
        }

        return $data['data'];
    }

    /**
     * Save data to cache
     * @param string $key
     * @param mixed $data
     * @param int $max_age
     * @return bool
     */
    private function saveToCache($key, $data, $max_age = null)
    {
        $max_age = $max_age ?? $this->cache_duration;
        $cache_file = dirname(__DIR__) . '/' . $this->cache_file;

        // Read existing cache
        $cache_data = [];
        if (file_exists($cache_file)) {
            $existing_cache = json_decode(file_get_contents($cache_file), true);
            if ($existing_cache) {
                $cache_data = $existing_cache;
            }
        }

        // Add new data
        $cache_data[$key] = [
            'timestamp' => time(),
            'data' => $data
        ];

        // Clean old entries (keep only last 100 entries to prevent cache bloat)
        if (count($cache_data) > 100) {
            // Sort by timestamp and keep only newest 50
            uasort($cache_data, function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });
            $cache_data = array_slice($cache_data, 0, 50, true);
        }

        return file_put_contents($cache_file, json_encode($cache_data)) !== false;
    }
}
