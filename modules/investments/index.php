<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get all investments with current values
$investments = $db->getInvestmentsWithCurrentValue($user_id);
$total_stats = $db->getTotalInvestmentValue($user_id);

// Success/Error Messages
$message = '';
if (isset($_SESSION['success'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $message = '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investments - StreamNet Finance</title>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">📈 Investments</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte deine Kryptowährung-Investments - Gemeinsame Ansicht aller User</p>
                </div>
                <a href="add.php" class="btn">+ Neues Investment</a>
            </div>

            <?= $message ?>

            <!-- Investment Stats -->
            <?php if (!empty($investments)): ?>
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-value portfolio">€<?= number_format($total_stats['total_current_value'], 2, ',', '.') ?></div>
                        <div class="stat-label">Portfolio-Wert</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value invested">€<?= number_format($total_stats['total_purchase_value'], 2, ',', '.') ?></div>
                        <div class="stat-label">Investiert</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value <?= $total_stats['total_profit_loss'] >= 0 ? 'profit' : 'loss' ?>">
                            <?= $total_stats['total_profit_loss'] >= 0 ? '+' : '' ?>€<?= number_format($total_stats['total_profit_loss'], 2, ',', '.') ?>
                        </div>
                        <div class="stat-label">Gewinn/Verlust</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value <?= $total_stats['total_profit_loss_percent'] >= 0 ? 'profit' : 'loss' ?>">
                            <?= $total_stats['total_profit_loss_percent'] >= 0 ? '+' : '' ?><?= number_format($total_stats['total_profit_loss_percent'], 2, ',', '.') ?>%
                        </div>
                        <div class="stat-label">Performance</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Investments Table -->
            <div class="investments-table">
                <?php if (empty($investments)): ?>
                    <div class="empty-state">
                        <h3>📈 Noch keine Investments</h3>
                        <p>Füge dein erstes Krypto-Investment hinzu, um hier eine Übersicht zu sehen.</p>
                        <div style="margin-top: 20px;">
                            <a href="add.php" class="btn">Erstes Investment hinzufügen</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-header">
                        <div>Kryptowährung</div>
                        <div>Menge</div>
                        <div>Einkaufspreis</div>
                        <div>Aktueller Preis</div>
                        <div>Investiert</div>
                        <div>Aktueller Wert</div>
                        <div>Gewinn/Verlust</div>
                        <div>24h Änderung</div>
                        <div>Aktionen</div>
                    </div>

                    <?php foreach ($investments as $investment): ?>
                        <div class="investment-row">
                            <div class="crypto-info">
                                <div class="crypto-symbol"><?= htmlspecialchars(strtoupper($investment['symbol'])) ?></div>
                                <div class="crypto-name"><?= htmlspecialchars($investment['name']) ?></div>
                            </div>

                            <div class="investment-amount">
                                <?= number_format($investment['amount'], 6, ',', '.') ?>
                            </div>

                            <div class="price">
                                €<?= number_format($investment['purchase_price'], 2, ',', '.') ?>
                            </div>

                            <div class="price">
                                €<?= number_format($investment['current_price'], 2, ',', '.') ?>
                            </div>

                            <div class="value invested">
                                €<?= number_format($investment['purchase_value'], 2, ',', '.') ?>
                            </div>

                            <div class="value current">
                                €<?= number_format($investment['current_value'], 2, ',', '.') ?>
                            </div>

                            <div class="profit-loss <?= $investment['profit_loss'] >= 0 ? 'profit' : 'loss' ?>">
                                <div class="amount">
                                    <?= $investment['profit_loss'] >= 0 ? '+' : '' ?>€<?= number_format($investment['profit_loss'], 2, ',', '.') ?>
                                </div>
                                <div class="percentage">
                                    <?= $investment['profit_loss_percent'] >= 0 ? '+' : '' ?><?= number_format($investment['profit_loss_percent'], 2, ',', '.') ?>%
                                </div>
                            </div>

                            <div class="price-change <?= $investment['price_change_24h'] >= 0 ? 'positive' : 'negative' ?>">
                                <?= $investment['price_change_24h'] >= 0 ? '+' : '' ?><?= number_format($investment['price_change_24h'], 2, ',', '.') ?>%
                            </div>

                            <div class="actions">
                                <a href="edit.php?id=<?= $investment['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten">✏️</a>
                                <a href="delete.php?id=<?= $investment['id'] ?>" class="btn btn-icon btn-delete"
                                    onclick="return confirm('Investment wirklich löschen?')" title="Löschen">🗑️</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-title">💡 Investment-Tracking</div>
                <div class="info-text">
                    Die Preise werden alle 5 Minuten von CoinGecko aktualisiert. Alle Investments sind für alle User sichtbar und gemeinsam verwaltbar.
                    Der Gesamtwert wird automatisch in dein Vermögen im Dashboard eingerechnet.
                </div>
            </div>
        </main>
    </div>
</body>

</html>