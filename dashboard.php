<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

// Null-safe number formatting helper function
function formatNumber($number, $decimals = 2, $default = '0.00')
{
    return $number !== null ? number_format($number, $decimals, ',', '.') : $default;
}

$db = new Database();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Automatisch fällige wiederkehrende Transaktionen verarbeiten beim Login
$processed_count = $db->processDueRecurringTransactions($user_id);

if ($processed_count > 0) {
    $_SESSION['success'] = "$processed_count wiederkehrende Transaktion(en) automatisch erstellt!";
}

// Dashboard-Statistiken laden
$current_month = date('Y-m');

// UPDATED: Verwende die neue getTotalWealth() Methode für alle Berechnungen
$wealth_data = $db->getTotalWealth($user_id);

// Monatliche Statistiken (aktueller Monat)
$total_income = $db->getTotalIncome($current_month);
$total_expenses = $db->getTotalExpenses($current_month);
$total_debt_in_month = $db->getTotalDebtIncoming($current_month);
$total_debt_out_month = $db->getTotalDebtOutgoing($current_month);

// Saldo berechnen (nur aktueller Monat)
$balance = $total_income - $total_expenses;
$debt_balance_month = $total_debt_in_month - $total_debt_out_month;

// UPDATED: Verwende die berechneten Werte aus getTotalWealth()
$starting_balance = $wealth_data['starting_balance'];
$total_income_all_time = $wealth_data['total_income'];
$total_expenses_all_time = $wealth_data['total_expenses'];
$total_debt_in = $wealth_data['total_debt_in'];
$total_debt_out = $wealth_data['total_debt_out'];
$net_debt_position = $wealth_data['net_debt_position'];
$total_investment_value = $wealth_data['total_investments'];
$total_wealth_with_investments = $wealth_data['total_wealth'];

// Lade Top Investments für die Anzeige
$all_investments = $db->getInvestmentsWithCurrentValue($user_id);
$top_investments = array_slice($all_investments, 0, 3); // Top 3 nehmen

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

// Recurring Transaction Statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_recurring,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_recurring,
        COUNT(CASE WHEN is_active = 1 AND next_due_date <= ? THEN 1 END) as due_soon,
        COUNT(CASE WHEN is_active = 1 AND next_due_date < ? THEN 1 END) as overdue
    FROM recurring_transactions
");

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+7 days'));

$stmt->execute([$soon, $today]);
$recurring_stats = $stmt->fetch() ?: [
    'total_recurring' => 0,
    'active_recurring' => 0,
    'due_soon' => 0,
    'overdue' => 0
];

// FIXED: Letzte Transaktionen laden (gemeinsam für alle User)
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_transactions = $stmt->fetchAll();

