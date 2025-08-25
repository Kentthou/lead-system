<?php
// admin/agents.php
require_once __DIR__ . '/../functions.php';
require_role('admin');

$db = db_connect();
$err = $msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $err = 'Invalid CSRF token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full = trim($_POST['full_name'] ?? '');
        if ($username === '' || $password === '' || $full === '') {
            $err = 'All fields required.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'agent')");
            $ins->bind_param('sss', $username, $hash, $full);
            if ($ins->execute()) {
                $msg = 'Agent created.';
            } else {
                $err = 'Error: ' . $db->error;
            }
            $ins->close();
        }
    }
}

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
      <thead><tr><th>ID</th><th>Username</th><th>Full name</th><th>Role</th><th>Created</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= esc((string)$u['id']) ?></td>
            <td><?= esc($u['username']) ?></td>
            <td><?= esc($u['full_name']) ?></td>
            <td><?= esc($u['role']) ?></td>
            <td><?= esc($u['created_at']) ?></td>
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
