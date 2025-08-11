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

// Aktuelles Startkapital laden
$current_starting_balance = $db->getStartingBalance($user_id);

// Aktuelle Statistiken berechnen
$current_month = date('Y-m');

// Gesamte Einnahmen (alle Zeit)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND c.type = 'income'
");
$stmt->execute([$user_id]);
$total_income_all_time = $stmt->fetchColumn();

// Gesamte Ausgaben (alle Zeit) 
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND c.type = 'expense'
");
$stmt->execute([$user_id]);
$total_expenses_all_time = $stmt->fetchColumn();

// Gesamtvermögen berechnen
$total_balance = $current_starting_balance + $total_income_all_time - $total_expenses_all_time;

// Form-Verarbeitung
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_starting_balance = $_POST['starting_balance'] ?? '';

    // Validierung
    if ($new_starting_balance === '') {
        $errors[] = 'Startkapital ist erforderlich.';
    } elseif (!is_numeric($new_starting_balance)) {
        $errors[] = 'Startkapital muss eine Zahl sein.';
    } else {
        $new_starting_balance = (float)$new_starting_balance;

        try {
            if ($db->updateStartingBalance($user_id, $new_starting_balance)) {
                $success = 'Startkapital erfolgreich auf €' . number_format($new_starting_balance, 2, ',', '.') . ' aktualisiert!';
                $current_starting_balance = $new_starting_balance;
                // Gesamtvermögen neu berechnen
                $total_balance = $current_starting_balance + $total_income_all_time - $total_expenses_all_time;
            } else {
                $errors[] = 'Fehler beim Aktualisieren des Startkapitals.';
            }
        } catch (Exception $e) {
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Finance Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .settings-card {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 25px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--clr-primary-a20);
        }

        .current-balance {
            text-align: center;
            padding: 20px;
            background-color: var(--clr-surface-tonal-a10);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .balance-amount {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .balance-amount.positive {
            color: #4ade80;
        }

        .balance-amount.negative {
            color: #f87171;
        }

        .balance-amount.neutral {
            color: var(--clr-primary-a20);
        }

        .balance-label {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .form-group-inline {
            display: flex;
            align-items: end;
            gap: 15px;
        }

        .currency-input-wrapper {
            position: relative;
            flex: 1;
        }

        .currency-symbol {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--clr-primary-a20);
            font-weight: 600;
            pointer-events: none;
        }

        .currency-input {
            padding-left: 30px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            background-color: var(--clr-surface-a20);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-value.income {
            color: #4ade80;
        }

        .stat-value.expense {
            color: #f87171;
        }

        .stat-value.neutral {
            color: var(--clr-primary-a20);
        }

        .stat-label {
            color: var(--clr-surface-a50);
            font-size: 13px;
        }

        .info-box {
            background-color: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .info-title {
            color: #93c5fd;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .info-text {
            color: var(--clr-surface-a50);
            font-size: 13px;
            line-height: 1.5;
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

        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #fca5a5;
        }

        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .stats-overview {
                grid-template-columns: 1fr 1fr;
            }

            .form-group-inline {
                flex-direction: column;
                align-items: stretch;
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
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="modules/expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="modules/income/index.php">💰 Einnahmen</a></li>
                    <li><a href="modules/categories/index.php">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="settings.php" class="active">⚙️ Einstellungen</a>
                    </li>
                    <li><a href="logout.php">🚪 Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">⚙️ Einstellungen</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte dein Startkapital und Kontoeinstellungen</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">← Zurück zum Dashboard</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Fehler:</strong><br>
                    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <!-- Startkapital Einstellungen -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">💰</div>
                        <div class="card-title">Startkapital</div>
                    </div>

                    <div class="current-balance">
                        <div class="balance-amount neutral">
                            €<?= number_format($current_starting_balance, 2, ',', '.') ?>
                        </div>
                        <div class="balance-label">Aktuelles Startkapital</div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="starting_balance">Neues Startkapital</label>
                            <div class="form-group-inline">
                                <div class="currency-input-wrapper">
                                    <span class="currency-symbol">€</span>
                                    <input type="number" id="starting_balance" name="starting_balance"
                                        class="form-input currency-input"
                                        step="0.01"
                                        value="<?= number_format($current_starting_balance, 2, '.', '') ?>"
                                        placeholder="0,00" required>
                                </div>
                                <button type="submit" class="btn">💾 Speichern</button>
                            </div>
                        </div>
                    </form>

                    <div class="info-box">
                        <div class="info-title">💡 Was ist Startkapital?</div>
                        <div class="info-text">
                            Das Startkapital ist der Geldbetrag, den du zu Beginn hattest (z.B. Kontostand beim Start der App).
                            Es wird zu deinen Einnahmen hinzugefügt und ist die Basis für die Berechnung deines Gesamtvermögens.
                        </div>
                    </div>
                </div>

                <!-- Vermögensübersicht -->
                <div class="settings-card">
                    <div class="card-header">
                        <div class="card-icon">📊</div>
                        <div class="card-title">Vermögensübersicht</div>
                    </div>

                    <div class="current-balance">
                        <div class="balance-amount <?= $total_balance >= 0 ? 'positive' : 'negative' ?>">
                            €<?= number_format($total_balance, 2, ',', '.') ?>
                        </div>
                        <div class="balance-label">Gesamtvermögen</div>
                    </div>

                    <div class="stats-overview">
                        <div class="stat-item">
                            <div class="stat-value neutral">€<?= number_format($current_starting_balance, 2, ',', '.') ?></div>
                            <div class="stat-label">Startkapital</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value income">+€<?= number_format($total_income_all_time, 2, ',', '.') ?></div>
                            <div class="stat-label">Gesamt Einnahmen</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value expense">-€<?= number_format($total_expenses_all_time, 2, ',', '.') ?></div>
                            <div class="stat-label">Gesamt Ausgaben</div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-value <?= ($total_income_all_time - $total_expenses_all_time) >= 0 ? 'income' : 'expense' ?>">
                                <?= ($total_income_all_time - $total_expenses_all_time) >= 0 ? '+' : '' ?>€<?= number_format($total_income_all_time - $total_expenses_all_time, 2, ',', '.') ?>
                            </div>
                            <div class="stat-label">Bilanz (Ein-/Ausgaben)</div>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-title">📈 Berechnung</div>
                        <div class="info-text">
                            <strong>Gesamtvermögen = Startkapital + Einnahmen - Ausgaben</strong><br>
                            €<?= number_format($current_starting_balance, 2, ',', '.') ?> + €<?= number_format($total_income_all_time, 2, ',', '.') ?> - €<?= number_format($total_expenses_all_time, 2, ',', '.') ?> = €<?= number_format($total_balance, 2, ',', '.') ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Amount-Input formatieren
        document.getElementById('starting_balance').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });

        // Focus auf Input beim Laden
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('starting_balance');
            if (input) {
                input.focus();
                input.select();
            }
        });
    </script>
</body>

</html>