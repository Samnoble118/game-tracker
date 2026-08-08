<?php

declare(strict_types=1);

/**
 * Renders profile and password management for the current user.
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$themeStyle="--accent:{$user->themeAccent()};--bg:{$user->themeBackground()};--panel:{$user->themePanel()};--text:{$user->themeText()}";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Account — ArchiveXP</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="theme-<?= $escape($user->themePreset()) ?> density-<?= $escape($user->layoutDensity()) ?>" style="<?= $escape($themeStyle) ?>">
    <header class="site-header account-header">
        <div>
            <a class="back-link" href="/">← Back to collection</a>
            <p class="eyebrow">Player settings</p>
            <h1>My Account</h1>
            <p class="lede">Manage your profile and account security.</p>
        </div>
    </header>

    <nav class="section-switcher settings-switcher"><a class="is-active" href="/?route=account">My Account</a><a href="/?route=appearance">Appearance</a><a href="/?route=profile-settings">Public profile</a><a href="/?route=data-transfer">Data tools</a></nav>
    <main class="account-layout">
        <section class="panel account-card">
            <div class="panel-heading">
                <div><p class="eyebrow">Profile</p><h2>Account details</h2></div>
            </div>
            <?php if ($section === 'profile' && $errors !== []): ?>
                <div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($saved === 'profile'): ?><div class="alert alert-success">Profile updated.</div><?php endif; ?>
            <form method="post" action="/?route=account" class="game-form">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="section" value="profile">
                <label><span>Username</span><input name="username" value="<?= $escape($user->username() ?? '') ?>" minlength="3" maxlength="30" placeholder="player_one" autocomplete="username"></label>
                <label><span>Email address</span><input type="email" name="email" value="<?= $escape($user->email()) ?>" required autocomplete="email"></label>
                <label><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
                <button class="primary-button" type="submit">Save profile</button>
            </form>
        </section>

        <section class="panel account-card">
            <div class="panel-heading">
                <div><p class="eyebrow">Security</p><h2>Change password</h2></div>
            </div>
            <?php if ($section === 'password' && $errors !== []): ?>
                <div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($saved === 'password'): ?><div class="alert alert-success">Password updated.</div><?php endif; ?>
            <form method="post" action="/?route=account" class="game-form">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="section" value="password">
                <label><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
                <label><span>New password</span><input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
                <label><span>Confirm new password</span><input type="password" name="new_password_confirmation" required minlength="10" autocomplete="new-password"></label>
                <button class="primary-button" type="submit">Update password</button>
            </form>
        </section>

    </main>
</body>
</html>
