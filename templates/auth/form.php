<?php

declare(strict_types=1);

/**
 * Renders the login and account-registration forms.
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$registering = $mode === 'register';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $registering ? 'Create account' : 'Sign in' ?> — Game Tracker</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-intro">
            <p class="eyebrow">Your personal library</p>
            <h1>Game Tracker</h1>
            <p class="lede">Your games, progress, wishlist, and trophies—private to your account.</p>
        </section>

        <section class="panel auth-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow"><?= $registering ? 'New player' : 'Welcome back' ?></p>
                    <h2><?= $registering ? 'Create your account' : 'Sign in' ?></h2>
                </div>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/?route=<?= $mode ?>" class="game-form">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <label>
                    <span>Email address</span>
                    <input type="email" name="email" value="<?= $escape($email) ?>" required autocomplete="email">
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" required minlength="10" autocomplete="<?= $registering ? 'new-password' : 'current-password' ?>">
                </label>
                <?php if ($registering): ?>
                    <label>
                        <span>Confirm password</span>
                        <input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password">
                    </label>
                <?php endif; ?>
                <button class="primary-button" type="submit"><?= $registering ? 'Create account' : 'Sign in' ?></button>
            </form>

            <p class="auth-switch">
                <?= $registering ? 'Already registered?' : 'New to Game Tracker?' ?>
                <a class="text-link" href="/?route=<?= $registering ? 'login' : 'register' ?>">
                    <?= $registering ? 'Sign in' : 'Create an account' ?>
                </a>
            </p>
        </section>
    </main>
</body>
</html>
