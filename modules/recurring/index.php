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

// Automatisch fällige wiederkehrende Transaktionen verarbeiten
$processed_count = $db->processDueRecurringTransactions($user_id);

if ($processed_count > 0) {
    $_SESSION['success'] = "$processed_count wiederkehrende Transaktion(en) automatisch erstellt!";
}

// Filter-Parameter
$selected_type = $_GET['type'] ?? '';
$selected_frequency = $_GET['frequency'] ?? '';
$selected_status = $_GET['status'] ?? '';

// Wiederkehrende Transaktionen laden mit Filtern
$sql = "
    SELECT rt.*, c.name as category_name, c.icon as category_icon, c.color as category_color, c.type as transaction_type
    FROM recurring_transactions rt
    JOIN categories c ON rt.category_id = c.id
    WHERE rt.user_id IS NOT NULL
";

$params = [];

if ($selected_type) {
    $sql .= " AND c.type = ?";
    $params[] = $selected_type;
}

if ($selected_frequency) {
    $sql .= " AND rt.frequency = ?";
    $params[] = $selected_frequency;
}

if ($selected_status !== '') {
    $sql .= " AND rt.is_active = ?";
    $params[] = $selected_status;
}

$sql .= " ORDER BY rt.next_due_date ASC, rt.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recurring_transactions = $stmt->fetchAll();

// Statistiken
$active_count = count(array_filter($recurring_transactions, fn($r) => $r['is_active']));
$income_count = count(array_filter($recurring_transactions, fn($r) => $r['transaction_type'] === 'income'));
$expense_count = count(array_filter($recurring_transactions, fn($r) => $r['transaction_type'] === 'expense'));

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

