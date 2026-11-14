<?php
/** Key facts and the complete metadata of one photo. @var array $photo @var array $meta */
$facts = [
    'Taken with' => $photo['device_model'],
    'Lens' => $photo['lens_model'],
    'iOS version' => $photo['software'],
    'Taken on' => nice_date($photo['taken_at'], 'j M Y, H:i:s'),
    'Size' => $photo['width'] . ' x ' . $photo['height'] . ' (' . number_format($photo['width'] * $photo['height'] / 1e6, 1) . ' MP)',
    'File' => strtoupper($photo['ext']) . ', ' . human_bytes((int) $photo['bytes']),
    'Location' => $photo['gps_lat'] !== null ? $photo['gps_lat'] . ', ' . $photo['gps_lng'] : 'Not in the file',
    'Uploaded' => nice_date($photo['created_at']),
    'SHA-256' => $photo['sha256'],
];
?>
<dl class="facts">
  <?php foreach ($facts as $k => $v): ?>
    <div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div>
  <?php endforeach; ?>
</dl>
<details class="meta-all">
  <summary>All information stored in the photo file (<?= count($meta) ?> items)</summary>
  <table class="table table-small">
    <tbody>
    <?php foreach ($meta as $k => $v): ?>
      <tr><th scope="row"><?= e($k) ?></th><td><?= e(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</details>
