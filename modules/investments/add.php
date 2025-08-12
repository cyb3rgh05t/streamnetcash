<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../config/crypto_api.php';

$db = new Database();
$crypto_api = new CryptoAPI();
$user_id = $_SESSION['user_id'];

// Form-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $amount = $_POST['amount'] ?? '';
    $purchase_price = $_POST['purchase_price'] ?? '';
    $purchase_date = $_POST['purchase_date'] ?? '';

    $errors = [];

    // Validierung
    if (empty($symbol)) {
        $errors[] = 'Kryptowährung-Symbol ist erforderlich.';
    }

    if (empty($name)) {
        $errors[] = 'Name der Kryptowährung ist erforderlich.';
    }

    if (empty($amount)) {
        $errors[] = 'Anzahl ist erforderlich.';
    } elseif (!is_numeric($amount) || floatval($amount) <= 0) {
        $errors[] = 'Anzahl muss eine positive Zahl sein.';
    }

    if (empty($purchase_price)) {
        $errors[] = 'Einkaufspreis ist erforderlich.';
    } elseif (!is_numeric($purchase_price) || floatval($purchase_price) <= 0) {
        $errors[] = 'Einkaufspreis muss eine positive Zahl sein.';
    }

    if (empty($purchase_date)) {
        $errors[] = 'Kaufdatum ist erforderlich.';
    } elseif (!strtotime($purchase_date)) {
        $errors[] = 'Ungültiges Kaufdatum.';
    }

    if (empty($errors)) {
        try {
            $investment_id = $db->addInvestment(
                $user_id,
                $symbol,
                $name,
                floatval($amount),
                floatval($purchase_price),
                $purchase_date
            );

            $_SESSION['success'] = 'Investment "' . htmlspecialchars($symbol) . '" erfolgreich hinzugefügt!';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Standardwerte für Formular
$form_data = [
    'symbol' => $_POST['symbol'] ?? '',
    'name' => $_POST['name'] ?? '',
    'amount' => $_POST['amount'] ?? '',
    'purchase_price' => $_POST['purchase_price'] ?? '',
    'purchase_date' => $_POST['purchase_date'] ?? date('Y-m-d')
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neues Investment - StreamNet Finance</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/investments.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="../../assets/images/logo.png" alt="StreamNet Finance Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php">📊 Dashboard</a></li>
                    <li><a href="../expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="../recurring/index.php">🔄 Wiederkehrend</a></li>
                    <li><a href="index.php" class="active">📈 Investments</a></li>
                    <li><a href="../categories/index.php">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="../../settings.php">⚙️ Einstellungen</a>
                    </li>
                    <li><a href="../../logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">📈 Neues Investment</h1>
                    <p style="color: var(--clr-surface-a50);">Füge ein neues Kryptowährung-Investment hinzu</p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2>📈 Investment hinzufügen</h2>
                        <p>Erfasse die Details deines Krypto-Investments - wird für alle User sichtbar</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="investmentForm">
                        <div class="form-group">
                            <label class="form-label" for="crypto_search">Kryptowährung suchen *</label>
                            <div class="crypto-search">
                                <input type="text" id="crypto_search"
                                    class="form-input"
                                    placeholder="z.B. Bitcoin, XRP, Ethereum..."
                                    oninput="searchCrypto(this.value)"
                                    autocomplete="off">
                                <div id="searchResults" class="search-results"></div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="symbol">Symbol *</label>
                                <input type="text" id="symbol" name="symbol"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['symbol']) ?>"
                                    placeholder="z.B. BTC, XRP, ETH"
                                    style="text-transform: uppercase;"
                                    required readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="name">Name *</label>
                                <input type="text" id="name" name="name"
                                    class="form-input"
                                    value="<?= htmlspecialchars($form_data['name']) ?>"
                                    placeholder="z.B. Bitcoin, Ripple"
                                    required readonly>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="amount">Anzahl *</label>
                                <input type="number" id="amount" name="amount"
                                    class="form-input"
                                    step="0.000001" min="0.000001"
                                    value="<?= htmlspecialchars($form_data['amount']) ?>"
                                    placeholder="0.000000" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="purchase_price">Einkaufspreis (€) *</label>
                                <div class="amount-input-wrapper">
                                    <span class="currency-symbol">€</span>
                                    <input type="number" id="purchase_price" name="purchase_price"
                                        class="form-input amount-input"
                                        step="0.01" min="0.01"
                                        value="<?= htmlspecialchars($form_data['purchase_price']) ?>"
                                        placeholder="0,00" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="purchase_date">Kaufdatum *</label>
                            <input type="date" id="purchase_date" name="purchase_date"
                                class="form-input"
                                value="<?= htmlspecialchars($form_data['purchase_date']) ?>"
                                max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-actions">
                            <a href="index.php" class="btn btn-cancel">Abbrechen</a>
                            <button type="submit" class="btn">💾 Investment speichern</button>
                        </div>
                    </form>

                    <div class="info-box">
                        <div class="info-title">💡 Beliebte Kryptowährungen</div>
                        <div class="info-text">
                            Bitcoin (BTC), Ethereum (ETH), Ripple (XRP), Cardano (ADA), Dogecoin (DOGE),
                            Polkadot (DOT), Chainlink (LINK), Litecoin (LTC), Bitcoin Cash (BCH), Stellar (XLM)
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let searchTimeout;

        function searchCrypto(query) {
            clearTimeout(searchTimeout);

            const resultsDiv = document.getElementById('searchResults');

            if (query.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                // Simuliere API-Suche (in echter Anwendung würde hier AJAX-Call stattfinden)
                const popularCryptos = [{
                        symbol: 'BTC',
                        name: 'Bitcoin',
                        id: 'bitcoin'
                    },
                    {
                        symbol: 'ETH',
                        name: 'Ethereum',
                        id: 'ethereum'
                    },
                    {
                        symbol: 'XRP',
                        name: 'Ripple',
                        id: 'ripple'
                    },
                    {
                        symbol: 'ADA',
                        name: 'Cardano',
                        id: 'cardano'
                    },
                    {
                        symbol: 'DOGE',
                        name: 'Dogecoin',
                        id: 'dogecoin'
                    },
                    {
                        symbol: 'DOT',
                        name: 'Polkadot',
                        id: 'polkadot'
                    },
                    {
                        symbol: 'LINK',
                        name: 'Chainlink',
                        id: 'chainlink'
                    },
                    {
                        symbol: 'LTC',
                        name: 'Litecoin',
                        id: 'litecoin'
                    },
                    {
                        symbol: 'BCH',
                        name: 'Bitcoin Cash',
                        id: 'bitcoin-cash'
                    },
                    {
                        symbol: 'XLM',
                        name: 'Stellar',
                        id: 'stellar'
                    },
                    {
                        symbol: 'USDT',
                        name: 'Tether',
                        id: 'tether'
                    },
                    {
                        symbol: 'USDC',
                        name: 'USD Coin',
                        id: 'usd-coin'
                    },
                    {
                        symbol: 'BNB',
                        name: 'Binance Coin',
                        id: 'binancecoin'
                    }
                ];

                const filteredCryptos = popularCryptos.filter(crypto =>
                    crypto.symbol.toLowerCase().includes(query.toLowerCase()) ||
                    crypto.name.toLowerCase().includes(query.toLowerCase())
                );

                displaySearchResults(filteredCryptos);
            }, 300);
        }

        function displaySearchResults(cryptos) {
            const resultsDiv = document.getElementById('searchResults');

            if (cryptos.length === 0) {
                resultsDiv.style.display = 'none';
                return;
            }

            let html = '';
            cryptos.forEach(crypto => {
                html += `
                    <div class="search-result-item" onclick="selectCrypto('${crypto.symbol}', '${crypto.name}')">
                        <div class="search-result-symbol">${crypto.symbol}</div>
                        <div class="search-result-name">${crypto.name}</div>
                    </div>
                `;
            });

            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
        }

        function selectCrypto(symbol, name) {
            document.getElementById('symbol').value = symbol;
            document.getElementById('name').value = name;
            document.getElementById('crypto_search').value = `${symbol} - ${name}`;
            document.getElementById('searchResults').style.display = 'none';

            // Focus auf Amount-Input
            document.getElementById('amount').focus();
        }

        // Click outside to close search results
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.crypto-search')) {
                document.getElementById('searchResults').style.display = 'none';
            }
        });

        // Escape key to close search results
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('searchResults').style.display = 'none';
            }
        });

        // Form validation
        document.getElementById('investmentForm').addEventListener('submit', function(e) {
            const symbol = document.getElementById('symbol').value;
            const name = document.getElementById('name').value;

            if (!symbol || !name) {
                e.preventDefault();
                alert('Bitte wähle eine Kryptowährung aus der Suche aus.');
                return false;
            }
        });

        // Number input formatting
        document.getElementById('purchase_price').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });

        // Focus auf ersten Input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('crypto_search').focus();
        });
    </script>
</body>

</html>