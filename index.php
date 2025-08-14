<?php
// Simple PHP To-Do List (SQLite + PDO + CSRF)
// Drop this file in a PHP-enabled server. It creates data.sqlite automatically.

declare(strict_types=1);
session_start();
if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

// ---- DB Setup ----
$dbFile = __DIR__ . '/data.sqlite';
$pdo = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("
CREATE TABLE IF NOT EXISTS todos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  notes TEXT,
  is_done INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
");

// ---- Helpers ----
function require_csrf(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}
function redirect_home(): void {
    header('Location: '.$_SERVER['PHP_SELF'].'?'.http_build_query([
        'filter' => $_GET['filter'] ?? 'all',
        'q'      => $_GET['q'] ?? '',
    ]));
    exit;
}

// ---- Actions ----
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if ($action === 'add') {
        $title = trim((string)($_POST['title'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($title !== '') {
            $stmt = $pdo->prepare("INSERT INTO todos(title, notes) VALUES(:t, :n)");
            $stmt->execute([':t' => $title, ':n' => $notes]);
        }
        redirect_home();
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE todos SET is_done = 1 - is_done, updated_at = datetime('now') WHERE id = :id");
        $stmt->execute([':id' => $id]);
        redirect_home();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        redirect_home();
    }

    if ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($id && $title !== '') {
            $stmt = $pdo->prepare("UPDATE todos SET title=:t, notes=:n, updated_at=datetime('now') WHERE id=:id");
            $stmt->execute([':t'=>$title, ':n'=>$notes, ':id'=>$id]);
        }
        redirect_home();
    }
}

// ---- Query (filter + search) ----
$filter = in_array(($_GET['filter'] ?? 'all'), ['all','open','done'], true) ? $_GET['filter'] : 'all';
$q = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];

if ($filter === 'open') { $where[] = 'is_done = 0'; }
if ($filter === 'done') { $where[] = 'is_done = 1'; }
if ($q !== '') {
    $where[] = '(title LIKE :q OR notes LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$sql = 'SELECT * FROM todos' . (count($where) ? ' WHERE '.implode(' AND ', $where) : '') . ' ORDER BY is_done, created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$todos = $stmt->fetchAll();
$counts = [
    'all'  => (int)$pdo->query("SELECT COUNT(*) FROM todos")->fetchColumn(),
    'open' => (int)$pdo->query("SELECT COUNT(*) FROM todos WHERE is_done = 0")->fetchColumn(),
    'done' => (int)$pdo->query("SELECT COUNT(*) FROM todos WHERE is_done = 1")->fetchColumn(),
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PHP To-Do</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{ background:#0f172a; color:#e2e8f0; }
  .card{ background:#111827; border:1px solid #1f2937; }
  .form-control, .form-select{ background:#0b1220; color:#e2e8f0; border-color:#374151; }
  .form-control:focus{ border-color:#60a5fa; box-shadow:none; }
  .btn-primary{ background:#2563eb; border-color:#2563eb; }
  .btn-outline-light{ border-color:#475569; color:#e2e8f0; }
  .badge-open{ background:#f59e0b; }
  .badge-done{ background:#16a34a; }
  .todo-done{ text-decoration: line-through; color:#94a3b8; }
  a, a:hover{ color:#93c5fd; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 m-0">✅ To-Do List</h1>
    <div>
      <span class="badge bg-secondary me-1">All: <?= $counts['all'] ?></span>
      <span class="badge badge-open me-1">Open: <?= $counts['open'] ?></span>
      <span class="badge badge-done">Done: <?= $counts['done'] ?></span>
    </div>
  </div>

  <!-- Add Task -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="add">
        <div class="col-md-5">
          <input class="form-control" name="title" placeholder="Task title" required>
        </div>
        <div class="col-md-5">
          <input class="form-control" name="notes" placeholder="Notes (optional)">
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Filters / Search -->
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
      <select class="form-select" name="filter" onchange="this.form.submit()">
        <option value="all"  <?= $filter==='all'?'selected':'' ?>>All</option>
        <option value="open" <?= $filter==='open'?'selected':'' ?>>Open</option>
        <option value="done" <?= $filter==='done'?'selected':'' ?>>Done</option>
      </select>
    </div>
    <div class="col-md-7">
      <input class="form-control" name="q" placeholder="Search title or notes…" value="<?= htmlspecialchars($q) ?>">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-light">Apply</button>
    </div>
  </form>

  <!-- List -->
  <div class="card">
    <div class="card-body p-0">
      <?php if (!$todos): ?>
        <p class="p-3 mb-0 text-center text-muted">No tasks yet. Add one above.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($todos as $t): ?>
            <div class="list-group-item d-flex align-items-start justify-content-between" style="background:#0b1220;">
              <div class="me-3 flex-grow-1">
                <div class="<?= $t['is_done'] ? 'todo-done' : '' ?>">
                  <strong><?= htmlspecialchars($t['title']) ?></strong>
                  <?php if ($t['is_done']): ?>
                    <span class="badge badge-done ms-1">Done</span>
                  <?php else: ?>
                    <span class="badge badge-open ms-1">Open</span>
                  <?php endif; ?>
                </div>
                <?php if ($t['notes']): ?>
                  <div class="small text-muted"><?= nl2br(htmlspecialchars($t['notes'])) ?></div>
                <?php endif; ?>
                <div class="small text-muted mt-1">
                  Created: <?= htmlspecialchars($t['created_at']) ?> • Updated: <?= htmlspecialchars($t['updated_at']) ?>
                </div>
              </div>

              <div class="d-flex gap-2">
                <!-- Toggle -->
                <form method="post" class="m-0">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="btn btn-sm btn-outline-light"><?= $t['is_done'] ? 'Mark Open' : 'Mark Done' ?></button>
                </form>

                <!-- Edit (modal-less simple inline) -->
                <button class="btn btn-sm btn-outline-light" onclick="toggleEdit(<?= (int)$t['id'] ?>)">Edit</button>

                <!-- Delete -->
                <form method="post" class="m-0" onsubmit="return confirm('Delete this task?')">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="btn btn-sm btn-outline-light">Delete</button>
                </form>
              </div>
            </div>

            <!-- Hidden edit row -->
            <div id="edit-<?= (int)$t['id'] ?>" class="p-3 border-top" style="display:none;background:#0b1220;border-color:#1f2937 !important;">
              <form method="post" class="row g-2">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <div class="col-md-5">
                  <input class="form-control" name="title" value="<?= htmlspecialchars($t['title']) ?>" required>
                </div>
                <div class="col-md-5">
                  <input class="form-control" name="notes" value="<?= htmlspecialchars($t['notes'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-grid">
                  <button class="btn btn-primary">Save</button>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function toggleEdit(id){
  const el = document.getElementById('edit-'+id);
  if (!el) return;
  el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
}
</script>
</body>
</html>
