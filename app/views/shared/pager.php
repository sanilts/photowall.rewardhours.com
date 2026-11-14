<?php /** @var int $pages @var int $page */ if ($pages > 1): ?>
<nav class="pager" aria-label="Pages">
  <?php if ($page > 1): ?><a class="btn btn-quiet" href="<?= e(query_with(['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
  <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-quiet" href="<?= e(query_with(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
