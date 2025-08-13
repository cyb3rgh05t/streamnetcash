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

// Startkapital laden
$starting_balance = $db->getStartingBalance($user_id);

// FIXED: Gesamte Einnahmen diesen Monat - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'income' AND strftime('%Y-%m', t.date) = ?
");
$stmt->execute([$current_month]);
$total_income = $stmt->fetchColumn();

// FIXED: Gesamte Ausgaben diesen Monat - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'expense' AND strftime('%Y-%m', t.date) = ?
");
$stmt->execute([$current_month]);
$total_expenses = $stmt->fetchColumn();

// FIXED: Gesamte Einnahmen (alle Zeit) - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'income'
");
$stmt->execute([]);
$total_income_all_time = $stmt->fetchColumn();

// FIXED: Gesamte Ausgaben (alle Zeit) - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE c.type = 'expense'
");
$stmt->execute([]);
$total_expenses_all_time = $stmt->fetchColumn();

// Investment-Werte laden
$investment_stats = $db->getTotalInvestmentValue($user_id);
$total_investment_value = $investment_stats['total_current_value'] ?? 0;

// Saldo berechnen (nur aktueller Monat)
$balance = $total_income - $total_expenses;

// Gesamtvermögen berechnen (Startkapital + alle Einnahmen - alle Ausgaben + Investments)
$total_wealth = $starting_balance + $total_income_all_time - $total_expenses_all_time;
$total_wealth_with_investments = $total_wealth + ($total_investment_value ?? 0);

// Investments laden für Anzeige
$investments = $db->getInvestmentsWithCurrentValue($user_id);
$top_investments = array_slice($investments, 0, 5); // Top 5 für Dashboard

