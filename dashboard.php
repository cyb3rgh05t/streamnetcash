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

// Dashboard-Statistiken laden
$current_month = date('Y-m');

// Gesamte Einnahmen diesen Monat (via JOIN mit categories)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND c.type = 'income' AND strftime('%Y-%m', t.date) = ?
");
$stmt->execute([$user_id, $current_month]);
$total_income = $stmt->fetchColumn();

// Gesamte Ausgaben diesen Monat (via JOIN mit categories)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND c.type = 'expense' AND strftime('%Y-%m', t.date) = ?
");
$stmt->execute([$user_id, $current_month]);
$total_expenses = $stmt->fetchColumn();

// Saldo berechnen
$balance = $total_income - $total_expenses;

// Letzte 5 Transaktionen
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll();

// Ausgaben nach Kategorien für Chart
$stmt = $pdo->prepare("
    SELECT c.name, c.color, c.icon, COALESCE(SUM(t.amount), 0) as total
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id AND strftime('%Y-%m', t.date) = ?
    WHERE c.user_id = ? AND c.type = 'expense'
    GROUP BY c.id, c.name, c.color, c.icon
    HAVING total > 0
    ORDER BY total DESC
");
$stmt->execute([$current_month, $user_id]);
$expense_categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Finance Tracker</title>
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

        .db-info {
            background-color: var(--clr-surface-tonal-a10);
            border: 1px solid var(--clr-primary-a0);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: var(--clr-primary-a20);
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
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div style="padding: 20px; border-bottom: 1px solid var(--clr-surface-a20); margin-bottom: 20px;">
                <h2 style="color: var(--clr-primary-a20);">💰 Finance Tracker</h2>
                <p style="color: var(--clr-surface-a50); font-size: 14px;">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                    <li><a href="modules/expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="modules/income/index.php">💰 Einnahmen</a></li>
                    <li><a href="modules/categories/index.php">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="logout.php">🚪 Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="db-info">
                ✅ <strong>Verbesserte Database-Klasse aktiv</strong> - Neue Schema-Struktur mit optimierten JOINs
            </div>

            <div class="dashboard-header">
                <div class="welcome-text">
                    <h1>Dashboard</h1>
                    <p>Überblick über deine Finanzen - <?= date('F Y') ?></p>
                </div>
                <div class="quick-actions">
                    <a href="modules/income/add.php" class="btn">+ Einnahme</a>
                    <a href="modules/expenses/add.php" class="btn btn-secondary">+ Ausgabe</a>
                </div>
            </div>

            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="card card-income">
                    <div class="card-header">
                        <h3 class="card-title">Gesamte Einnahmen</h3>
                        <span>💰</span>
                    </div>
                    <div class="card-value">€<?= number_format($total_income, 2, ',', '.') ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Diesen Monat</p>
                </div>

                <div class="card card-expense">
                    <div class="card-header">
                        <h3 class="card-title">Gesamte Ausgaben</h3>
                        <span>💸</span>
                    </div>
                    <div class="card-value">€<?= number_format($total_expenses, 2, ',', '.') ?></div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Diesen Monat</p>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Saldo</h3>
                        <span>📊</span>
                    </div>
                    <div class="card-value" style="color: <?= $balance >= 0 ? '#4ade80' : '#f87171' ?>">
                        €<?= number_format($balance, 2, ',', '.') ?>
                    </div>
                    <p style="color: var(--clr-surface-a50); font-size: 14px;">Einnahmen - Ausgaben</p>
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