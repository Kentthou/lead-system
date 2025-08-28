<?php
require_once __DIR__ . '/../functions.php';
require_role('admin');

require __DIR__ . '/../header.php';
?>
<div class="card mb-3">
  <div class="card-body">
    <h4>Admin Dashboard</h4>
    <p>Quick links:</p>
    <ul>
      <li><a href="<?= BASE_URL ?>/admin/agents.php">Manage Agents</a></li>
      <li><a href="<?= BASE_URL ?>/admin/upload.php">Upload Leads (CSV)</a></li>
      <li><a href="<?= BASE_URL ?>/admin/leads.php">View Leads & Monitor</a></li>
    </ul>
  </div>
</div>
<?php require __DIR__ . '/../footer.php'; ?>
