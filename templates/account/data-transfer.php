<?php

declare(strict_types=1);

/** Renders private collection CSV import, export, validation, and history tools. */

$escape=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
$themeStyle="--accent:{$user->themeAccent()};--bg:{$user->themeBackground()};--panel:{$user->themePanel()};--text:{$user->themeText()}";
$ready=count(array_filter($preview,static fn(array $row):bool=>$row['status']==='ready'));
$duplicates=count(array_filter($preview,static fn(array $row):bool=>$row['status']==='duplicate'));
$invalid=count(array_filter($preview,static fn(array $row):bool=>$row['status']==='error'));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Import and export — ArchiveXP</title><link rel="stylesheet" href="/assets/app.css"></head>
<body class="theme-<?= $escape($user->themePreset()) ?> density-<?= $escape($user->layoutDensity()) ?>" style="<?= $escape($themeStyle) ?>">
<header class="site-header account-header"><div><a class="back-link" href="/">← Back to collection</a><p class="eyebrow">Player settings</p><h1>Import &amp; export</h1><p class="lede">Move an existing collection into ArchiveXP without losing control of your data.</p></div></header>
<nav class="section-switcher settings-switcher"><a href="/?route=account">My Account</a><a href="/?route=appearance">Appearance</a><a class="is-active" href="/?route=data-transfer">Data tools</a></nav>
<main class="appearance-layout data-transfer-layout">
<?php if($errors!==[]):?><div class="alert alert-error appearance-message" role="alert"><?php foreach($errors as $error):?><p><?= $escape($error) ?></p><?php endforeach;?></div><?php endif;?>
<?php if($summary!==null):?><section class="panel transfer-summary"><div><p class="eyebrow">Import complete</p><h2>Your collection has been updated</h2></div><div class="summary-grid"><span><strong><?= $summary['added'] ?></strong>Added</span><span><strong><?= $summary['skipped'] ?></strong>Skipped</span><span><strong><?= $summary['failed'] ?></strong>Failed</span></div></section><?php endif;?>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">Collection type</p><h2>Choose what to transfer</h2></div><p class="section-description">Games and merchandise use separate templates so each row can be checked accurately.</p></div>
<nav class="section-switcher transfer-switcher"><a class="<?= $type==='games'?'is-active':'' ?>" href="/?route=data-transfer&amp;type=games">Games</a><a class="<?= $type==='merchandise'?'is-active':'' ?>" href="/?route=data-transfer&amp;type=merchandise">Merchandise</a></nav>
<div class="export-row"><div><h3>Export your <?= $escape($type) ?></h3><p class="field-help">Downloads all collection fields plus your private prices, receipts, serial numbers, and notes.</p></div><a class="filter-button export-button" href="/?route=data-transfer&amp;type=<?= $escape($type) ?>&amp;action=export">Download CSV</a></div></section>

<?php if($stage==='upload'||$stage==='complete'):?>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">Step 1</p><h2>Upload a CSV</h2></div><p class="section-description">Maximum 2 MB and 1,000 rows. Nothing is saved until after preview.</p></div>
<form class="game-form transfer-upload" method="post" action="/?route=data-transfer&amp;type=<?= $escape($type) ?>" enctype="multipart/form-data"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="collection_type" value="<?= $escape($type) ?>"><label><span>CSV file</span><input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required></label><button class="primary-button" type="submit">Read columns</button></form></section>
<?php elseif($stage==='map'&&$upload!==null):?>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">Step 2</p><h2>Map your columns</h2></div><p class="section-description">Match ArchiveXP fields to the headings found in your spreadsheet.</p></div>
<form class="game-form" method="post" action="/?route=data-transfer&amp;type=<?= $escape($type) ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="preview"><input type="hidden" name="collection_type" value="<?= $escape($type) ?>"><div class="mapping-grid">
<?php foreach($fields as $field=>$label):?><label><span><?= $escape($label) ?><?= in_array($field,$required,true)?' *':'' ?></span><select name="mapping[<?= $escape($field) ?>]"><option value="">Not imported</option><?php foreach($upload['headers'] as $index=>$header):?><option value="<?= $index ?>" <?= ($mapping[$field]??'')===(string)$index?'selected':'' ?>><?= $escape($header!==''?$header:'Column '.($index+1)) ?></option><?php endforeach;?></select></label><?php endforeach;?></div><div class="appearance-actions"><button class="primary-button" type="submit">Preview <?= count($upload['rows']) ?> rows</button><button class="danger-button" type="submit" name="action" value="cancel">Cancel</button></div></form></section>
<?php elseif($stage==='preview'):?>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">Step 3</p><h2>Review before importing</h2></div><p class="section-description">Invalid and duplicate records will be skipped automatically.</p></div>
<div class="summary-grid preview-summary"><span><strong><?= $ready ?></strong>Ready</span><span><strong><?= $duplicates ?></strong>Duplicates</span><span><strong><?= $invalid ?></strong>Errors</span></div>
<div class="import-preview"><table><thead><tr><th>CSV row</th><th>Record</th><th>Result</th></tr></thead><tbody><?php foreach($preview as $row):?><tr class="import-row-<?= $escape($row['status']) ?>"><td><?= $row['line'] ?></td><td><strong><?= $escape($row['data'][$type==='games'?'title':'name']??'Untitled') ?></strong><small><?= $escape($row['data'][$type==='games'?'platform':'category']??'') ?></small></td><td><span class="import-status"><?= $escape($row['status']) ?></span><small><?= $escape($row['message']) ?></small></td></tr><?php endforeach;?></tbody></table></div>
<form class="appearance-actions import-actions" method="post" action="/?route=data-transfer&amp;type=<?= $escape($type) ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="collection_type" value="<?= $escape($type) ?>"><button class="primary-button" type="submit" name="action" value="confirm" <?= $ready===0?'disabled':'' ?>>Import <?= $ready ?> ready rows</button><button class="danger-button" type="submit" name="action" value="cancel">Cancel</button></form></section>
<?php endif;?>

<section class="panel"><div class="panel-heading"><div><p class="eyebrow">Private activity</p><h2>Recent imports</h2></div></div><?php if($history===[]):?><p class="lede">No CSV imports have been completed yet.</p><?php else:?><div class="history-list"><?php foreach($history as $entry):?><article><div><strong><?= $escape((string)$entry['filename']) ?></strong><small><?= $escape(ucfirst((string)$entry['collection_type'])) ?> · <?= $escape((string)$entry['created_at']) ?></small></div><span><?= (int)$entry['added'] ?> added · <?= (int)$entry['skipped'] ?> skipped · <?= (int)$entry['failed'] ?> failed</span></article><?php endforeach;?></div><?php endif;?></section>
</main></body></html>
