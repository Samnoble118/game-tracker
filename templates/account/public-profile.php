<?php

declare(strict_types=1);

/** Renders opt-in public cabinet and profile-photograph settings. */

$escape=static fn(string $value): string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
$themeStyle="--accent:{$user->themeAccent()};--bg:{$user->themeBackground()};--panel:{$user->themePanel()};--text:{$user->themeText()}";
$shareUrl=$user->username()===null?'':('/?route=display-cabinet&user='.rawurlencode($user->username()));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Public profile — ArchiveXP</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="theme-<?= $escape($user->themePreset()) ?> density-<?= $escape($user->layoutDensity()) ?>" style="<?= $escape($themeStyle) ?>">
<header class="site-header account-header"><div><a class="back-link" href="/">← Back to collection</a><p class="eyebrow">Player settings</p><h1>Display Cabinet</h1><p class="lede">Choose how other collectors see your ArchiveXP profile.</p></div></header>
<nav class="section-switcher settings-switcher"><a href="/?route=account">My Account</a><a href="/?route=appearance">Appearance</a><a class="is-active" href="/?route=profile-settings">Public profile</a><a href="/?route=data-transfer">Data tools</a></nav>
<main class="account-layout profile-settings-layout"><section class="panel account-card"><div class="panel-heading"><div><p class="eyebrow">Identity</p><h2>Profile and photograph</h2></div></div>
<?php if($errors!==[]): ?><div class="alert alert-error" role="alert"><?php foreach($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?></div><?php endif; ?><?php if($saved): ?><div class="alert alert-success">Public profile updated.</div><?php endif; ?>
<form class="game-form" method="post" action="/?route=profile-settings" enctype="multipart/form-data"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
<div class="profile-photo-row"><?php if($user->profileImage()!==null): ?><img class="profile-photo-preview" src="/?route=profile-image&user=<?= rawurlencode($user->username() ?? '') ?>" alt="Current profile photograph"><?php else: ?><span class="profile-photo-preview profile-photo-placeholder"><?= $escape(strtoupper(substr($user->profileDisplayName(),0,1))) ?></span><?php endif; ?><label><span>Profile picture</span><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"><small class="field-help">JPEG, PNG, or WebP, up to 5 MB.</small></label></div>
<label><span>Display name</span><input name="display_name" maxlength="60" value="<?= $escape($user->profileDisplayName()) ?>" placeholder="Collector name"></label><label><span>Bio</span><textarea name="bio" maxlength="300" rows="5" placeholder="Tell visitors about your collection…"><?= $escape($user->profileBio()) ?></textarea></label>
<label class="checkbox-label"><input type="checkbox" name="profile_public" value="1" <?= $user->profilePublic()?'checked':'' ?> <?= $user->username()===null?'disabled':'' ?>><span>Make my Display Cabinet public</span></label><?php if($user->username()===null): ?><p class="field-help">Add a username in My Account before publishing a shareable profile.</p><?php endif; ?>
<button class="primary-button" type="submit">Save public profile</button><?php if($user->profileImage()!==null): ?><button class="danger-button" type="submit" name="profile_action" value="remove-image">Remove profile picture</button><?php endif; ?></form></section>
<aside class="panel account-card privacy-card"><p class="eyebrow">Privacy first</p><h2>Safe to share</h2><p>Your cabinet shows catalogue details and collection progress. It never includes your email, purchase prices, receipts, serial numbers, storage locations, barcodes, or private notes.</p><?php if($user->profilePublic()&&$shareUrl!==''): ?><a class="primary-button share-profile-button" href="<?= $escape($shareUrl) ?>">View and share profile</a><code class="share-url"><?= $escape($shareUrl) ?></code><?php endif; ?></aside></main></body></html>
