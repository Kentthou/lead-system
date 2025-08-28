<?php
// admin/agents.php
require_once __DIR__ . '/../functions.php';
require_role('admin');

session_start();

$db = db_connect();
$err = $msg = '';

// Pick up flash messages from last redirect
if (!empty($_SESSION['flash_err'])) {
    $err = $_SESSION['flash_err'];
    unset($_SESSION['flash_err']);
}
if (!empty($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $_SESSION['flash_err'] = 'Invalid CSRF token.';
    } else {
        if (isset($_POST['delete_id'])) {
            // --- DELETE AGENT ---
            $delete_id = (int)$_POST['delete_id'];
            if ($delete_id > 0) {
                $del = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'agent'");
                $del->bind_param('i', $delete_id);
                if ($del->execute() && $del->affected_rows > 0) {
                    $_SESSION['flash_msg'] = "Agent deleted.";
                } else {
                    $_SESSION['flash_err'] = "Failed to delete agent.";
                }
                $del->close();
            } else {
                $_SESSION['flash_err'] = "Invalid agent ID.";
            }
        } else {
            // --- CREATE AGENT ---
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $full = trim($_POST['full_name'] ?? '');
            if ($username === '' || $password === '' || $full === '') {
                $_SESSION['flash_err'] = 'All fields required.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'agent')");
                $ins->bind_param('sss', $username, $hash, $full);
                if ($ins->execute()) {
                    $_SESSION['flash_msg'] = 'Agent created.';
                } else {
                    $_SESSION['flash_err'] = 'Error: ' . $db->error;
                }
                $ins->close();
            }
        }
    }

    // Redirect to avoid resubmission on refresh
    header("Location: agents.php");
    exit;
}

// Fetch agents
$res = $db->query("SELECT id, username, full_name, role, created_at FROM users WHERE role='agent' ORDER BY id DESC");
$users = [];
while ($row = $res->fetch_assoc()) $users[] = $row;
$db->close();

require __DIR__ . '/../header.php';
$csrf = generate_csrf_token();
?>
<div class="card mb-3">
  <div class="card-body">
    <h4>Agents</h4>
    <?php if ($err): ?><div class="alert alert-danger"><?= esc($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= esc($msg) ?></div><?php endif; ?>

    <table class="table table-sm">
      <thead>
        <tr>
          <th>ID</th><th>Username</th><th>Full name</th><th>Role</th><th>Created</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= esc((string)$u['id']) ?></td>
            <td><?= esc($u['username']) ?></td>
            <td><?= esc($u['full_name']) ?></td>
            <td><?= esc($u['role']) ?></td>
            <td><?= esc($u['created_at']) ?></td>
            <td>
              <form method="post" style="display:inline;" 
                    onsubmit="return confirm('Delete this agent? This will also remove all their leads and uploads.');">
                <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
                <input type="hidden" name="delete_id" value="<?= esc((string)$u['id']) ?>">
                <button class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <hr>
    <h5>Create Agent</h5>
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
      <div class="col-md-4">
        <label class="form-label">Username</label>
        <input name="username" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Full name</label>
        <input name="full_name" class="form-control" required>
      </div>
      <div class="col-12">
        <button class="btn btn-success">Create Agent</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
