<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    $role = $_SESSION['role'];
    header('Location: ' . ($role === 'admin' ? BASE_URL . '/admin/index.php' : BASE_URL . '/agent/index.php'));
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $err = 'Username and password required.';
    } else {
        $db = db_connect();
        $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $uname, $hash, $full, $role);
            $stmt->fetch();
            if (password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $uname;
                $_SESSION['full_name'] = $full;
                $_SESSION['role'] = $role;
                generate_csrf_token();
                header('Location: ' . ($role === 'admin' ? BASE_URL . '/admin/index.php' : BASE_URL . '/agent/index.php'));
                exit;
            } else {
                $err = 'Invalid credentials.';
            }
        } else {
            $err = 'Invalid credentials.';
        }
        $stmt->close();
        $db->close();
    }
}

require __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title mb-3">Login</h3>
        <?php if ($err): ?>
            <div class="alert alert-danger"><?= esc($err) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required>
          </div>
          <button class="btn btn-primary">Login</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
