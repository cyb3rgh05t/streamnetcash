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
    <title>Kategorien - StreamNet Finance</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/categories.css">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="sidebar-logo">
                    <img src="../../assets/images/logo.png" alt="StreamNet Finance Logo" class="sidebar-logo-image">
                    <h2 class="sidebar-logo-text">StreamNet Finance</h2>
                </a>
                <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="../../dashboard.php">📊 Dashboard</a></li>
                    <li><a href="../expenses/index.php">💸 Ausgaben</a></li>
                    <li><a href="../income/index.php">💰 Einnahmen</a></li>
                    <li><a href="../recurring/index.php">🔄 Wiederkehrend</a></li>
                    <li><a href="index.php" class="active">🏷️ Kategorien</a></li>
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