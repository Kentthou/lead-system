<?php
require_once __DIR__ . '/../functions.php';
require_role('agent');

$db = db_connect();
$me = current_user();
$agent_id = (int)$me['id'];
$selected_date = $_GET['date'] ?? date('Y-m-d');

$leads = [];
$stmt = $db->prepare("SELECT id, company_name, description, status, notes, lead_date, updated_at FROM leads WHERE agent_id = ? AND lead_date = ? ORDER BY id ASC");
$stmt->bind_param('is', $agent_id, $selected_date);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $leads[] = $r;
$stmt->close();
$db->close();

require __DIR__ . '/../header.php';
$csrf = generate_csrf_token();
?>
<div class="card mb-3">
  <div class="card-body">
    <h4>Your Leads — <?= esc($selected_date) ?></h4>

    <form class="mb-3">
      <label class="form-label">Date</label>
      <input type="date" name="date" value="<?= esc($selected_date) ?>" class="form-control" onchange="this.form.submit()">
    </form>

    <?php if (empty($leads)): ?>
      <div class="alert alert-info">No leads for this date.</div>
    <?php else: ?>
      <table class="table table-sm">
        <thead><tr><th>#</th><th>Company</th><th>Description</th><th>Status</th><th>Notes</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($leads as $l): ?>
            <tr>
              <td><?= esc((string)$l['id']) ?></td>
              <td><?= esc($l['company_name']) ?></td>
              <td><?= esc($l['description']) ?></td>
              <td><?= esc($l['status']) ?></td>
              <td><?= esc($l['notes']) ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-<?= esc((string)$l['id']) ?>">Edit</button>
              </td>
            </tr>
            <tr class="collapse-row">
              <td colspan="6">
                <div class="collapse" id="edit-<?= esc((string)$l['id']) ?>">
                  <div class="card card-body">
                    <form method="post" action="<?= BASE_URL ?>/agent/update_lead.php">
                      <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
                      <input type="hidden" name="lead_id" value="<?= esc((string)$l['id']) ?>">
                      <div class="row g-2">
                        <div class="col-md-3">
                          <label class="form-label">Status</label>
                          <select name="status" class="form-select">
                            <option value="Other" <?= $l['status']==='Other' ? 'selected':'' ?>>Other</option>
                            <option value="Good" <?= $l['status']==='Good' ? 'selected':'' ?>>Good</option>
                            <option value="Bad" <?= $l['status']==='Bad' ? 'selected':'' ?>>Bad</option>
                          </select>
                        </div>
                        <div class="col-md-7">
                          <label class="form-label">Notes</label>
                          <input name="notes" class="form-control" value="<?= esc($l['notes']) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                          <button class="btn btn-success">Save</button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
