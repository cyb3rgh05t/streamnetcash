<?php
// includes/sidebar.php - Zentrale Sidebar Komponente
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

// Aktuelle Seite für Active-State bestimmen
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Helper function für Active-State
function isActive($page, $directory = null)
{
    global $current_page, $current_dir;

    if ($directory) {
        return $current_dir === $directory ? 'active' : '';
    }

    return $current_page === $page ? 'active' : '';
}

// Helper function für korrekte Pfade je nach Ordnertiefe
function getBasePath()
{
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));

    // Wenn wir in einem Modul-Ordner sind (modules/expenses/, modules/income/, etc.)
    if (in_array($current_dir, ['expenses', 'income', 'debts', 'recurring', 'investments', 'categories'])) {
        return '../../';
    }

    // Wenn wir im Root-Verzeichnis sind (dashboard.php, settings.php, etc.)
    return '';
}

$basePath = getBasePath();

// Sidebar Override CSS automatisch laden
$basePath = getBasePath();
echo '<link rel="stylesheet" href="' . $basePath . 'assets/css/sidebar.css">';
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $basePath ?>dashboard.php" class="sidebar-logo">
            <img src="<?= $basePath ?>assets/images/logo.png" alt="StreamNet Finance Logo" class="sidebar-logo-image">
        </a>
        <p class="sidebar-welcome">Willkommen, <?= htmlspecialchars($_SESSION['username']) ?></p>
    </div>

    <nav>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= $basePath ?>dashboard.php" class="<?= isActive('dashboard.php') ?>">
                    <i class="fa-solid fa-house"></i>&nbsp;&nbsp;Dashboard
                </a>
            </li>
            <li>
                <a href="<?= $basePath ?>modules/expenses/index.php" class="<?= isActive(null, 'expenses') ?>">
                    <i class="fa-solid fa-money-bill-wave"></i>&nbsp;&nbsp;Ausgaben
                </a>
            </li>
            <li>
                <a href="<?= $basePath ?>modules/income/index.php" class="<?= isActive(null, 'income') ?>">
                    <i class="fa-solid fa-sack-dollar"></i>&nbsp;&nbsp;Einnahmen
                </a>
            </li>
            <li>
                <a href="<?= $basePath ?>modules/debts/index.php" class="<?= isActive(null, 'debts') ?>">
                    <i class="fa-solid fa-handshake"></i>&nbsp;&nbsp;Schulden
                </a>
            </li>
            <li>
                <a href="<?= $basePath ?>modules/recurring/index.php" class="<?= isActive(null, 'recurring') ?>">
                    <i class="fas fa-sync"></i>&nbsp;&nbsp;Wiederkehrend
                </a>
            </li>
            <li>
                <a href="<?= $basePath ?>modules/investments/index.php" class="<?= isActive(null, 'investments') ?>">
                    <i class="fa-brands fa-btc"></i>&nbsp;&nbsp;Crypto
                </a>
            </li>
            <li>
                <a href="<?= $basePath ?>modules/categories/index.php" class="<?= isActive(null, 'categories') ?>">
                    <i class="fa-solid fa-layer-group"></i>&nbsp;&nbsp;Kategorien
                </a>
            </li>
            <li>
                <a style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;"
                    href="<?= $basePath ?>settings.php" class="<?= isActive('settings.php') ?>">
                    <i class="fa-solid fa-gear"></i>&nbsp;&nbsp;Einstellungen
                </a>
            </li>
            <li>
                <a href="<?= $basePath ?>logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i>&nbsp;&nbsp;Logout
                </a>
            </li>
        </ul>
    </nav>
</aside>