// Fällige wiederkehrende Transaktionen für Info-Box
$due_recurring = $db->getDueRecurringTransactions($user_id, 3);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StreamNet Finance</title>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-text h1 {
            color: var(--clr-primary-a20);
            margin-bottom: 5px;
        }

        .welcome-text p {
            color: var(--clr-surface-a50);
        }

        .quick-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Neues Layout: Gesamtvermögen alleine oben */
        .wealth-card-container {
            margin-bottom: 30px;
        }

        .wealth-card {
            background: linear-gradient(135deg, var(--clr-surface-a10) 0%, var(--clr-surface-tonal-a10) 100%);
            border: 1px solid var(--clr-primary-a0);
            border-radius: 12px;
            padding: 32px;
            position: relative;
            overflow: hidden;
        }

        .wealth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--clr-primary-a0), var(--clr-primary-a20));
        }

        .wealth-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .wealth-card h2 {
            color: var(--clr-primary-a20);
            margin: 0;
            font-size: 24px;
        }

        .wealth-value {
            font-size: 48px;
            font-weight: bold;
            color: var(--clr-primary-a0);
            margin-bottom: 15px;
            text-align: center;
        }

        .wealth-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .breakdown-item {
            text-align: center;
            padding: 15px;
            background: var(--clr-surface-a05);
            border-radius: 8px;
            border: 1px solid var(--clr-surface-a10);
        }

        .breakdown-value {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .breakdown-label {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .positive {
            color: #22c55e;
        }

        .negative {
            color: #ef4444;
        }

        .neutral {
            color: var(--clr-surface-a70);
        }

        /* NEUE CSS-Klassen für Schulden */
        .debt-positive {
            color: #22c55e;
        }

        .debt-negative {
            color: #f97316;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--clr-surface-a05);
            border: 1px solid var(--clr-surface-a10);
            border-radius: 8px;
            padding: 20px;
        }

        .stat-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            font-size: 24px;
            margin-right: 10px;
        }

        .stat-title {
            font-weight: bold;
            color: var(--clr-surface-a70);
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-subtitle {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--clr-surface-a05);
            border: 1px solid var(--clr-surface-a10);
            border-radius: 8px;
            padding: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--clr-surface-a10);
        }

        .card h3 {
            margin: 0;
            font-size: 18px;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--clr-surface-a05);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-title {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .transaction-meta {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .transaction-amount {
            font-weight: bold;
            font-size: 16px;
        }

        .investment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--clr-surface-a05);
        }

        .investment-item:last-child {
            border-bottom: none;
        }

        .investment-info {
            flex: 1;
        }

        .investment-symbol {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .investment-name {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .investment-value {
            text-align: right;
        }

        .investment-current {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .investment-change {
            font-size: 14px;
        }

        .price-unavailable {
            color: var(--clr-surface-a50);
            font-style: italic;
        }

        .info-box {
            background: var(--clr-surface-a05);
            border: 1px solid var(--clr-surface-a10);
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }

        .info-title {
            font-weight: bold;
            color: var(--clr-primary-a20);
            margin-bottom: 10px;
        }

        .info-text {
            color: var(--clr-surface-a70);
            line-height: 1.5;
        }

        .empty-state {
            text-align: center;
            color: var(--clr-surface-a50);
            padding: 40px 20px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        .income {
            color: #22c55e;
        }

        .expense {
            color: #ef4444;
        }

        .debt_in {
            color: #22c55e;
        }

        .debt_out {
            color: #f97316;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .wealth-breakdown {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                flex-direction: column;
                width: 100%;
            }

            .wealth-value {
                font-size: 36px;
            }
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <img src="assets/images/logo.png" alt="StreamNet Finance Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <li><a href="modules/expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="modules/income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="modules/debts/index.php"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                    <li><a href="modules/recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="modules/investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="modules/categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li>
                        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="welcome-text">
                    <h1><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</h1>
                    <p>Gemeinsamer Überblick über die Finanzen - <?= date('F Y') ?></p>
                </div>
                <div class="quick-actions">
                    <a href="modules/income/add.php" class="btn btn-primary">+ Einnahme</a>
                    <a href="modules/expenses/add.php" class="btn btn-secondary">+ Ausgabe</a>
                    <a href="modules/debts/add.php?type=debt_in" class="btn" style="background: #22c55e; color: white;">+ Geld erhalten</a>
                    <a href="modules/debts/add.php?type=debt_out" class="btn" style="background: #f97316; color: white;">+ Geld verleihen</a>
                    <a href="modules/investments/add.php" class="btn" style="background: #f59e0b; color: white;">+ Investment</a>
                </div>
            </div>

            <?= $message ?>

            <!-- Gesamtvermögen (Hauptkarte) -->
            <div class="wealth-card-container">
                <div class="wealth-card">
                    <div class="wealth-card-header">
                        <h2><i class="fa-solid fa-globe"></i> Gesamtvermögen</h2>
                        <div style="color: var(--clr-surface-a50); font-size: 14px;">
                            Stand: <?= date('d.m.Y H:i') ?>
                        </div>
                    </div>

                    <div class="wealth-value">
                        €<?= formatNumber($total_wealth_with_investments) ?>
                    </div>

                    <div class="wealth-breakdown">
                        <div class="breakdown-item">
                            <div class="breakdown-value">€<?= formatNumber($starting_balance) ?></div>
                            <div class="breakdown-label">Startkapital</div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-value positive">+€<?= formatNumber($total_income_all_time) ?></div>
                            <div class="breakdown-label">Gesamt Einnahmen</div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-value negative">-€<?= formatNumber($total_expenses_all_time) ?></div>
                            <div class="breakdown-label">Gesamt Ausgaben</div>
                        </div>

                        <!-- NEUE Schulden-Anzeige -->
                        <?php if ($total_debt_in > 0 || $total_debt_out > 0): ?>
                            <div class="breakdown-item">
                                <div class="breakdown-value debt-positive">+€<?= formatNumber($total_debt_in) ?></div>
                                <div class="breakdown-label">Erhaltenes Geld</div>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-value debt-negative">-€<?= formatNumber($total_debt_out) ?></div>
                                <div class="breakdown-label">Verliehenes Geld</div>
                            </div>
                        <?php endif; ?>

                        <?php if ($total_investment_value > 0): ?>
                            <div class="breakdown-item">
                                <div class="breakdown-value positive">+€<?= formatNumber($total_investment_value) ?></div>
                                <div class="breakdown-label">Investments</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Monatsstatistiken -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div class="stat-title">Einnahmen diesen Monat</div>
                    </div>
                    <div class="stat-value income">+€<?= formatNumber($total_income) ?></div>
                    <div class="stat-subtitle">Einnahmen</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <div class="stat-title">Ausgaben diesen Monat</div>
                    </div>
                    <div class="stat-value expense">-€<?= formatNumber($total_expenses) ?></div>
                    <div class="stat-subtitle">Ausgaben</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
                        <div class="stat-title">Monatssaldo</div>
                    </div>
                    <div class="stat-value <?= $balance >= 0 ? 'positive' : 'negative' ?>">
                        <?= $balance >= 0 ? '+' : '' ?>€<?= formatNumber($balance) ?>
                    </div>
                    <div class="stat-subtitle">Einnahmen - Ausgaben</div>
                </div>

                <!-- NEUE Schulden-Statistik für diesen Monat -->
                <?php if ($total_debt_in_month > 0 || $total_debt_out_month > 0): ?>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">🤝</div>
                            <div class="stat-title">Schulden-Saldo</div>
                        </div>
                        <div class="stat-value <?= $debt_balance_month >= 0 ? 'debt-positive' : 'debt-negative' ?>">
                            <?= $debt_balance_month >= 0 ? '+' : '' ?>€<?= formatNumber($debt_balance_month) ?>
                        </div>
                        <div class="stat-subtitle">Dieser Monat</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Content Grid -->
            <div class="dashboard-grid">
                <!-- Letzte Transaktionen -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="color: var(--clr-primary-a20);"><i class="fa-solid fa-clock-rotate-left"></i> Letzte Transaktionen</h3>
                        <a href="modules/expenses/index.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                            Alle anzeigen →
                        </a>
                    </div>

                    <?php if (empty($recent_transactions)): ?>
                        <div class="empty-state">
                            <h3>Keine Transaktionen</h3>
                            <p>Erstelle deine erste Transaktion!</p>
                            <a href="modules/expenses/add.php" class="btn btn-small">Transaktion hinzufügen</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-title">
                                        <?= htmlspecialchars($transaction['note'] ?: $transaction['category_name']) ?>
                                    </div>
                                    <div class="transaction-meta">
                                        <?= htmlspecialchars($transaction['category_name']) ?> •
                                        <?= date('d.m.Y', strtotime($transaction['date'])) ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?= $transaction['transaction_type'] ?>">
                                    <?php
                                    $prefix = '';
                                    switch ($transaction['transaction_type']) {
                                        case 'income':
                                        case 'debt_in':
                                            $prefix = '+';
                                            break;
                                        case 'expense':
                                        case 'debt_out':
                                            $prefix = '-';
                                            break;
                                    }
                                    ?>
                                    <?= $prefix ?>€<?= number_format($transaction['amount'], 2, ',', '.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Top Investments -->
                <?php if (!empty($top_investments)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 style="color: var(--clr-primary-a20);"><i class="fa-brands fa-btc"></i> Top Investments</h3>
                            <a href="modules/investments/index.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                                Alle anzeigen →
                            </a>
                        </div>

                        <?php foreach ($top_investments as $investment): ?>
                            <div class="investment-item">
                                <div class="investment-info">
                                    <div class="investment-symbol"><?= htmlspecialchars($investment['symbol']) ?></div>
                                    <div class="investment-name"><?= htmlspecialchars($investment['name']) ?></div>
                                </div>
                                <div class="investment-value">
                                    <div class="investment-current">
                                        <?php if (($investment['current_value'] ?? null) !== null): ?>
                                            €<?= formatNumber($investment['current_value']) ?>
                                        <?php else: ?>
                                            <span class="price-unavailable">Nicht verfügbar</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="investment-change <?= (($investment['profit_loss_percent'] ?? 0) >= 0) ? 'positive' : 'negative' ?>">
                                        <?php if (($investment['profit_loss_percent'] ?? null) !== null): ?>
                                            <?= ($investment['profit_loss_percent'] ?? 0) >= 0 ? '+' : '' ?><?= formatNumber($investment['profit_loss_percent']) ?>%
                                        <?php else: ?>
                                            <span class="price-unavailable">-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Info-Box wenn keine Investments -->
                    <div class="card">
                        <div class="card-header">
                            <h3 style="color: var(--clr-primary-a20);"><i class="fa-brands fa-btc"></i> Investments</h3>
                        </div>
                        <div class="empty-state">
                            <h3>Keine Investments</h3>
                            <p>Erstelle dein erstes Crypto-Investment!</p>
                            <a href="modules/investments/add.php" class="btn btn-small">Investment hinzufügen</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- NEUE Schulden-Info-Box (nur anzeigen wenn Schulden existieren) -->
            <?php if ($total_debt_in > 0 || $total_debt_out > 0): ?>
                <div class="info-box">
                    <div class="info-title">🤝 Schulden-Übersicht</div>
                    <div class="info-text">
                        <strong>Deine Schulden-Position:</strong><br>
                        Erhaltenes Geld: <span class="debt-positive">+€<?= formatNumber($total_debt_in) ?></span><br>
                        Verliehenes Geld: <span class="debt-negative">-€<?= formatNumber($total_debt_out) ?></span><br>
                        <strong>Netto-Position: <span class="<?= $net_debt_position >= 0 ? 'debt-positive' : 'debt-negative' ?>">
                                <?= $net_debt_position >= 0 ? '+' : '' ?>€<?= formatNumber($net_debt_position) ?>
                            </span></strong><br><br>

                        <?php if ($net_debt_position > 0): ?>
                            💡 Du hast mehr Geld erhalten als verliehen - das verbessert dein Gesamtvermögen!
                        <?php elseif ($net_debt_position < 0): ?>
                            💡 Du hast mehr Geld verliehen als erhalten - das reduziert dein verfügbares Vermögen.
                        <?php else: ?>
                            💡 Deine Schulden-Position ist ausgeglichen.
                        <?php endif; ?>

                        <br><br>
                        <a href="modules/debts/index.php" style="color: var(--clr-primary-a20);">→ Alle Schulden verwalten</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Allgemeine Info-Box -->
            <div class="info-box">
                <div class="info-title">📈 Vermögensberechnung</div>
                <div class="info-text">
                    <strong>Gesamtvermögen = Startkapital + Einnahmen - Ausgaben + Erhaltenes Geld - Verliehenes Geld + Investments</strong><br>
                    €<?= formatNumber($starting_balance) ?> + €<?= formatNumber($total_income_all_time) ?> - €<?= formatNumber($total_expenses_all_time) ?>
                    <?php if ($total_debt_in > 0): ?>+ €<?= formatNumber($total_debt_in) ?><?php endif; ?>
                    <?php if ($total_debt_out > 0): ?> - €<?= formatNumber($total_debt_out) ?><?php endif; ?>
                        <?php if ($total_investment_value > 0): ?> + €<?= formatNumber($total_investment_value) ?><?php endif; ?>
                            = <strong>€<?= formatNumber($total_wealth_with_investments) ?></strong>

                            <?php if (!empty($due_recurring)): ?>
                                <br><br>
                                <strong>🔔 Fällige wiederkehrende Transaktionen:</strong><br>
                                <?php foreach ($due_recurring as $due): ?>
                                    • <?= htmlspecialchars($due['category_name']) ?>: €<?= number_format($due['amount'], 2, ',', '.') ?> (<?= date('d.m.Y', strtotime($due['next_due_date'])) ?>)<br>
                                <?php endforeach; ?>
                                <a href="modules/recurring/index.php" style="color: var(--clr-primary-a20);">→ Wiederkehrende Transaktionen verwalten</a>
                            <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html>