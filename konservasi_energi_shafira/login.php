<?php
require_once __DIR__ . '/includes/functions.php';
if (user()) redirect('index.php');           // sudah login -> ke dashboard

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();                              // tolak form yang bukan berasal dari halaman ini
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');   // prepared statement: aman dari SQL injection
    $st->execute([$username]);
    $row = $st->fetch();

    // Siswa yang ada di tabel siswa tetapi belum punya akun: username = NIS, password awal = NIS.
    $akunBaru = false;
    if (!$row && LOGIN_NIS_OTOMATIS && ctype_digit($username) && strlen($username) <= 10 && hash_equals($username, $password)) {
        $row = buat_akun_siswa($pdo, $username);
        $akunBaru = (bool)$row;
    }

    // password_verify membandingkan password yang diketik dengan hash di database
    if ($row && password_verify($password, $row['password'])) {
        session_regenerate_id(true);           // ganti ID sesi setelah login (cegah session fixation)
        $_SESSION['user'] = ['id' => $row['id_user'], 'username' => $row['username'], 'nis' => $row['nis'],
                             'nama' => $row['nama'], 'level' => $row['level']];
        if ($akunBaru) flash('warning', 'Akun Anda dibuat otomatis dengan password awal = NIS. Segera ganti di menu Setting > Ganti password.');
        redirect('index.php');
    }
    $err = 'Username atau password salah.';   // pesan sengaja sama untuk username salah / password salah
}
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk | Konservasi Energi Shafira</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
  <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
  <script>try { var t = localStorage.getItem('tema'); if (t) document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {}</script>
  <style>.login-bg { min-height:100vh; display:grid; place-items:center; padding:24px; background:radial-gradient(600px 420px at 85% 0%, #d4e8ff, transparent 70%), radial-gradient(520px 420px at 0% 100%, #c2dcff, transparent 70%), #f1f7ff; }
    .login-bg .card { border-radius:24px; box-shadow:0 16px 44px rgba(63,130,221,.14); }
    .login-bg .logo-img { box-shadow:0 10px 26px rgba(91,156,240,.30); }</style>
</head>
<body>
<div class="login-bg">
  <div class="card shadow-lg border-0" style="width:100%;max-width:400px">
    <div class="card-body p-4 p-sm-5">
      <div class="text-center mb-4">
        <div class="d-inline-block mb-3"><?= logo(96) ?></div>
        <h4 class="mb-1">Konservasi Energi Shafira</h4>
        <div class="muted">Masuk untuk mengisi checklist hemat energi.</div>
        <?php if (LOGIN_NIS_OTOMATIS): ?><div class="muted small mt-1">Siswa: username dan password awal adalah NIS.</div><?php endif; ?>
      </div>
      <?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Username</label>
          <input name="username" class="form-control" required autofocus></div>
        <div class="mb-4"><label class="form-label">Password</label>
          <input name="password" type="password" class="form-control" required></div>
        <button class="btn btn-success w-100 py-2">Masuk</button>
      </form>
    </div>
    <div class="px-3 pb-3" style="margin-top:-6px"><?= ilus_sekolah() ?></div>
  </div>
</div>
</body></html>
