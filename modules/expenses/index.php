<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';

$db = new Database();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Filter-Parameter
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Kategorien für Filter laden (nur Ausgaben-Kategorien)
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Ausgaben laden mit Filtern (UPDATED für neue Schema-Struktur)
$sql = "
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND c.type = 'expense'
";

$params = [$user_id];

if ($selected_month) {
    $sql .= " AND strftime('%Y-%m', t.date) = ?";
    $params[] = $selected_month;
}

if ($selected_category) {
    $sql .= " AND t.category_id = ?";
    $params[] = $selected_category;
}

if ($search) {
    $sql .= " AND (t.note LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY t.date DESC, t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Summe der gefilterten Ausgaben
$total_filtered = array_sum(array_column($expenses, 'amount'));

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
    <title>Ausgaben - Finance Tracker</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .filters {
            background-color: var(--clr-surface-a10);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 14px;
            color: var(--clr-surface-a50);
            margin-bottom: 5px;
        }

        .expenses-summary {
            background-color: var(--clr-surface-tonal-a10);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .expenses-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f87171;
            margin-bottom: 5px;
        }

        .expenses-count {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .expenses-table {
            background-color: var(--clr-surface-a10);
            border-radius: 8px;
            overflow: hidden;
        }

        .table-header {
            background-color: var(--clr-surface-tonal-a10);
            padding: 15px 20px;
            display: grid;
            grid-template-columns: 60px 1fr 150px 120px 120px 80px;
            gap: 15px;
            align-items: center;
            font-weight: 600;
            color: var(--clr-primary-a20);
            font-size: 14px;
        }

        .expense-row {
            padding: 15px 20px;
            display: grid;
            grid-template-columns: 60px 1fr 150px 120px 120px 80px;
            gap: 15px;
            align-items: center;
            border-bottom: 1px solid var(--clr-surface-a20);
            transition: background-color 0.2s ease;
        }

        .expense-row:hover {
            background-color: var(--clr-surface-a20);
        }

        .expense-row:last-child {
            border-bottom: none;
        }

        .category-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .expense-amount {
            font-weight: 600;
            color: #f87171;
            text-align: right;
        }

        .expense-description {
            color: var(--clr-light-a0);
            font-weight: 500;
        }

        .expense-date {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .btn-icon {
            padding: 6px 8px;
            font-size: 12px;
            min-width: auto;
        }

        .btn-edit {
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
        }

        .btn-delete {
            background-color: #f87171;
            color: var(--clr-light-a0);
        }

        .btn-delete:hover {
            background-color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--clr-surface-a50);
        }

        .empty-state h3 {
            color: var(--clr-surface-a40);
            margin-bottom: 10px;
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
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .table-header,
            .expense-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .expense-row {
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 10px;
                background-color: var(--clr-surface-a20);
            }

            .actions {
                justify-content: flex-end;
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
                    <li><a href="../../dashboard.php">📊 Dashboard</a></li>
                    <li><a href="index.php" class="active">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="../categories/index.php">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="../../logout.php">🚪 Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="db-info">
                ✅ <strong>Schema-Update aktiv</strong> - Verwendet neue Datenbankstruktur mit JOINs (note, date, categories.type)
            </div>

            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">💸 Ausgaben</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte deine Ausgaben und behalte den Überblick</p>
                </div>
                <a href="add.php" class="btn">+ Neue Ausgabe</a>
            </div>

            <?= $message ?>

            <!-- Filter Section -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Monat</label>
                    <input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" class="form-input">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Kategorie</label>
                    <select name="category" class="form-select">
                        <option value="">Alle Kategorien</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $selected_category == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Suche</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                        placeholder="Beschreibung oder Kategorie..." class="form-input">
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn">🔍 Filtern</button>
                </div>
            </form>

            <!-- Summary -->
            <?php if (!empty($expenses)): ?>
                <div class="expenses-summary">
                    <div class="expenses-total">€<?= number_format($total_filtered, 2, ',', '.') ?></div>
                    <div class="expenses-count"><?= count($expenses) ?> Ausgabe(n) gefunden</div>
                </div>
            <?php endif; ?>

            <!-- Expenses Table -->
            <div class="expenses-table">
                <?php if (empty($expenses)): ?>
                    <div class="empty-state">
                        <h3>💸 Noch keine Ausgaben</h3>
                        <p>Füge deine erste Ausgabe hinzu, um hier eine Übersicht zu sehen.</p>
                        <div style="margin-top: 20px;">
                            <a href="add.php" class="btn">Erste Ausgabe hinzufügen</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-header">
                        <div>Icon</div>
                        <div>Beschreibung</div>
                        <div>Kategorie</div>
                        <div>Betrag</div>
                        <div>Datum</div>
                        <div>Aktionen</div>
                    </div>

                    <?php foreach ($expenses as $expense): ?>
                        <div class="expense-row">
                            <div style="font-size: 24px;"><?= htmlspecialchars($expense['category_icon']) ?></div>

                            <div class="expense-description">
                                <?= htmlspecialchars($expense['note']) ?: 'Keine Beschreibung' ?>
                            </div>

                            <div>
                                <span class="category-badge" style="background-color: <?= htmlspecialchars($expense['category_color']) ?>20; color: <?= htmlspecialchars($expense['category_color']) ?>;">
                                    <?= htmlspecialchars($expense['category_icon']) ?> <?= htmlspecialchars($expense['category_name']) ?>
                                </span>
                            </div>

                            <div class="expense-amount">
                                €<?= number_format($expense['amount'], 2, ',', '.') ?>
                            </div>

                            <div class="expense-date">
                                <?= date('d.m.Y', strtotime($expense['date'])) ?>
                            </div>

                            <div class="actions">
                                <a href="edit.php?id=<?= $expense['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten">✏️</a>
                                <a href="delete.php?id=<?= $expense['id'] ?>" class="btn btn-icon btn-delete"
                                    onclick="return confirm('Ausgabe wirklich löschen?')" title="Löschen">🗑️</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>