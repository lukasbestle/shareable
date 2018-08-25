<?= $app->snippet('snippets/header') ?>

<header>
  <h1>Publish "<?= $file->getFilename() ?>"</h1>
  <a href="<?= url('_admin/inbox') ?>" class="header__back">← Back</a>
  <span class="header__user" title="Current user"><?= $app->user()->username() ?></span>
</header>

<?php if (isset($error)): ?>
<p class="error">
  <span>Error:</span>
  <?= $error ?>
</p>
<?php endif ?>

<form action="<?= url('_admin/inbox/' . urlencode($file->getFilename())) ?>" method="POST">
  <label for="created">Created <span>defaults to "now"</span></label>
  <input type="text" name="created" value="<?= get('created') ?>">

  <label for="expires">Expires <span>defaults to "never" if empty</span></label>
  <input type="text" name="expires" value="<?= get('expires') ?? '+ 1 year' ?>">

  <label for="id">ID <span>defaults to a random ID</span></label>
  <input type="text" name="id" value="<?= get('id') ?>">

  <label for="timeout">Timeout <span>defaults to "none" if empty</span></label>
  <input type="text" name="timeout" value="<?= get('timeout') ?? '+ 1 month' ?>">

  <hr>

  <input type="hidden" name="response" value="redirect">
  <input type="submit" value="Publish">
</form>

<?= $app->snippet('snippets/footer') ?>
