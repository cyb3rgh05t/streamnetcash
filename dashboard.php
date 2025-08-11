<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

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

// Saldo berechnen (nur aktueller Monat)
$balance = $total_income - $total_expenses;

// Gesamtvermögen berechnen (Startkapital + alle Einnahmen - alle Ausgaben)
$total_wealth = $starting_balance + $total_income_all_time - $total_expenses_all_time;

// FIXED: Wiederkehrende Transaktionen Statistiken - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_recurring,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_recurring,
        COUNT(CASE WHEN is_active = 1 AND next_due_date <= ? THEN 1 END) as due_soon
    FROM recurring_transactions
");
$stmt->execute([date('Y-m-d', strtotime('+7 days'))]);
$recurring_stats = $stmt->fetch();

// FIXED: Fällige wiederkehrende Transaktionen für Warning - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT rt.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM recurring_transactions rt
    JOIN categories c ON rt.category_id = c.id
    WHERE rt.is_active = 1 AND rt.next_due_date <= ?
    ORDER BY rt.next_due_date ASC
    LIMIT 3
");
$stmt->execute([date('Y-m-d', strtotime('+3 days'))]);
$due_recurring = $stmt->fetchAll();

// FIXED: Letzte 5 Transaktionen - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 5
");
$stmt->execute([]);
$recent_transactions = $stmt->fetchAll();

