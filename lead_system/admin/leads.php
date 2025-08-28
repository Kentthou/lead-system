<?php
// admin/leads.php
require_once __DIR__ . '/../functions.php';
require_role('admin');

$db = db_connect();
$agents = [];
$res = $db->query("SELECT id, username, full_name FROM users WHERE role='agent' ORDER BY username");
while ($r = $res->fetch_assoc()) $agents[] = $r;

$selected_agent = (int)($_GET['agent_id'] ?? 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');
$mode = $_GET['mode'] ?? 'paged'; // 'paged' or 'all'
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead'])) {
    $lead_id = (int)$_POST['lead_id'];

    // Only update fields that are actually sent in the POST
    $updates = [];
    $params = [];
    $types = '';

    if (isset($_POST['company_name'])) {
        $updates[] = 'company_name = ?';
        $params[] = trim($_POST['company_name']);
        $types .= 's';
    }
    if (isset($_POST['description'])) {
        $updates[] = 'description = ?';
        $params[] = trim($_POST['description']);
        $types .= 's';
    }
    if (isset($_POST['status'])) {
        $updates[] = 'status = ?';
        $params[] = $_POST['status'];
        $types .= 's';
    }
    if (isset($_POST['notes'])) {
        $updates[] = 'notes = ?';
        $params[] = trim($_POST['notes']);
        $types .= 's';
    }

    if (!empty($updates)) {
        $sql = "UPDATE leads SET " . implode(', ', $updates) . " WHERE id = ? AND agent_id = ?";
        $stmt = $db->prepare($sql);

        $types .= 'ii';
        $params[] = $lead_id;
        $params[] = $selected_agent;

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: leads.php?agent_id={$selected_agent}&date={$selected_date}&mode={$mode}&page={$page}&updated=1");
    exit;
}

$leads = [];
$total_rows = 0;

if ($selected_agent > 0) {
    if ($mode === 'paged') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id = ? AND lead_date = ?");
        $stmt->bind_param('is', $selected_agent, $selected_date);
        $stmt->execute();
        $stmt->bind_result($total_rows);
        $stmt->fetch();
        $stmt->close();

        $offset = ($page - 1) * $per_page;
        $stmt = $db->prepare("SELECT id, number, company_name, description, status, notes, lead_date, created_at, updated_at 
                              FROM leads 
                              WHERE agent_id = ? AND lead_date = ? 
                              ORDER BY id ASC 
                              LIMIT ? OFFSET ?");
        $stmt->bind_param('isii', $selected_agent, $selected_date, $per_page, $offset);
    } else {
        $stmt = $db->prepare("SELECT id, number, company_name, description, status, notes, lead_date, created_at, updated_at 
                              FROM leads 
                              WHERE agent_id = ? AND lead_date = ? 
                              ORDER BY id ASC");
        $stmt->bind_param('is', $selected_agent, $selected_date);
    }

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
    <h4 class="mb-3">Leads Monitor</h4>

    <!-- Filter Form -->
    <form class="row g-3 align-items-end mb-4" method="get">
      <input type="hidden" name="mode" value="<?= esc($mode) ?>">

      <!-- Agent -->
      <div class="col-md-4">
        <label class="form-label fw-bold">Agent</label>
        <select name="agent_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Select Agent --</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= esc((string)$a['id']) ?>" <?= $selected_agent === (int)$a['id'] ? 'selected' : '' ?>>
              <?= esc($a['username'] . ' — ' . $a['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Date with navigation -->
      <div class="col-md-5">
        <label class="form-label fw-bold">Date</label>
        <div class="input-group">
          <a class="btn btn-outline-secondary"
             href="?agent_id=<?= $selected_agent ?>&date=<?= esc(date('Y-m-d', strtotime($selected_date . ' -1 day'))) ?>&mode=<?= esc($mode) ?>">
            ← Prev
          </a>
          <input type="date" name="date" value="<?= esc($selected_date) ?>" class="form-control"
                 onchange="this.form.submit()">
          <a class="btn btn-outline-secondary"
             href="?agent_id=<?= $selected_agent ?>&date=<?= esc(date('Y-m-d')) ?>&mode=<?= esc($mode) ?>">
            Today
          </a>
          <a class="btn btn-outline-secondary"
             href="?agent_id=<?= $selected_agent ?>&date=<?= esc(date('Y-m-d', strtotime($selected_date . ' +1 day'))) ?>&mode=<?= esc($mode) ?>">
            Next →
          </a>
        </div>
      </div>
    </form>

    <!-- Info / Results -->
    <?php if ($selected_agent === 0): ?>
      <div class="alert alert-info">Please select an agent to view leads.</div>
    <?php else: ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Leads for <span class="text-primary"><?= esc($selected_date) ?></span></h5>
        <a href="?agent_id=<?= $selected_agent ?>&date=<?= esc($selected_date) ?>&mode=<?= $mode === 'all' ? 'paged' : 'all' ?>"
           class="btn btn-sm btn-outline-dark">
          <?= $mode === 'all' ? 'Switch to Paginated' : 'Show All Leads' ?>
        </a>
      </div>

      <?php if (empty($leads)): ?>
        <div class="alert alert-secondary">No leads found for this agent/date.</div>
      <?php else: ?>
        <?php
        // We'll collect modal HTML here and print them AFTER the table to avoid placing modal <div>s inside <tbody>.
        $modals = '';
        ?>

        <table class="table table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Company</th>
              <th>Description</th>
              <th>Status</th>
              <th>Notes</th>
              <th>Updated</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leads as $l): ?>
              <?php
                $row_class = '';
                if ($l['status'] === 'Good') $row_class = 'table-success';
                elseif ($l['status'] === 'Bad') $row_class = 'table-danger';

                // Prepare modal HTML for this lead (stored for printing after the table)
                $modal_id = "editLeadModal" . (int)$l['id'];
                $modals .= '
                <div class="modal fade" id="' . esc($modal_id) . '" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <form method="post">
                        <div class="modal-header">
                          <h5 class="modal-title">Edit Lead #' . esc((string)$l['number']) . '</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <input type="hidden" name="lead_id" value="' . esc((string)$l['id']) . '">
                          <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="' . esc($l['company_name']) . '">
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">' . esc($l['description']) . '</textarea>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                ';
                foreach (['Good', 'Bad', 'N/A'] as $statusOption) {
                    $selectedAttr = ($l['status'] === $statusOption) ? ' selected' : '';
                    $modals .= '<option value="' . $statusOption . '"' . $selectedAttr . '>' . $statusOption . '</option>';
                }
                $modals .= '
                            </select>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3">' . esc($l['notes']) . '</textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" name="update_lead" class="btn btn-primary">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                ';
              ?>
              <tr class="<?= $row_class ?>">
                <td><?= esc((string)$l['number']) ?></td>
                <td><?= esc($l['company_name']) ?></td>
                <td><?= esc(mb_strimwidth($l['description'], 0, 50, '...')) ?></td>

                <!-- Inline Status -->
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="lead_id" value="<?= esc((string)$l['id']) ?>">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                      <?php foreach (['Good','Bad','N/A'] as $status): ?>
                        <option value="<?= $status ?>" <?= $l['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="update_lead" value="1">
                  </form>
                </td>

                <!-- Inline Notes -->
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="lead_id" value="<?= esc((string)$l['id']) ?>">
                    <input type="text" name="notes" class="form-control form-control-sm"
                           value="<?= esc($l['notes']) ?>" onchange="this.form.submit()">
                    <input type="hidden" name="update_lead" value="1">
                  </form>
                </td>

                <td><?= esc(date('H:i', strtotime($l['updated_at']))) ?></td>
                <td>
                  <button type="button" class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal" data-bs-target="#<?= esc($modal_id) ?>">
                    Edit
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Output all modals AFTER the table -->
        <?= $modals ?>

        <!-- Pagination -->
        <?php if ($mode === 'paged' && $total_rows > $per_page): ?>
          <?php $total_pages = ceil($total_rows / $per_page); ?>
          <nav>
            <ul class="pagination justify-content-center">
              <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link"
                     href="?agent_id=<?= $selected_agent ?>&date=<?= esc($selected_date) ?>&mode=paged&page=<?= $p ?>">
                    <?= $p ?>
                  </a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
