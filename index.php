<?php
session_start();

// Wenn bereits eingeloggt, weiterleiten zum Dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

$success_message = '';
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-tonal-a0) 100%);
        }

        .login-card {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-title {
            color: var(--clr-primary-a20);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--clr-surface-a20);
        }

        .tab-button {
            flex: 1;
            padding: 12px;
            background-color: var(--clr-surface-a20);
            color: var(--clr-surface-a50);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
        }

        .form-container {
            display: none;
        }

        .form-container.active {
            display: block;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #fca5a5;
        }

        .alert-success {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #86efac;
        }

        .btn-full {
            width: 100%;
            justify-content: center;
            margin-top: 10px;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
        }

        .form-footer p {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">💰 Finance Tracker</h1>
                <p class="login-subtitle">Verwalte deine Finanzen intelligent</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <div class="tab-buttons">
                <button class="tab-button active" onclick="switchTab('login')">Anmelden</button>
                <button class="tab-button" onclick="switchTab('register')">Registrieren</button>
            </div>

            <!-- Login Form -->
            <div id="login-form" class="form-container active">
                <form action="auth/login.php" method="POST">
                    <div class="form-group">
                        <label class="form-label" for="login-username">Benutzername</label>
                        <input type="text" id="login-username" name="username" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login-password">Passwort</label>
                        <input type="password" id="login-password" name="password" class="form-input" required>
                    </div>

                    <button type="submit" class="btn btn-full">Anmelden</button>
                </form>
            </div>

            <!-- Register Form -->
            <div id="register-form" class="form-container">
                <form action="auth/register.php" method="POST">
                    <div class="form-group">
                        <label class="form-label" for="reg-username">Benutzername</label>
                        <input type="text" id="reg-username" name="username" class="form-input" required minlength="3">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-email">E-Mail</label>
                        <input type="email" id="reg-email" name="email" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-password">Passwort</label>
                        <input type="password" id="reg-password" name="password" class="form-input" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-password-confirm">Passwort bestätigen</label>
                        <input type="password" id="reg-password-confirm" name="password_confirm" class="form-input" required>
                    </div>

                    <button type="submit" class="btn btn-full">Registrieren</button>
                </form>
            </div>

            <div class="form-footer">
                <p>© 2025 Finance Tracker - Deine persönliche Finanzverwaltung</p>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Form containers
            document.querySelectorAll('.form-container').forEach(container => {
                container.classList.remove('active');
            });

            // Activate selected tab
            if (tab === 'login') {
                document.querySelector('.tab-button:first-child').classList.add('active');
                document.getElementById('login-form').classList.add('active');
            } else {
                document.querySelector('.tab-button:last-child').classList.add('active');
                document.getElementById('register-form').classList.add('active');
            }
        }

        // Password confirmation validation
        document.getElementById('reg-password-confirm').addEventListener('input', function() {
            const password = document.getElementById('reg-password').value;
            const confirm = this.value;

            if (password !== confirm) {
                this.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>

</html>