// FIXED: Ausgaben nach Kategorien für Chart - user_id Filter entfernt
$stmt = $pdo->prepare("
    SELECT c.name, c.color, c.icon, COALESCE(SUM(t.amount), 0) as total
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id AND strftime('%Y-%m', t.date) = ?
    WHERE c.type = 'expense'
    GROUP BY c.id, c.name, c.color, c.icon
    HAVING total > 0
    ORDER BY total DESC
");
$stmt->execute([$current_month]);
$expense_categories = $stmt->fetchAll();

// Success message anzeigen
$message = '';
if (isset($_SESSION['success'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StreamNet Finance</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .welcome-text h1 {
            color: var(--clr-primary-a20);
            margin-bottom: 5px;
        }

        .welcome-text p {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .quick-actions {
            display: flex;
            gap: 10px;
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card-wealth {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            border: 2px solid var(--clr-primary-a0);
            position: relative;
            overflow: hidden;
        }

        .card-wealth::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(230, 163, 9, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .card-wealth .card-value {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wealth-subtitle {
            font-size: 12px;
            color: var(--clr-surface-a40);
            margin-top: 5px;
        }

        .card-recurring {
            background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
            border: 2px solid #6b7280;
        }

        .card-recurring .card-value {
            color: #a5b4fc;
        }

        .transaction-item {
            display: flex;
            align-items: center;
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
            margin-right: 15px;
            font-size: 18px;
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-title {
            font-weight: 500;
            color: var(--clr-light-a0);
            margin-bottom: 2px;
        }

        .transaction-category {
            font-size: 12px;
            color: var(--clr-surface-a50);
        }

        .transaction-amount {
            font-weight: 600;
            font-size: 16px;
        }

        .transaction-amount.income {
            color: #4ade80;
        }

        .transaction-amount.expense {
            color: #f87171;
        }

        .transaction-date {
            font-size: 12px;
            color: var(--clr-surface-a50);
            text-align: right;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .no-data {
            text-align: center;
            color: var(--clr-surface-a50);
            padding: 40px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .wealth-breakdown {
            background-color: var(--clr-surface-tonal-a10);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .breakdown-title {
            color: var(--clr-primary-a20);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 4px;
            color: var(--clr-surface-a50);
        }

        .breakdown-value {
            color: var(--clr-light-a0);
            font-weight: 500;
        }

        .due-recurring-warning {
            background-color: rgba(251, 191, 36, 0.1);
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .warning-title {
            color: #fcd34d;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .warning-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .warning-icon {
            font-size: 16px;
        }

        .warning-text {
            color: var(--clr-surface-a50);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
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

        .shared-notice-title {
            color: #93c5fd;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .shared-notice-text {
            color: var(--clr-surface-a50);
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .quick-actions {
                width: 100%;
                justify-content: center;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
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
                    <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                    <li><a href="modules/expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="modules/income/index.php">💰 Einnahmen</a></li>
                    <li><a href="modules/recurring/index.php">🔄 Wiederkehrend</a></li>
                    <li><a href="modules/categories/index.php">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="settings.php">⚙️ Einstellungen</a>
                    </li>
                    <li><a href="logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <?= $message ?>

            <!-- Warnung für fällige wiederkehrende Transaktionen -->
            <?php if (!empty($due_recurring)): ?>
                <div class="due-recurring-warning">
                    <div class="warning-title">⚠️ Fällige wiederkehrende Transaktionen</div>
                    <?php foreach ($due_recurring as $due): ?>
                        <div class="warning-item">
                            <span class="warning-icon"><?= htmlspecialchars($due['category_icon']) ?></span>
                            <span class="warning-text">
                                <strong><?= htmlspecialchars($due['note']) ?></strong>
                                (<?= $due['transaction_type'] === 'income' ? '+' : '-' ?>€<?= number_format($due['amount'], 2, ',', '.') ?>)
                                - Fällig am <?= date('d.m.Y', strtotime($due['next_due_date'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top: 10px;">
                        <a href="modules/recurring/index.php" class="btn btn-small">🔄 Wiederkehrende Transaktionen verwalten</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dashboard-header">
                <div class="welcome-text">
                    <h1>📊 Dashboard</h1>
                    <p>Gemeinsamer Überblick über die Finanzen - <?= date('F Y') ?></p>
                </div>
                <div class="quick-actions">
                    <a href="modules/income/add.php" class="btn">+ Einnahme</a>
                    <a href="modules/expenses/add.php" class="btn btn-secondary">+ Ausgabe</a>
                    <a href="modules/recurring/add.php" class="btn" style="background-color: #6b7280;">🔄 Wiederkehrend</a>
                </div>
            </div>

            <!-- Shortcut zu Einstellungen wenn kein Startkapital gesetzt -->
            <?php if ($starting_balance == 0): ?>
                <div style="background-color: rgba(251, 191, 36, 0.1); border: 1px solid #fbbf24; border-radius: 8px; padding: 15px; margin-top: 20px; text-align: center;">
                    <div style="color: #fcd34d; margin-bottom: 10px; font-weight: 600;">💡 Tipp: Startkapital festlegen</div>
                    <div style="color: var(--clr-surface-a50); font-size: 14px; margin-bottom: 15px;">
                        Lege das gemeinsame Startkapital fest, um das echte Gesamtvermögen zu sehen!
                    </div>
                    <a href="settings.php" class="btn btn-small">⚙️ Startkapital festlegen</a>
                </div>
            <?php endif; ?>
            </br>

            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <!-- Gesamtvermögen (Hauptkarte) -->
                <div class="card card-wealth">
                    <div class="card-header">
                        <h3 class="card-title">Gesamtvermögen</h3>
                        <span>🏦</span>
                    </div>
                    <div class="card-value">€<?= number_format($total_wealth, 2, ',', '.') ?></div>
                    <div class="wealth-subtitle">Gemeinsames Startkapital + Einnahmen - Ausgaben</div>

                    <div class="wealth-breakdown">
                        <div class="breakdown-title">📋 Aufschlüsselung</div>
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
                    </div>
                </div>

                <!-- Wiederkehrende Transaktionen -->
                <div class="card card-recurring">
                    <div class="card-header">
                        <h3 class="card-title">Wiederkehrende Transaktionen</h3>
                        <span>🔄</span>
                    </div>
                    <div class="card-value"><?= $recurring_stats['active_recurring'] ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">
                        <?= $recurring_stats['active_recurring'] ?> aktiv von <?= $recurring_stats['total_recurring'] ?> gesamt
                        <?php if ($recurring_stats['due_soon'] > 0): ?>
                            <br><span style="color: #fbbf24; font-weight: 600;"><?= $recurring_stats['due_soon'] ?> bald fällig</span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="card card-income">
                    <div class="card-header">
                        <h3 class="card-title">Einnahmen</h3>
                        <span>💰</span>
                    </div>
                    <div class="card-value">€<?= number_format($total_income, 2, ',', '.') ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Diesen Monat</p>
                </div>

                <div class="card card-expense">
                    <div class="card-header">
                        <h3 class="card-title">Ausgaben</h3>
                        <span>💸</span>
                    </div>
                    <div class="card-value">€<?= number_format($total_expenses, 2, ',', '.') ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Diesen Monat</p>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monatssaldo</h3>
                        <span>📊</span>
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
                    <h3 style="margin-bottom: 20px; color: var(--clr-primary-a20);">
                        📋 Letzte Transaktionen
                    </h3>

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
                                        <?= htmlspecialchars($transaction['note']) ?: 'Keine Beschreibung' ?>
                                        <?php if ($transaction['recurring_transaction_id']): ?>
                                            <span style="color: #a5b4fc; font-size: 12px;">🔄</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="transaction-category">
                                        <?= htmlspecialchars($transaction['category_name']) ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="transaction-amount <?= $transaction['transaction_type'] ?>">
                                        <?= $transaction['transaction_type'] == 'income' ? '+' : '-' ?>€<?= number_format($transaction['amount'], 2, ',', '.') ?>
                                    </div>
                                    <div class="transaction-date">
                                        <?= date('d.m.Y', strtotime($transaction['date'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div style="text-align: center; margin-top: 15px;">
                            <a href="modules/expenses/index.php" style="color: var(--clr-primary-a20); text-decoration: none; font-size: 14px;">
                                Alle Transaktionen anzeigen →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3 style="margin-bottom: 20px; color: var(--clr-primary-a20);">
                        📊 Ausgaben nach Kategorie
                    </h3>

                    <?php if (empty($expense_categories)): ?>
                        <div class="no-data">
                            <p>Noch keine Ausgaben in diesem Monat.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <?php if (!empty($expense_categories)): ?>
        <script>
            // Chart.js Konfiguration
            const ctx = document.getElementById('categoryChart').getContext('2d');

            const chartData = {
                labels: <?= json_encode(array_column($expense_categories, 'name')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($expense_categories, 'total')) ?>,
                    backgroundColor: <?= json_encode(array_column($expense_categories, 'color')) ?>,
                    borderColor: 'var(--clr-surface-a20)',
                    borderWidth: 1
                }]
            };

            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: 'var(--clr-light-a0)',
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>