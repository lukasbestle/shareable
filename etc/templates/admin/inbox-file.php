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
  <label for="created">Created <small>defaults to "now"</small></label>
  <input type="text" id="created" name="created" value="<?= get('created') ?>">

  <label for="expires">Expires <small>defaults to "never" if empty</small></label>
  <input type="text" id="expires" name="expires" value="<?= get('expires') ?? '+ 1 year' ?>">

  <label for="id">ID <small>defaults to a random ID</small></label>
  <input type="text" id="id" name="id" value="<?= get('id') ?>">

  <label for="timeout">Timeout <small>defaults to "none" if empty</small></label>
  <input type="text" id="timeout" name="timeout" value="<?= get('timeout') ?? '+ 1 month' ?>">

  <div class="input-wrapper">
    <input type="checkbox" id="timeout-immediately" name="timeout-immediately" value="true"<?php if (get('timeout-immediately') === 'true'): ?> checked<?php endif ?>>
    <label for="timeout-immediately" class="label--checkbox">Start timeout immediately</label>
  </div>

  <hr>

  <input type="hidden" name="response" value="redirect">
  <input type="submit" value="Publish">
</form>

<?= $app->snippet('snippets/footer') ?>
