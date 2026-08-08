<?php

declare(strict_types=1);

/** Renders shared catalogue fields and owner-only collection records. */

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$backLink = $subject['type']->value === 'game' ? '/?route=game&id='.$subject['id'] : '/?route=merchandise';
$price = $metadata->purchasePricePence() === null ? '' : number_format($metadata->purchasePricePence() / 100, 2, '.', '');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $escape($subject['name']) ?> Details — ArchiveXP</title><link rel="stylesheet" href="/assets/app.css"></head>
<body>
<header class="site-header details-header"><div><a class="back-link" href="<?= $escape($backLink) ?>">← Back</a><p class="eyebrow">Collection record</p><h1><?= $escape($subject['name']) ?></h1><p class="lede"><?= $escape($subject['subtitle']) ?> · <?= $escape($subject['type']->label()) ?></p></div><div class="summary"><strong><?= $metadata->condition()->label() ?></strong><span>condition</span></div></header>
<div class="account-bar"><div class="account-identity"><span class="account-avatar"><?= strtoupper(substr($currentUser->displayName(),0,1)) ?></span><span><small>Signed in as</small><strong><?= $escape($currentUser->displayName()) ?></strong></span></div><a class="account-button" href="/?route=account">My Account</a><form method="post" action="/?route=logout"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><button class="logout-button" type="submit">Sign out</button></form></div>
<?php if ($errors !== []): ?><div class="journal-alert alert alert-error"><?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?></div><?php endif; ?>
<?php if ($saved): ?><div class="journal-alert alert alert-success">Collection details saved.</div><?php endif; ?>
<main class="details-layout">
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">Catalogue</p><h2>Organise and connect</h2></div></div>
<form class="game-form" method="post" action="/?route=collection-details&amp;type=<?= $subject['type']->value ?>&amp;id=<?= $subject['id'] ?>">
<input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="type" value="<?= $subject['type']->value ?>"><input type="hidden" name="item_id" value="<?= $subject['id'] ?>">
<label><span>Franchise</span><input name="franchise" maxlength="255" value="<?= $escape($metadata->franchise()) ?>" placeholder="e.g. Sonic the Hedgehog"></label>
<label><span>Characters</span><input name="characters" maxlength="255" value="<?= $escape($metadata->characters()) ?>" placeholder="e.g. Sonic, Tails, Amy"></label>
<div class="form-row form-row-three"><label><span>Storage location</span><input name="location" maxlength="255" value="<?= $escape($metadata->location()) ?>" placeholder="e.g. Games room · Cabinet A · Shelf 2"></label><label><span>Condition</span><select name="condition"><?php foreach ($conditions as $condition): ?><option value="<?= $condition->value ?>" <?= $metadata->condition() === $condition ? 'selected' : '' ?>><?= $escape($condition->label()) ?></option><?php endforeach; ?></select></label><label><span>Packaging</span><select name="packaging"><?php foreach ($packagingOptions as $packaging): ?><option value="<?= $packaging->value ?>" <?= $metadata->packaging() === $packaging ? 'selected' : '' ?>><?= $escape($packaging->label()) ?></option><?php endforeach; ?></select></label></div>
<div class="private-section"><p class="eyebrow">Private ownership record</p><p class="field-help details-help">These fields are visible only inside your signed-in account and are excluded from shared collection views.</p>
<div class="form-row form-row-three"><label><span>Purchase price</span><input name="purchase_price" inputmode="decimal" value="<?= $escape($price) ?>" placeholder="39.99"></label><label><span>Currency</span><input name="currency" maxlength="3" value="<?= $escape($metadata->currency()) ?>"></label><label><span>Purchased on</span><input type="date" name="purchased_on" value="<?= $metadata->purchasedOn()?->format('Y-m-d') ?? '' ?>"></label></div>
<label><span>Retailer or seller</span><input name="retailer" maxlength="255" value="<?= $escape($metadata->retailer()) ?>" placeholder="e.g. GAME, eBay, private seller"></label>
<div class="form-row"><label><span>Serial number</span><input name="serial_number" maxlength="255" value="<?= $escape($metadata->serialNumber()) ?>"></label><label><span>Receipt reference</span><input name="receipt_reference" maxlength="255" value="<?= $escape($metadata->receiptReference()) ?>" placeholder="Filename, order number, or storage reference"></label></div>
<label><span>Private notes</span><textarea name="private_notes" rows="5" maxlength="4000" placeholder="Insurance notes, provenance, defects, or identifying marks…"><?= $escape($metadata->privateNotes()) ?></textarea></label></div>
<button class="primary-button" type="submit">Save collection details</button></form></section>
<aside class="panel related-panel"><div class="panel-heading"><div><p class="eyebrow">Connected collection</p><h2><?= $metadata->franchise() === '' ? 'Add a franchise' : $escape($metadata->franchise()) ?></h2></div></div>
<?php if ($metadata->franchise() === ''): ?><div class="empty-state"><span>◇</span><h3>No franchise yet</h3><p>Add a franchise to connect related games and merchandise.</p></div>
<?php elseif ($related['games'] === [] && $related['merchandise'] === []): ?><div class="empty-state"><span>◇</span><h3>No related records yet</h3><p>Other items with this franchise will appear here.</p></div>
<?php else: ?><div class="related-list"><?php foreach ($related['games'] as $game): ?><a href="/?route=collection-details&amp;type=game&amp;id=<?= $game->id() ?>"><span>Game</span><strong><?= $escape($game->title()) ?></strong><small><?= $escape($game->platform()) ?></small></a><?php endforeach; ?><?php foreach ($related['merchandise'] as $item): ?><a href="/?route=collection-details&amp;type=merchandise&amp;id=<?= $item->id() ?>"><span>Merchandise</span><strong><?= $escape($item->name()) ?></strong><small><?= $escape($item->category()->label()) ?></small></a><?php endforeach; ?></div><?php endif; ?></aside>
</main></body></html>
