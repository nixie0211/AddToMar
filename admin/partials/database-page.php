<?php

declare(strict_types=1);

$dbTables = admin_database_tables();
$dbTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_GET['table'] ?? ''));
$dbPage = max(1, (int) ($_GET['p'] ?? 1));
$dbView = $dbTable !== '' ? admin_database_table_view($dbTable, $dbPage) : ['table' => '', 'columns' => [], 'rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
?>
<section class="admin-card table-card db-browser">
  <div class="table-head">
    <div>
      <h2>Database</h2>
      <p class="table-sub">Read-only view of the live MySQL tables used by AddToMar. Passwords and file contents are hidden.</p>
    </div>
  </div>

  <?php if ($dbTables === []): ?>
  <p class="ov-empty">Could not load tables from the database.</p>
  <?php else: ?>
  <div class="db-layout">
    <aside class="db-tables" aria-label="Database tables">
      <h3>Tables</h3>
      <ul>
        <?php foreach ($dbTables as $table): ?>
        <li>
          <a class="<?= $dbTable === $table['name'] ? 'is-active' : '' ?>" href="<?= htmlspecialchars(admin_url('?page=database&table=' . urlencode($table['name'])), ENT_QUOTES, 'UTF-8') ?>">
            <strong><?= htmlspecialchars($table['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= number_format((int) $table['rows']) ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <div class="db-rows">
      <?php if ($dbTable === ''): ?>
      <p class="ov-empty">Choose a table on the left to see its rows. Start with <strong>users</strong> or <strong>pharmacies</strong>.</p>
      <?php elseif ($dbView['table'] === ''): ?>
      <p class="ov-empty">That table was not found.</p>
      <?php else: ?>
      <div class="db-rows-head">
        <h3><?= htmlspecialchars($dbView['table'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= number_format((int) $dbView['total']) ?> row<?= (int) $dbView['total'] === 1 ? '' : 's' ?></p>
      </div>
      <?php if ($dbView['rows'] === []): ?>
      <p class="ov-empty">This table is empty.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <?php foreach ($dbView['columns'] as $column): ?>
              <th><?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dbView['rows'] as $row): ?>
            <tr>
              <?php foreach ($dbView['columns'] as $column): ?>
              <td><?= htmlspecialchars(admin_database_cell($row[$column] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ((int) $dbView['pages'] > 1): ?>
      <nav class="mini-pager" aria-label="Database pages">
        <a class="<?= (int) $dbView['page'] <= 1 ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars(admin_url('?page=database&table=' . urlencode($dbView['table']) . '&p=' . max(1, (int) $dbView['page'] - 1)), ENT_QUOTES, 'UTF-8') ?>">Prev</a>
        <span class="mini-pager-page"><?= (int) $dbView['page'] ?> / <?= (int) $dbView['pages'] ?></span>
        <a class="<?= (int) $dbView['page'] >= (int) $dbView['pages'] ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars(admin_url('?page=database&table=' . urlencode($dbView['table']) . '&p=' . min((int) $dbView['pages'], (int) $dbView['page'] + 1)), ENT_QUOTES, 'UTF-8') ?>">Next</a>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</section>
