<?= $app->snippet('snippets/header') ?>

<header>
  <h1>Admin</h1>
  <span class="header__user"><?= $app->user()->username() ?></span>
</header>

<ul>
  <li><a href="<?= url('_admin/inbox') ?>">Inbox</a></li>
  <li><a href="<?= url('_admin/items') ?>">View items</a></li>
</ul>

<?= $app->snippet('snippets/footer') ?>
