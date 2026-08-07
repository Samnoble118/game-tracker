<?php

declare(strict_types=1);

/**
 * Renders profile and password management for the current user.
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Account — Game Tracker</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <header class="site-header account-header">
        <div>
            <a class="back-link" href="/">← Back to collection</a>
            <p class="eyebrow">Player settings</p>
            <h1>My Account</h1>
            <p class="lede">Manage your profile, security, and dashboard style.</p>
        </div>
    </header>

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

        <section class="panel appearance-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">Personalisation</p><h2>Dashboard appearance</h2></div>
                <p class="section-description">Add a personal backdrop while keeping your collection easy to read.</p>
            </div>
            <?php if ($section === 'appearance' && $errors !== []): ?>
                <div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($saved === 'appearance'): ?><div class="alert alert-success">Dashboard appearance updated.</div><?php endif; ?>
            <div class="appearance-grid">
                <div class="appearance-preview <?= $user->dashboardImage() === null ? 'is-empty' : '' ?>" <?= $user->dashboardImage() !== null ? 'style="--dashboard-image: url(\'/?route=dashboard-image\'); --dashboard-overlay: ' . ($user->dashboardOverlay() / 100) . '"' : '' ?>>
                    <?php if ($user->dashboardImage() === null): ?><span>Your image preview will appear here</span><?php endif; ?>
                </div>
                <form method="post" action="/?route=account" class="game-form appearance-form" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="section" value="appearance">
                    <label><span>Choose an image</span><input type="file" name="dashboard_image" accept="image/jpeg,image/png,image/webp"></label>
                    <p class="field-help">JPEG, PNG, or WebP, up to 5 MB.</p>
                    <label><span>Display style</span><select name="image_mode"><option value="banner" <?= $user->dashboardImageMode() === 'banner' ? 'selected' : '' ?>>Header banner</option><option value="wallpaper" <?= $user->dashboardImageMode() === 'wallpaper' ? 'selected' : '' ?>>Page wallpaper</option></select></label>
                    <label><span>Dark overlay: <output id="overlay-output"><?= $user->dashboardOverlay() ?>%</output></span><input type="range" name="overlay" min="20" max="90" value="<?= $user->dashboardOverlay() ?>" oninput="document.getElementById('overlay-output').value = this.value + '%'" /></label>
                    <div class="appearance-actions">
                        <button class="primary-button" type="submit">Save appearance</button>
                        <?php if ($user->dashboardImage() !== null): ?>
                            <button class="danger-button" type="submit" name="appearance_action" value="remove">Remove image</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel appearance-panel">
            <div class="panel-heading">
                <div><p class="eyebrow">Personalisation</p><h2>Merchandise appearance</h2></div>
                <p class="section-description">Choose separate artwork for your physical collection.</p>
            </div>
            <?php if ($section === 'merchandise-appearance' && $errors !== []): ?>
                <div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($saved === 'merchandise-appearance'): ?><div class="alert alert-success">Merchandise appearance updated.</div><?php endif; ?>
            <div class="appearance-grid">
                <div class="appearance-preview <?= $user->merchandiseImage() === null ? 'is-empty' : '' ?>" <?= $user->merchandiseImage() !== null ? 'style="--dashboard-image: url(\'/?route=merchandise-image\'); --dashboard-overlay: ' . ($user->merchandiseOverlay() / 100) . '"' : '' ?>>
                    <?php if ($user->merchandiseImage() === null): ?><span>Your merchandise image preview will appear here</span><?php endif; ?>
                </div>
                <form method="post" action="/?route=account" class="game-form appearance-form" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="section" value="merchandise-appearance">
                    <label><span>Choose an image</span><input type="file" name="merchandise_image" accept="image/jpeg,image/png,image/webp"></label>
                    <p class="field-help">JPEG, PNG, or WebP, up to 5 MB.</p>
                    <label><span>Display style</span><select name="image_mode"><option value="banner" <?= $user->merchandiseImageMode() === 'banner' ? 'selected' : '' ?>>Header banner</option><option value="wallpaper" <?= $user->merchandiseImageMode() === 'wallpaper' ? 'selected' : '' ?>>Page wallpaper</option></select></label>
                    <label><span>Dark overlay: <output id="merchandise-overlay-output"><?= $user->merchandiseOverlay() ?>%</output></span><input type="range" name="overlay" min="20" max="90" value="<?= $user->merchandiseOverlay() ?>" oninput="document.getElementById('merchandise-overlay-output').value = this.value + '%'" /></label>
                    <div class="appearance-actions">
                        <button class="primary-button" type="submit">Save merchandise appearance</button>
                        <?php if ($user->merchandiseImage() !== null): ?>
                            <button class="danger-button" type="submit" name="appearance_action" value="remove">Remove image</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
