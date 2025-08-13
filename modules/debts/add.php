<?php
// =================================================================
// FILE: modules/debts/add.php  
// =================================================================
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

// Get debt type from URL parameter
$debt_type = $_GET['type'] ?? 'debt_out';
if (!in_array($debt_type, ['debt_in', 'debt_out'])) {
    $debt_type = 'debt_out';
}

// Load debt categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = ? ORDER BY name");
$stmt->execute([$debt_type]);
$categories = $stmt->fetchAll();

// Form processing
$errors = [];
$form_data = [
    'category_id' => '',
    'amount' => '',
    'note' => '',
    'date' => date('Y-m-d')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = trim($_POST['category_id'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $date = trim($_POST['date'] ?? '');

    // Validation
    if (empty($category_id)) {
        $errors[] = 'Kategorie ist erforderlich.';
    }
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = 'Betrag muss eine positive Zahl sein.';
    }
    if (empty($date)) {
        $errors[] = 'Datum ist erforderlich.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, category_id, amount, note, date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $category_id, $amount, $note, $date]);

            $_SESSION['success'] = $debt_type === 'debt_out' ?
                'Geld erfolgreich als verliehen eingetragen!' :
                'Erhaltenes Geld erfolgreich eingetragen!';

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }

    // Store form data for redisplay
    $form_data = [
        'category_id' => $category_id,
        'amount' => $amount,
        'note' => $note,
        'date' => $date
    ];
}

$page_title = $debt_type === 'debt_out' ? 'Geld verleihen' : 'Geld leihen';
$page_icon = $debt_type === 'debt_out' ? '💸' : '💰';
$page_color = $debt_type === 'debt_out' ? '#f97316' : '#22c55e';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - StreamNet Finance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/debt.css">
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
                    <li><a href="../../dashboard.php"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard</a></li>
                    <li><a href="../expenses/index.php"><i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben</a></li>
                    <li><a href="../income/index.php"><i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen</a></li>
                    <li><a href="index.php" class="active"><i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden</a></li>
                    <li><a href="../recurring/index.php"><i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend</a></li>
                    <li><a href="../investments/index.php"><i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto</a></li>
                    <li><a href="../categories/index.php"><i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien</a></li>
                    <li>
                        <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;" href="../../settings.php">
                            <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                        </a>
                    </li>
                    <li>
                        <a href="../../logout.php"><i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 style="color: <?= $page_color ?>; margin-bottom: 5px;">
                        <?= $page_icon ?>&nbsp;&nbsp;<?= $page_title ?>
                    </h1>
                    <p style="color: var(--clr-surface-a50);">
                        <?= $debt_type === 'debt_out' ? 'Erfasse verliehenes Geld' : 'Erfasse erhaltenes Geld' ?>
                    </p>
                </div>
                <a href="index.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
            </div>

            <div class="form-container">
                <div class="form-card">
                    <div class="form-header">
                        <h2><?= $page_icon ?>&nbsp;&nbsp;<?= $page_title ?></h2>
                        <p>Erfasse alle Details der Transaktion</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Fehler:</strong><br>
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($categories)): ?>
                        <div class="alert alert-error">
                            <strong>Keine Kategorien vorhanden!</strong><br>
                            Du musst zuerst <a href="../categories/add.php?type=<?= $debt_type ?>" style="color: <?= $page_color ?>;">
                                Schulden-Kategorien erstellen</a>, bevor du Transaktionen hinzufügen kannst.
                        </div>
                    <?php else: ?>
                        <form method="POST" class="transaction-form">
                            <div class="form-group">
                                <label class="form-label" for="category_id">Kategorie</label>
                                <select id="category_id" name="category_id" class="form-select" required>
                                    <option value="">Kategorie wählen...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"
                                            <?= $form_data['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['icon']) ?> <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="amount">Betrag</label>
                                <div class="currency-input-wrapper">
                                    <span class="currency-symbol">€</span>
                                    <input type="number" id="amount" name="amount" class="form-input currency-input"
                                        step="0.01" min="0.01" value="<?= htmlspecialchars($form_data['amount']) ?>"
                                        placeholder="0,00" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="note">Notiz (optional)</label>
                                <textarea id="note" name="note" class="form-textarea" rows="3"
                                    placeholder="Z.B. An wen verliehen oder wofür..."><?= htmlspecialchars($form_data['note']) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="date">Datum</label>
                                <input type="date" id="date" name="date" class="form-input"
                                    value="<?= htmlspecialchars($form_data['date']) ?>" required>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn">💾 Speichern</button>
                                <a href="index.php" class="btn btn-secondary">Abbrechen</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html>