<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
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
                    <li><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                            📊 Dashboard
                        </a></li>
                    <li><a href="modules/expenses/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'expenses') ? 'active' : '' ?>">
                            💸 Ausgaben
                        </a></li>
                    <li><a href="modules/income/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'income') ? 'active' : '' ?>">
                            💰 Einnahmen
                        </a></li>
                    <li><a href="modules/categories/index.php" class="<?= strpos($_SERVER['PHP_SELF'], 'categories') ? 'active' : '' ?>">
                            🏷️ Kategorien
                        </a></li>
                    <li style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;">
                        <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">⚙️ Einstellungen</a>
                    </li>
                    <li>
                        <a href="logout.php">🚪 Logout</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">