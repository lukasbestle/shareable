<?= $app->snippet('snippets/header') ?>

<header>
  <h1>Items</h1>
  <a href="<?= url('_admin') ?>" class="header__back">← Back</a>
  <span class="header__user" title="Current user"><?= $app->user()->username() ?></span>
</header>

<?php if ($collection->count() > 0): ?>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Created</th>
      <th>Invalidity date</th>
      <th>Filename</th>
      <th>User</th>
      <th class="table__column--center">📥</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($collection as $itemName => $item): ?>
    <tr>
      <td data-label="ID"><a href="<?= url($itemName) ?>"><?= $itemName ?></a></td>
      <td data-label="Created"><?= $item->created('Y-m-d H:i') ?></td>
      <td data-label="Invalidity date"><?= $item->invalidityDate('Y-m-d H:i') ?></td>
      <td data-label="Filename"><?= basename($item->filename()) ?></td>
      <td data-label="User"><?= $item->user() ?></td>
      <td class="table__column--center" data-label="Downloads"><?= $item->downloads() ?></td>
      <td data-label="Actions"><a class="icon" href="<?= url('_admin/items/' . $itemName) ?>" title="Info as JSON">📍</a><a class="icon" href="<?= url('_admin/items/' . $itemName . '/delete') ?>" title="Delete">🗑</a></td>
    </tr>
    <?php endforeach ?>
  </tbody>
</table>

<nav class="pagination">
  <?php if ($collection->pagination()->hasNextPage()): ?>
  <a class="pagination__link" href="<?= url('_admin/items?page=' . $collection->pagination()->nextPage()) ?>" title="Next page">❮</a>
  <?php else: ?>
  <span class="pagination__inactive">❮</span>
  <?php endif ?>

  <?php if ($collection->pagination()->hasPrevPage()): ?>
  <a class="pagination__link" href="<?= url('_admin/items?page=' . $collection->pagination()->prevPage()) ?>" title="Previous page">❯</a>
  <?php else: ?>
  <span class="pagination__inactive">❯</span>
  <?php endif ?>
</nav>
<?php else: ?>
<p><em>Currently no items, <a href="<?= url('_admin/inbox') ?>">upload and publish some</a>.</em></p>
<?php endif ?>

<?= $app->snippet('snippets/footer') ?>