// Häufigkeits-Labels
$frequency_labels = [
    'daily' => 'Täglich',
    'weekly' => 'Wöchentlich',
    'monthly' => 'Monatlich',
    'yearly' => 'Jährlich'
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiederkehrende Transaktionen - StreamNet Finance</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/recurring.css">
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
                    <li><a href="index.php" class="active">🔄 Wiederkehrend</a></li>
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
                    <h1 style="color: var(--clr-primary-a20); margin-bottom: 5px;">🔄 Wiederkehrende Transaktionen</h1>
                    <p style="color: var(--clr-surface-a50);">Automatisiere deine regelmäßigen Einnahmen und Ausgaben</p>
                </div>
                <a href="add.php" class="btn">+ Neue wiederkehrende Transaktion</a>
            </div>

            <?= $message ?>

            <!-- Quick Stats -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value total"><?= count($recurring_transactions) ?></div>
                    <div class="stat-label">Gesamt</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value active"><?= $active_count ?></div>
                    <div class="stat-label">Aktiv</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value income"><?= $income_count ?></div>
                    <div class="stat-label">Einnahmen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value expense"><?= $expense_count ?></div>
                    <div class="stat-label">Ausgaben</div>
                </div>
            </div>

            <!-- Filter Section -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Typ</label>
                    <select name="type" class="form-select">
                        <option value="">Alle Typen</option>
                        <option value="income" <?= $selected_type === 'income' ? 'selected' : '' ?>>Einnahmen</option>
                        <option value="expense" <?= $selected_type === 'expense' ? 'selected' : '' ?>>Ausgaben</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Häufigkeit</label>
                    <select name="frequency" class="form-select">
                        <option value="">Alle Häufigkeiten</option>
                        <option value="daily" <?= $selected_frequency === 'daily' ? 'selected' : '' ?>>Täglich</option>
                        <option value="weekly" <?= $selected_frequency === 'weekly' ? 'selected' : '' ?>>Wöchentlich</option>
                        <option value="monthly" <?= $selected_frequency === 'monthly' ? 'selected' : '' ?>>Monatlich</option>
                        <option value="yearly" <?= $selected_frequency === 'yearly' ? 'selected' : '' ?>>Jährlich</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Alle Status</option>
                        <option value="1" <?= $selected_status === '1' ? 'selected' : '' ?>>Aktiv</option>
                        <option value="0" <?= $selected_status === '0' ? 'selected' : '' ?>>Inaktiv</option>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn">🔍 Filtern</button>
                </div>
            </form>

            <!-- Recurring Transactions Table -->
            <div class="recurring-table">
                <?php if (empty($recurring_transactions)): ?>
                    <div class="empty-state">
                        <h3>🔄 Noch keine wiederkehrenden Transaktionen</h3>
                        <p>Automatisiere deine regelmäßigen Einnahmen und Ausgaben.</p>
                        <div style="margin-top: 20px;">
                            <a href="add.php" class="btn">Erste wiederkehrende Transaktion erstellen</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-header">
                        <div>Icon</div>
                        <div>Beschreibung</div>
                        <div>Betrag</div>
                        <div>Häufigkeit</div>
                        <div>Nächste Fälligkeit</div>
                        <div>Status</div>
                        <div>Erstellt</div>
                        <div>Aktionen</div>
                    </div>

                    <?php foreach ($recurring_transactions as $recurring): ?>
                        <?php
                        $today = date('Y-m-d');
                        $next_due = $recurring['next_due_date'];
                        $is_overdue = $next_due < $today;
                        $is_due_soon = $next_due <= date('Y-m-d', strtotime('+3 days'));
                        ?>
                        <div class="recurring-row <?= !$recurring['is_active'] ? 'inactive' : '' ?>">
                            <div style="font-size: 24px;"><?= htmlspecialchars($recurring['category_icon']) ?></div>

                            <div>
                                <div style="font-weight: 500; color: var(--clr-light-a0); margin-bottom: 4px;">
                                    <?= htmlspecialchars($recurring['note']) ?: 'Keine Beschreibung' ?>
                                </div>
                                <span class="category-badge" style="background-color: <?= htmlspecialchars($recurring['category_color']) ?>20; color: <?= htmlspecialchars($recurring['category_color']) ?>;">
                                    <?= htmlspecialchars($recurring['category_icon']) ?> <?= htmlspecialchars($recurring['category_name']) ?>
                                </span>
                            </div>

                            <div class="amount <?= $recurring['transaction_type'] ?>">
                                <?= $recurring['transaction_type'] === 'income' ? '+' : '-' ?>€<?= number_format($recurring['amount'], 2, ',', '.') ?>
                            </div>

                            <div>
                                <span class="frequency-badge">
                                    <?= $frequency_labels[$recurring['frequency']] ?>
                                </span>
                            </div>

                            <div class="next-due <?= $is_overdue ? 'overdue' : ($is_due_soon ? 'due-soon' : '') ?>">
                                <?= date('d.m.Y', strtotime($next_due)) ?>
                                <?php if ($is_overdue): ?>
                                    <br><small style="color: #f87171;">Überfällig</small>
                                <?php elseif ($is_due_soon): ?>
                                    <br><small style="color: #fbbf24;">Bald fällig</small>
                                <?php endif; ?>
                            </div>

                            <div>
                                <span class="status-badge <?= $recurring['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $recurring['is_active'] ? '✅ Aktiv' : '⏸️ Pausiert' ?>
                                </span>
                            </div>

                            <div style="font-size: 14px; color: var(--clr-surface-a50);">
                                <?= date('d.m.Y', strtotime($recurring['created_at'])) ?>
                            </div>

                            <div class="actions">
                                <a href="edit.php?id=<?= $recurring['id'] ?>" class="btn btn-icon btn-edit" title="Bearbeiten">✏️</a>
                                <a href="toggle.php?id=<?= $recurring['id'] ?>" class="btn btn-icon btn-toggle"
                                    title="<?= $recurring['is_active'] ? 'Pausieren' : 'Aktivieren' ?>">
                                    <?= $recurring['is_active'] ? '⏸️' : '▶️' ?>
                                </a>
                                <a href="delete.php?id=<?= $recurring['id'] ?>" class="btn btn-icon btn-delete"
                                    onclick="return confirm('Wiederkehrende Transaktion wirklich löschen?')" title="Löschen">🗑️</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>