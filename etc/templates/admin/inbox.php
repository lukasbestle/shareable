<?= $app->snippet('snippets/header') ?>

<header>
  <h1>Inbox</h1>
  <a href="<?= url('_admin') ?>" class="header__back">← Back</a>
  <span class="header__user" title="Current user"><?= $app->user()->username() ?></span>
</header>

<h2>Available files</h2>

<?php if ($app->inbox()->count() > 0): ?>
<ul>
  <?php foreach ($app->inbox() as $file): ?>
  <li>
    <?php if ($app->user()->hasPermission('publish')): ?>
    <a class="icon" href="<?= url('_admin/inbox/' . urlencode($file->getFilename()) . '/delete') ?>">🗑</a>
    <a href="<?= url('_admin/inbox/' . urlencode($file->getFilename())) ?>">
      <?= $file->getFilename() ?> (<?= Kirby\Toolkit\F::niceSize($file->getSize()) ?>)
    </a>
    <?php else: ?>
    <?= $file->getFilename() ?> (<?= Kirby\Toolkit\F::niceSize($file->getSize()) ?>)
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
<?php else: ?>
<p><em>Currently no available files</em></p>
<?php endif ?>

<?php if ($app->user()->hasPermission('upload')): ?>
<h2>Upload to the inbox</h2>

<?php if (isset($error)): ?>
<p class="error">
  <span>Error:</span>
  <?= $error ?>
</p>
<?php endif ?>

<form enctype="multipart/form-data" action="<?= url('_admin/inbox') ?>" method="POST">
  <input type="file" name="files[]" multiple>

  <input type="hidden" name="response" value="redirect">
  <input type="submit" value="Upload">
</form>
<?php endif ?>

<?= $app->snippet('snippets/footer') ?>
