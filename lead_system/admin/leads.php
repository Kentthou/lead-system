<?php
require_once __DIR__ . '/../functions.php';
require_role('admin');

$db = db_connect();
$agents = [];
$res = $db->query("SELECT id, username, full_name FROM users WHERE role='agent' ORDER BY username");
while ($r = $res->fetch_assoc()) $agents[] = $r;

$selected_agent = (int)($_GET['agent_id'] ?? 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');

$leads = [];
if ($selected_agent > 0) {
    $stmt = $db->prepare("SELECT id, company_name, description, status, notes, lead_date, created_at, updated_at FROM leads WHERE agent_id = ? AND lead_date = ? ORDER BY id ASC");
    $stmt->bind_param('is', $selected_agent, $selected_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $leads[] = $row;
    $stmt->close();
}

$db->close();
require __DIR__ . '/../header.php';
?>
<div class="card mb-3">
  <div class="card-body">
    <h4>View Leads / Monitor</h4>

    <form class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label">Agent</label>
        <select name="agent_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- select agent --</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= esc((string)$a['id']) ?>" <?= $selected_agent === (int)$a['id'] ? 'selected' : '' ?>><?= esc($a['username'] . ' — ' . $a['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="date" value="<?= esc($selected_date) ?>" class="form-control" onchange="this.form.submit()">
      </div>
    </form>

    <?php if ($selected_agent === 0): ?>
      <div class="alert alert-info">Please select an agent to view leads.</div>
    <?php else: ?>
      <h5>Leads for <?= esc($selected_date) ?></h5>
      <?php if (empty($leads)): ?>
        <div class="alert alert-secondary">No leads found for this agent/date.</div>
      <?php else: ?>
        <table class="table table-sm">
          <thead><tr><th>#</th><th>Company</th><th>Description</th><th>Status</th><th>Notes</th><th>Updated</th></tr></thead>
          <tbody>
            <?php foreach ($leads as $l): ?>
              <tr>
                <td><?= esc((string)$l['id']) ?></td>
                <td><?= esc($l['company_name']) ?></td>
                <td><?= esc($l['description']) ?></td>
                <td><?= esc($l['status']) ?></td>
                <td><?= esc($l['notes']) ?></td>
                <td><?= esc($l['updated_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
