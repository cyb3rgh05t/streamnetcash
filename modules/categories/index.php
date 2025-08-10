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

// Kategorien laden
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY type, name");
$stmt->execute([$user_id]);
$all_categories = $stmt->fetchAll();

// Nach Typen trennen
$income_categories = [];
$expense_categories = [];

// Statistiken für jede Kategorie laden (mit neuer Schema-Struktur)
foreach ($all_categories as $category) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            MAX(date) as last_used
        FROM transactions 
        WHERE category_id = ? AND user_id = ?
    ");
    $stmt->execute([$category['id'], $user_id]);
    $stats = $stmt->fetch();

    // Sicherstellen dass alle Werte gesetzt sind
    $category['stats'] = [
        'transaction_count' => isset($stats['transaction_count']) ? (int)$stats['transaction_count'] : 0,
        'total_amount' => isset($stats['total_amount']) ? (float)$stats['total_amount'] : 0.00,
        'last_used' => isset($stats['last_used']) ? $stats['last_used'] : null
    ];

    // Nach Typ trennen
    if ($category['type'] === 'income') {
        $income_categories[] = $category;
    } else {
        $expense_categories[] = $category;
    }
}

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
    <title>Kategorien - Finance Tracker</title>
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

        .categories-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .category-section {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 25px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .section-title.income {
            color: #4ade80;
        }

        .section-title.expense {
            color: #f87171;
        }

        .section-stats {
            font-size: 13px;
            color: var(--clr-surface-a50);
        }

        .category-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .category-card {
            display: grid;
            grid-template-columns: 60px 1fr auto auto;
            gap: 15px;
            align-items: center;
            padding: 15px;
            background-color: var(--clr-surface-a20);
            border: 1px solid var(--clr-surface-a30);
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .category-card:hover {
            background-color: var(--clr-surface-a30);
            transform: translateY(-1px);
        }

        .category-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--clr-light-a0);
        }

        .category-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .category-name {
            font-weight: 600;
            color: var(--clr-light-a0);
            font-size: 16px;
        }

        .category-usage {
            font-size: 12px;
            color: var(--clr-surface-a50);
        }

        .category-stats {
            text-align: right;
            font-size: 13px;
        }

        .stat-amount {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .stat-amount.income {
            color: #4ade80;
        }

        .stat-amount.expense {
            color: #f87171;
        }

        .stat-count {
            color: var(--clr-surface-a50);
            font-size: 11px;
        }

        .category-actions {
            display: flex;
            gap: 6px;
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

        .btn-delete:disabled {
            background-color: var(--clr-surface-a40);
            cursor: not-allowed;
            opacity: 0.5;
        }

        .empty-section {
            text-align: center;
            color: var(--clr-surface-a50);
            padding: 40px 20px;
        }

        .empty-section h4 {
            margin-bottom: 10px;
            color: var(--clr-surface-a40);
        }

        .tips-section {
            background-color: var(--clr-surface-tonal-a10);
            border: 1px solid var(--clr-surface-tonal-a20);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
        }

        .tips-title {
            color: var(--clr-primary-a20);
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .tip-item {
            padding: 12px;
            background-color: var(--clr-surface-a10);
            border-radius: 6px;
            font-size: 13px;
            color: var(--clr-surface-a50);
        }

        .tip-icon {
            margin-right: 8px;
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

        @media (max-width: 1024px) {
            .categories-grid {
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

            .category-card {
                grid-template-columns: 1fr;
                gap: 10px;
                text-align: center;
            }

            .category-stats {
                text-align: center;
            }

            .category-actions {
                justify-content: center;
            }

            .tips-grid {
                grid-template-columns: 1fr;
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
                    <li><a href="../expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="index.php" class="active">🏷️ Kategorien</a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="../../logout.php">🚪 Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="db-info">
                ✅ <strong>Verbesserte Database-Klasse aktiv</strong> - Neue Schema-Struktur mit optimierten JOINs, keine Type-Spalte in Transactions
            </div>

            <div class="page-header">
                <div>
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">🏷️ Kategorien</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte deine Einnahmen- und Ausgaben-Kategorien</p>
                </div>
                <a href="add.php" class="btn">+ Neue Kategorie</a>
            </div>

            <?= $message ?>

            <div class="categories-grid">
                <!-- Einnahmen-Kategorien -->
                <div class="category-section">
                    <div class="section-header">
                        <h2 class="section-title income">💰 Einnahmen</h2>
                        <div class="section-stats">
                            <?= count($income_categories) ?> Kategorien
                        </div>
                    </div>

                    <?php if (empty($income_categories)): ?>
                        <div class="empty-section">
                            <h4>Noch keine Einnahmen-Kategorien</h4>
                            <p>Erstelle deine erste Kategorie für Einnahmen.</p>
                            <div style="margin-top: 15px;">
                                <a href="add.php?type=income" class="btn btn-small">+ Einnahmen-Kategorie</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($income_categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                        <?= htmlspecialchars($category['icon']) ?>
                                    </div>

                                    <div class="category-info">
                                        <div class="category-name"><?= htmlspecialchars($category['name']) ?></div>
                                        <div class="category-usage">
                                            <?php if ($category['stats']['transaction_count'] > 0): ?>
                                                <?= $category['stats']['transaction_count'] ?> Transaktionen
                                                • Zuletzt: <?= date('d.m.Y', strtotime($category['stats']['last_used'])) ?>
                                            <?php else: ?>
                                                Noch nicht verwendet
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="category-stats">
                                        <div class="stat-amount income">
                                            +€<?= number_format($category['stats']['total_amount'], 2, ',', '.') ?>
                                        </div>
                                        <div class="stat-count">
                                            <?= $category['stats']['transaction_count'] ?> Einträge
                                        </div>
                                    </div>

                                    <div class="category-actions">
                                        <a href="edit.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten">✏️</a>
                                        <?php if ($category['stats']['transaction_count'] == 0): ?>
                                            <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-delete"
                                                onclick="return confirm('Kategorie wirklich löschen?')" title="Löschen">🗑️</a>
                                        <?php else: ?>
                                            <button class="btn btn-icon btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden">🔒</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ausgaben-Kategorien -->
                <div class="category-section">
                    <div class="section-header">
                        <h2 class="section-title expense">💸 Ausgaben</h2>
                        <div class="section-stats">
                            <?= count($expense_categories) ?> Kategorien
                        </div>
                    </div>

                    <?php if (empty($expense_categories)): ?>
                        <div class="empty-section">
                            <h4>Noch keine Ausgaben-Kategorien</h4>
                            <p>Erstelle deine erste Kategorie für Ausgaben.</p>
                            <div style="margin-top: 15px;">
                                <a href="add.php?type=expense" class="btn btn-small">+ Ausgaben-Kategorie</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($expense_categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-icon" style="background-color: <?= htmlspecialchars($category['color']) ?>;">
                                        <?= htmlspecialchars($category['icon']) ?>
                                    </div>

                                    <div class="category-info">
                                        <div class="category-name"><?= htmlspecialchars($category['name']) ?></div>
                                        <div class="category-usage">
                                            <?php if ($category['stats']['transaction_count'] > 0): ?>
                                                <?= $category['stats']['transaction_count'] ?> Transaktionen
                                                • Zuletzt: <?= date('d.m.Y', strtotime($category['stats']['last_used'])) ?>
                                            <?php else: ?>
                                                Noch nicht verwendet
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="category-stats">
                                        <div class="stat-amount expense">
                                            -€<?= number_format($category['stats']['total_amount'], 2, ',', '.') ?>
                                        </div>
                                        <div class="stat-count">
                                            <?= $category['stats']['transaction_count'] ?> Einträge
                                        </div>
                                    </div>

                                    <div class="category-actions">
                                        <a href="edit.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten">✏️</a>
                                        <?php if ($category['stats']['transaction_count'] == 0): ?>
                                            <a href="delete.php?id=<?= $category['id'] ?>" class="btn btn-icon btn-delete"
                                                onclick="return confirm('Kategorie wirklich löschen?')" title="Löschen">🗑️</a>
                                        <?php else: ?>
                                            <button class="btn btn-icon btn-delete" disabled title="Kategorie wird verwendet und kann nicht gelöscht werden">🔒</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tips Section -->
            <div class="tips-section">
                <h3 class="tips-title">💡 Tipps für bessere Kategorien</h3>
                <div class="tips-grid">
                    <div class="tip-item">
                        <span class="tip-icon">🎯</span>
                        <strong>Spezifisch sein:</strong> Verwende aussagekräftige Namen wie "Lebensmittel" statt nur "Essen".
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🎨</span>
                        <strong>Farben nutzen:</strong> Verwende ähnliche Farben für verwandte Kategorien.
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">📊</span>
                        <strong>Nicht zu viele:</strong> 5-10 Kategorien pro Typ reichen meist aus.
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🔄</span>
                        <strong>Anpassbar:</strong> Du kannst Kategorien jederzeit bearbeiten, aber nicht löschen wenn sie verwendet werden.
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>