// FIXED: Wiederkehrende Transaktionen Statistiken - user_id Filter entfernt
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

        .wealth-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--clr-primary-a20);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .wealth-value {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--clr-primary-a10);
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .wealth-subtitle {
            color: var(--clr-surface-a50);
            font-size: 16px;
            margin-bottom: 24px;
        }

        .wealth-breakdown {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 20px;
        }

        .breakdown-title {
            font-weight: 600;
            color: var(--clr-primary-a30);
            margin-bottom: 16px;
            font-size: 16px;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .breakdown-item:last-child {
            border-bottom: none;
        }

        .breakdown-value {
            font-weight: 600;
            color: var(--clr-light-a0);
        }

        /* Andere Karten in einer Reihe darunter */
        .other-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border-color: var(--clr-primary-a0);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--clr-surface-a50);
        }

        .card-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--clr-primary-a20);
            margin-bottom: 8px;
        }

        /* Spezifische Karten-Stile */
        .card-investment {
            border-color: #10b981;
        }

        .card-investment:hover {
            border-color: #059669;
        }

        .card-investment .card-value {
            color: #10b981;
        }

        .card-recurring {
            border-color: #6b7280;
        }

        .card-recurring:hover {
            border-color: #4b5563;
        }

        .card-recurring .card-value {
            color: #9ca3af;
        }

        .card-income {
            border-color: #4ade80;
        }

        .card-income:hover {
            border-color: #22c55e;
        }

        .card-income .card-value {
            color: #4ade80;
        }

        .card-expense {
            border-color: #f87171;
        }

        .card-expense:hover {
            border-color: #ef4444;
        }

        .card-expense .card-value {
            color: #f87171;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-top: 30px;
        }

        .stats-grid .card {
            height: fit-content;
        }

        /* Investment Items */
        .investment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .investment-item:last-child {
            border-bottom: none;
        }

        .investment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .investment-symbol {
            font-weight: 700;
            color: var(--clr-primary-a20);
        }

        .investment-name {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .investment-value {
            text-align: right;
        }

        .investment-current {
            font-weight: 600;
            color: var(--clr-light-a0);
        }

        .investment-change {
            font-size: 13px;
        }

        .investment-change.positive {
            color: #4ade80;
        }

        .investment-change.negative {
            color: #f87171;
        }

        /* Transaction Items */
        .transaction-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-title {
            font-weight: 600;
            color: var(--clr-light-a0);
            font-size: 14px;
        }

        .transaction-meta {
            color: var(--clr-surface-a50);
            font-size: 12px;
            margin-top: 2px;
        }

        .transaction-amount {
            font-weight: 700;
            text-align: right;
        }

        .transaction-amount.income {
            color: #4ade80;
        }

        .transaction-amount.expense {
            color: #f87171;
        }

        /* Alerts und Notices */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #86efac;
        }

        .shared-notice {
            background-color: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .no-data {
            text-align: center;
            color: var(--clr-surface-a50);
            padding: 20px;
        }

        .price-unavailable {
            color: #94a3b8;
            font-style: italic;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .other-cards {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .quick-actions {
                width: 100%;
                justify-content: center;
            }

            .wealth-card {
                padding: 24px;
            }

            .wealth-value {
                font-size: 2.5rem;
            }

            .other-cards {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .investment-item {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .wealth-card {
                padding: 20px;
            }

            .wealth-value {
                font-size: 2rem;
            }

            .card {
                padding: 16px;
            }

            .card-value {
                font-size: 1.8rem;
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
                    <li><a href="modules/debts/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'debts') ? 'active' : '' ?>">
                            <i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden
                        </a></li>
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
            <!-- Success Message -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Info für fällige wiederkehrende Transaktionen -->
            <!-- <?php if (!empty($due_recurring)): ?>
                <div class="shared-notice">
                    <div class="shared-notice-title">🔄 Fällige wiederkehrende Transaktionen</div>
                    <div class="shared-notice-text">
                        <?= count($due_recurring) ?> wiederkehrende Transaktion(en) sind fällig:
                        <?php foreach ($due_recurring as $due): ?>
                            <span style="display: block; margin-top: 5px;">
                                <?= htmlspecialchars($due['category_icon']) ?> <?= htmlspecialchars($due['note']) ?>
                                (<?= $due['transaction_type'] === 'income' ? '+' : '-' ?>€<?= number_format($due['amount'], 2, ',', '.') ?>)
                                - Fällig am <?= date('d.m.Y', strtotime($due['next_due_date'])) ?>
                            </span>
                        <?php endforeach; ?>
                        <div style="margin-top: 10px;">
                            <a href="modules/recurring/index.php" class="btn btn-small">🔄 Wiederkehrende Transaktionen verwalten</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?> -->

            <div class="dashboard-header">
                <div class="welcome-text">
                    <h1><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</h1>
                    <p>Gemeinsamer Überblick über die Finanzen - <?= date('F Y') ?></p>
                </div>
                <div class="quick-actions">
                    <a href="modules/income/add.php" class="btn">+ Einnahme</a>
                    <a href="modules/expenses/add.php" class="btn btn-secondary">+ Ausgabe</a>
                    <a href="modules/investments/add.php" class="btn" style="background-color: #10b981;">+ Investment</a>
                    <a href="modules/recurring/add.php" class="btn" style="background-color: #6b7280;">+ Wiederkehrend</a>
                </div>
            </div>

            <!-- Shortcut zu Einstellungen wenn kein Startkapital gesetzt -->
            <?php if ($starting_balance == 0): ?>
                <div style="background-color: rgba(251, 191, 36, 0.1); border: 1px solid #fbbf24; border-radius: 8px; padding: 15px; margin-bottom: 20px; text-align: center;">
                    <div style="color: #fcd34d; margin-bottom: 10px; font-weight: 600;">💡 Tipp: Startkapital festlegen</div>
                    <div style="color: var(--clr-surface-a50); font-size: 14px; margin-bottom: 15px;">
                        Lege das gemeinsame Startkapital fest, um das echte Gesamtvermögen zu sehen!
                    </div>
                    <a href="settings.php" class="btn btn-small">⚙️ Startkapital festlegen</a>
                </div>
            <?php endif; ?>

            <!-- GESAMTVERMÖGEN KARTE - ALLEINE IN EINER REIHE -->
            <div class="wealth-card-container">
                <div class="wealth-card">
                    <div class="wealth-card-header">
                        <div class="wealth-title">
                            <i class="fa-solid fa-globe"></i>&nbsp;&nbsp;Gesamtvermögen
                        </div>
                    </div>

                    <div class="wealth-value">€<?= formatNumber($total_wealth_with_investments) ?></div>
                    <div class="wealth-subtitle">Startkapital + Einnahmen - Ausgaben + Investments</div>

                    <div class="wealth-breakdown">
                        <div class="breakdown-title"><i class="fa-solid fa-eye"></i> Aufschlüsselung</div>
                        <div class="breakdown-item">
                            <span>Startkapital:</span>
                            <span class="breakdown-value">€<?= number_format($starting_balance, 2, ',', '.') ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span>+ Gesamt Einnahmen:</span>
                            <span class="breakdown-value">€<?= number_format($total_income_all_time, 2, ',', '.') ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span>- Gesamt Ausgaben:</span>
                            <span class="breakdown-value">€<?= number_format($total_expenses_all_time, 2, ',', '.') ?></span>
                        </div>
                        <?php if (($total_investment_value ?? 0) > 0): ?>
                            <div class="breakdown-item">
                                <span>+ Investments:</span>
                                <span class="breakdown-value">€<?= formatNumber($total_investment_value) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ALLE ANDEREN KARTEN - IN EINER REIHE DARUNTER -->
            <div class="other-cards">
                <!-- Investment Card -->
                <div class="card card-investment">
                    <div class="card-header">
                        <h3 class="card-title">Investments</h3>
                        <span><i class="fa-brands fa-btc"></i></span>
                    </div>
                    <?php if ($total_investment_value !== null): ?>
                        <div class="card-value">€<?= formatNumber($total_investment_value) ?></div>
                        <?php if ($investment_stats['investment_count'] > 0): ?>
                            <p style="color: var(--clr-surface-a50); font-size: 14px;">
                                <?= $investment_stats['investment_count'] ?> Positionen
                                <?php if (($investment_stats['total_profit_loss_percent'] ?? null) !== null): ?>
                                    • <?= ($investment_stats['total_profit_loss_percent'] ?? 0) >= 0 ? '+' : '' ?><?= formatNumber($investment_stats['total_profit_loss_percent'], 1) ?>%
                                <?php else: ?>
                                    • <span class="price-unavailable">Gewinn/Verlust nicht verfügbar</span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p style="color: var(--clr-surface-a50); font-size: 14px;">Noch keine Investments</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="card-value price-unavailable">Nicht verfügbar</div>
                        <p style="color: #f87171; font-size: 14px;">❌ API nicht erreichbar</p>
                    <?php endif; ?>
                </div>

                <!-- Wiederkehrende Transaktionen -->
                <div class="card card-recurring">
                    <div class="card-header">
                        <h3 class="card-title">Wiederkehrende Transaktionen</h3>
                        <span><i class="fas fa-sync"></i></span>
                    </div>
                    <div class="card-value"><?= $recurring_stats['active_recurring'] ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">
                        <?= $recurring_stats['active_recurring'] ?> aktiv von <?= $recurring_stats['total_recurring'] ?> gesamt
                        <?php if ($recurring_stats['due_soon'] > 0): ?>
                            <br><span style="color: #fbbf24; font-weight: 600;"><?= $recurring_stats['due_soon'] ?> bald fällig</span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Einnahmen -->
                <div class="card card-income">
                    <div class="card-header">
                        <h3 class="card-title">Einnahmen</h3>
                        <span><i class="fa-solid fa-sack-dollar"></i></span>
                    </div>
                    <div class="card-value">+ €<?= number_format($total_income, 2, ',', '.') ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Diesen Monat</p>
                </div>

                <!-- Ausgaben -->
                <div class="card card-expense">
                    <div class="card-header">
                        <h3 class="card-title">Ausgaben</h3>
                        <span><i class="fa-solid fa-money-bill-wave"></i></span>
                    </div>
                    <div class="card-value">- €<?= number_format($total_expenses, 2, ',', '.') ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Diesen Monat</p>
                </div>

                <!-- Monatssaldo -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monatssaldo</h3>
                        <span><i class="fa-solid fa-money-check-dollar"></i></span>
                    </div>
                    <div class="card-value" style="color: <?= $balance >= 0 ? '#4ade80' : '#f87171' ?>">
                        €<?= number_format($balance, 2, ',', '.') ?>
                    </div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Einnahmen - Ausgaben (<?= date('M Y') ?>)</p>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="stats-grid">
                <div class="card">
                    <div class="card-header">
                        <h3 style="color: var(--clr-primary-a20);"><i class="fa-solid fa-eye"></i> Letzte Transaktionen</h3>
                        <a href="modules/expenses/index.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                            Alle anzeigen →
                        </a>
                    </div>

                    <?php if (empty($recent_transactions)): ?>
                        <div class="no-data">
                            <p>Noch keine Transaktionen vorhanden.</p>
                            <p style="margin-top: 10px;">
                                <a href="modules/income/add.php" class="btn" style="margin-right: 10px;">Erste Einnahme hinzufügen</a>
                                <a href="modules/expenses/add.php" class="btn btn-secondary">Erste Ausgabe hinzufügen</a>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon" style="background-color: <?= htmlspecialchars($transaction['category_color']) ?>20; color: <?= htmlspecialchars($transaction['category_color']) ?>;">
                                    <?= htmlspecialchars($transaction['category_icon']) ?>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-title">
                                        <?= htmlspecialchars($transaction['note'] ?: $transaction['category_name']) ?>
                                    </div>
                                    <div class="transaction-meta">
                                        <?= htmlspecialchars($transaction['category_name']) ?> •
                                        <?= date('d.m.Y', strtotime($transaction['date'])) ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?= $transaction['transaction_type'] ?>">
                                    <?= $transaction['transaction_type'] === 'income' ? '+' : '-' ?>€<?= number_format($transaction['amount'], 2, ',', '.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

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
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>