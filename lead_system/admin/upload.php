<?php
require_once __DIR__ . '/../functions.php';
require_role('admin');

$db = db_connect();
$err = $msg = '';

$agents = [];
$res = $db->query("SELECT id, username, full_name FROM users WHERE role='agent' ORDER BY username");
while ($r = $res->fetch_assoc()) $agents[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? null)) {
        $err = 'Invalid CSRF token.';
    } else {
        if (empty($_POST['agent_id'])) {
            $err = 'Select an agent.';
        } else {
            $agent_id = (int)$_POST['agent_id'];
            if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
                $err = 'File upload error.';
            } else {
                $file = $_FILES['csv'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    $err = 'Only CSV files allowed.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $err = 'Max 5MB.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $dest = $uploadDir . '/upload_' . time() . '_' . bin2hex(random_bytes(6)) . '.csv';
                    if (!move_uploaded_file($file['tmp_name'], $dest)) {
                        $err = 'Failed to save uploaded file.';
                    } else {
                        // Save CSV record
                        $admin_id = $_SESSION['user_id'];
                        $ins_csv = $db->prepare("INSERT INTO uploaded_csvs (agent_id, file_path, uploaded_by) VALUES (?, ?, ?)");
                        $ins_csv->bind_param('isi', $agent_id, $dest, $admin_id);
                        $ins_csv->execute();
                        $csv_id = $ins_csv->insert_id;
                        $ins_csv->close();

                        // Parse CSV
                        if (($handle = fopen($dest, 'r')) !== false) {
                            $row = 0;
                            $today = date('Y-m-d');
                            $ins = $db->prepare("INSERT INTO leads 
                                (agent_id, company_name, description, status, notes, lead_date, csv_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");

                            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                                $row++;
                                // Skip empty lines
                                $allEmpty = true;
                                foreach ($data as $c) if (trim($c) !== '') { $allEmpty = false; break; }
                                if ($allEmpty) continue;

                                if ($row === 1) {
                                    $lower = strtolower(implode(',', $data));
                                    if (strpos($lower, 'company') !== false || strpos($lower, 'name') !== false) {
                                        continue; // skip header
                                    }
                                }

                                $company = trim($data[0] ?? '');
                                $desc = trim($data[1] ?? '');
                                $status = ucfirst(strtolower(trim($data[2] ?? 'Other')));
                                $notes = trim($data[3] ?? '');
                                $lead_date = trim($data[4] ?? $today);
                                if ($company === '') continue;
                                if (!in_array($status, ['Good','Bad','Other'])) $status = 'Other';
                                $ins->bind_param('isssssi', $agent_id, $company, $desc, $status, $notes, $lead_date, $csv_id);
                                $ins->execute();
                            }
                            $ins->close();
                            fclose($handle);
                            $msg = 'CSV processed and assigned to agent. All leads linked to uploaded CSV.';
                        } else {
                            $err = 'Failed to read uploaded CSV.';
                        }
                    }
                }
            }
        }
    }
}

$db->close();
require __DIR__ . '/../header.php';
$csrf = generate_csrf_token();
?>
<div class="card mb-3">
  <div class="card-body">
    <h4>Upload Leads (CSV)</h4>
    <?php if ($err): ?><div class="alert alert-danger"><?= esc($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= esc($msg) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
      <div class="mb-3">
        <label class="form-label">Agent</label>
        <select name="agent_id" class="form-select" required>
          <option value="">-- select agent --</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= esc((string)$a['id']) ?>"><?= esc($a['username'] . ' — ' . $a['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">CSV file</label>
        <input type="file" name="csv" accept=".csv" class="form-control" required>
        <div class="form-text">
            CSV columns (header optional): company_name, description, status, notes, lead_date. Max 5MB.
        </div>
      </div>
      <button class="btn btn-primary">Upload and Assign</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
