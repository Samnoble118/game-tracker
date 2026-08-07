<?php

declare(strict_types=1);

/**
 * Renders physical merchandise management and collection filters.
 */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merchandise — Game Tracker</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?= $currentUser->merchandiseImage() !== null ? 'has-dashboard-image image-mode-' . $escape($currentUser->merchandiseImageMode()) : '' ?>" style="<?= $currentUser->merchandiseImage() !== null ? '--dashboard-image: url(\'/?route=merchandise-image\'); --dashboard-overlay: ' . ($currentUser->merchandiseOverlay() / 100) : '' ?>">
    <header class="site-header merchandise-header">
        <div><p class="eyebrow">Physical collection</p><h1>Merchandise</h1><p class="lede">Track figures, statues, Pop Vinyls, LEGO, and everything on your shelf.</p></div>
        <div class="summary"><strong><?= count($allItems) ?></strong><span><?= count($allItems) === 1 ? 'item' : 'items' ?></span></div>
    </header>

    <div class="account-bar">
        <div class="account-identity">
            <span class="account-avatar" aria-hidden="true"><?= strtoupper(substr($currentUser->displayName(), 0, 1)) ?></span>
            <span><small>Signed in as</small><strong><?= $escape($currentUser->displayName()) ?></strong></span>
        </div>
        <a class="account-button" href="/?route=account">My Account</a>
        <form method="post" action="/?route=logout">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <button class="logout-button" type="submit">Sign out</button>
        </form>
    </div>

    <nav class="section-switcher" aria-label="Tracker sections">
        <a href="/">Games</a><a class="is-active" href="/?route=merchandise" aria-current="page">Merchandise</a>
    </nav>

    <nav class="collection-tabs merchandise-collections" aria-label="Merchandise collections">
        <?php foreach (['all'=>'All items','owned'=>'Owned','wishlist'=>'Wishlist'] as $key => $label): ?>
            <a class="collection-tab <?= $activeCollection === $key ? 'is-active' : '' ?>" href="/?<?= $escape(http_build_query(['route'=>'merchandise','collection'=>$key,'category'=>$activeCategory,'packaging'=>$activePackaging,'q'=>$search])) ?>"><span><?= $label ?></span><strong><?= $counts[$key] ?></strong></a>
        <?php endforeach; ?>
    </nav>

    <section class="library-tools merchandise-tools">
        <form class="search-form" method="get" action="/">
            <input type="hidden" name="route" value="merchandise"><input type="hidden" name="collection" value="<?= $escape($activeCollection) ?>"><input type="hidden" name="category" value="<?= $escape($activeCategory) ?>">
            <input type="hidden" name="packaging" value="<?= $escape($activePackaging) ?>">
            <label class="search-field"><span class="visually-hidden">Search merchandise</span><input type="search" name="q" value="<?= $escape($search) ?>" placeholder="Search item or franchise…"></label>
            <button class="filter-button" type="submit">Search</button><a class="reset-link" href="/?route=merchandise">Reset</a>
        </form>
        <nav class="platform-tabs" aria-label="Merchandise categories">
            <a class="platform-tab <?= $activeCategory === 'all' ? 'is-active' : '' ?>" href="/?<?= $escape(http_build_query(['route'=>'merchandise','collection'=>$activeCollection,'category'=>'all','packaging'=>$activePackaging,'q'=>$search])) ?>">All categories <strong><?= $categoryCounts['all'] ?></strong></a>
            <?php foreach ($categories as $category): ?>
                <a class="platform-tab <?= $activeCategory === $category->value ? 'is-active' : '' ?>" href="/?<?= $escape(http_build_query(['route'=>'merchandise','collection'=>$activeCollection,'category'=>$category->value,'packaging'=>$activePackaging,'q'=>$search])) ?>"><?= $escape($category->label()) ?><strong><?= $categoryCounts[$category->value] ?? 0 ?></strong></a>
            <?php endforeach; ?>
        </nav>
        <nav class="platform-tabs" aria-label="Merchandise packaging">
            <a class="platform-tab <?= $activePackaging === 'all' ? 'is-active' : '' ?>" href="/?<?= $escape(http_build_query(['route'=>'merchandise','collection'=>$activeCollection,'category'=>$activeCategory,'packaging'=>'all','q'=>$search])) ?>">All packaging <strong><?= $packagingCounts['all'] ?></strong></a>
            <?php foreach ($packagingOptions as $packaging): ?>
                <a class="platform-tab <?= $activePackaging === $packaging->value ? 'is-active' : '' ?>" href="/?<?= $escape(http_build_query(['route'=>'merchandise','collection'=>$activeCollection,'category'=>$activeCategory,'packaging'=>$packaging->value,'q'=>$search])) ?>"><?= $escape($packaging->label()) ?><strong><?= $packagingCounts[$packaging->value] ?? 0 ?></strong></a>
            <?php endforeach; ?>
        </nav>
    </section>

    <main class="layout merchandise-layout">
        <section class="panel form-panel">
            <div class="panel-heading"><div><p class="eyebrow"><?= $form['id'] === '' ? 'New collectible' : 'Update collectible' ?></p><h2><?= $form['id'] === '' ? 'Add merchandise' : 'Edit merchandise' ?></h2></div><?php if ($form['id'] !== ''): ?><a class="text-link" href="/?route=merchandise">Cancel</a><?php endif; ?></div>
            <?php if ($errors !== []): ?><div class="alert alert-error"><?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <?php if ($saved): ?><div class="alert alert-success">Merchandise saved.</div><?php endif; ?>
            <form class="game-form" method="post" action="/?route=merchandise">
                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="id" value="<?= $escape($form['id']) ?>">
                <label><span>Item name</span><input name="name" value="<?= $escape($form['name']) ?>" required maxlength="150" placeholder="e.g. Sonic 30th Anniversary Statue"></label>
                <label><span>Franchise</span><input name="franchise" value="<?= $escape($form['franchise']) ?>" maxlength="100" placeholder="e.g. Sonic the Hedgehog"></label>
                <div class="form-row"><label><span>Category</span><select name="category"><?php foreach ($categories as $category): ?><option value="<?= $category->value ?>" <?= $form['category'] === $category->value ? 'selected' : '' ?>><?= $escape($category->label()) ?></option><?php endforeach; ?></select></label><label><span>Packaging</span><select name="packaging"><?php foreach ($packagingOptions as $packaging): ?><option value="<?= $packaging->value ?>" <?= $form['packaging'] === $packaging->value ? 'selected' : '' ?>><?= $escape($packaging->label()) ?></option><?php endforeach; ?></select></label><label><span>Collection</span><select name="collection_type"><?php foreach ($collectionTypes as $type): ?><option value="<?= $type->value ?>" <?= $form['collection_type'] === $type->value ? 'selected' : '' ?>><?= ucfirst($type->value) ?></option><?php endforeach; ?></select></label></div>
                <label><span>Quantity</span><input type="number" name="quantity" value="<?= $escape($form['quantity']) ?>" min="1" max="999" required></label>
                <label><span>Notes</span><textarea name="notes" rows="4" maxlength="1000" placeholder="Edition, condition, shelf location…"><?= $escape($form['notes']) ?></textarea></label>
                <button class="primary-button" type="submit"><?= $form['id'] === '' ? 'Add to collection' : 'Save changes' ?></button>
            </form>
        </section>
        <section class="panel collection-panel">
            <div class="panel-heading"><div><p class="eyebrow">Display cabinet</p><h2><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></h2></div></div>
            <?php if ($items === []): ?><div class="empty-state"><span>＋</span><h3>No merchandise here yet</h3><p>Add a collectible or adjust the filters.</p></div><?php else: ?><div class="merchandise-grid">
                <?php foreach ($items as $item): ?><article class="merchandise-card">
                    <div class="merchandise-icon" aria-hidden="true"><?= match ($item->category()->value) {'action-figure'=>'⚔','statue'=>'♜','pop-vinyl'=>'●','lego'=>'▦',default=>'◆'} ?></div>
                    <div class="game-card-topline"><div class="card-badges"><span class="collection-badge collection-<?= $item->collectionType()->value ?>"><?= ucfirst($item->collectionType()->value) ?></span><span class="status"><?= $escape($item->category()->label()) ?></span><span class="status"><?= $escape($item->packaging()->label()) ?></span></div><a class="text-link" href="/?route=merchandise&amp;edit=<?= $item->id() ?>">Edit</a></div>
                    <h3><?= $escape($item->name()) ?></h3><p class="platform"><?= $escape($item->franchise() !== '' ? $item->franchise() : 'No franchise specified') ?></p>
                    <div class="merchandise-meta"><span>Quantity</span><strong><?= $item->quantity() ?></strong></div>
                    <?php if ($item->notes() !== ''): ?><p class="merchandise-notes"><?= nl2br($escape($item->notes())) ?></p><?php endif; ?>
                </article><?php endforeach; ?>
            </div><?php endif; ?>
        </section>
    </main>
</body>
</html>
