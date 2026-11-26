<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// Pilihan kondisi perangkat. norm_kondisi() merapikan huruf besar/kecil ("nyala" -> "Nyala").
const KONDISI = ['Nyala', 'Mati'];
function norm_kondisi($v) {
    foreach (KONDISI as $k) if (strcasecmp(trim((string)$v), $k) === 0) return $k;
    return trim((string)$v);
}
// <option> kondisi; nilai lama di luar daftar tetap ditampilkan supaya datanya tidak hilang.
function opsi_kondisi($terpilih) {
    $terpilih = norm_kondisi($terpilih);
    $h = '';
    $daftar = KONDISI;
    if ($terpilih !== '' && !in_array($terpilih, $daftar, true)) $daftar[] = $terpilih;
    foreach ($daftar as $k) $h .= '<option value="' . e($k) . '"' . ($k === $terpilih ? ' selected' : '') . '>' . e($k) . '</option>';
    return $h;
}
function redirect($path) { header('Location: ' . BASE_URL . '/' . $path); exit; }

function flash($type = null, $msg = null) {
    if ($type !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Permintaan tidak valid.');
    }
}

function user() { return $_SESSION['user'] ?? null; }
function is_siswa() { return (user()['level'] ?? '') === 'Siswa'; }

function login_wajib(array $level = []) {
    if (empty($_SESSION['user'])) redirect('login.php');
    if ($level && !in_array($_SESSION['user']['level'], $level, true)) {
        http_response_code(403);
        exit('Anda tidak punya akses ke halaman ini.');
    }
}

// Ambil checklist; siswa hanya boleh membuka miliknya sendiri (username = NIS)
function ambil_checklist(PDO $pdo, $id) {
    $st = $pdo->prepare('SELECT c.*, s.nama, s.kelas FROM checklist c JOIN siswa s ON s.nis = c.nis WHERE c.id_checklist = ?');
    $st->execute([(int)$id]);
    $c = $st->fetch();
    if (!$c) { flash('danger', 'Checklist tidak ditemukan.'); redirect('checklist/'); }
    if (is_siswa() && (string)$c['nis'] !== (string)(user()['nis'] ?? '')) {
        http_response_code(403);
        exit('Ini bukan checklist Anda.');
    }
    return $c;
}

// ---------- Tampilan ----------

// Ikon SVG sebaris (stroke mengikuti warna teks). Dipakai di sidebar dan kartu.
function ikon($nama) {
    $p = [
        'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'home'   => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
        'siswa'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M17 5a3.5 3.5 0 010 7M22 20c0-2.5-1.5-4-4-5"/>',
        'bolt'   => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'list'   => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
        'moon'   => '<path d="M20 14A8 8 0 019.5 3.5 8 8 0 1020 14z"/>',
        'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
        'out'    => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>',
        'chev'   => '<path d="M6 9l6 6 6-6"/>',
        'set'    => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
        'device' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 21h8M12 16v5"/>',
        'chart'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'heart'  => '<path d="M12 20s-7-4.4-9-9.2C1.6 7.3 4 4 7.2 4c1.8 0 3.2.9 4.8 2.7C13.6 4.9 15 4 16.8 4 20 4 22.4 7.3 21 10.8 19 15.6 12 20 12 20z"/>',
        'leaf'   => '<path d="M5 19c0-8 5-14 15-14 0 10-6 15-14 15M5 19c2-5 5-8 9-10"/>',
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'eye'    => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    ][$nama] ?? '';
    return '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

// Mencari file logo di assets/img/ dengan nama konservasi.(png|jpg|jpeg|webp|svg); urutan = prioritas.
function logo_file() {
    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $x) {
        if (is_file(__DIR__ . '/../assets/img/konservasi.' . $x)) return 'konservasi.' . $x;
    }
    return null;
}

// Logo aplikasi: pakai file logo jika ada, kalau tidak pakai ikon petir.
function logo($ukuran = 36) {
    $f = logo_file();
    if ($f) {
        return '<img src="' . BASE_URL . '/assets/img/' . $f . '" alt="Logo Konservasi Energi" width="' . $ukuran . '" height="' . $ukuran . '" class="logo-img">';
    }
    return '<span class="logo-ph" style="width:' . $ukuran . 'px;height:' . $ukuran . 'px">' . ikon('bolt') . '</span>';
}

// Halaman aktif ditentukan dari alamat file yang sedang dibuka (untuk menyorot menu).
function halaman_aktif() {
    $s = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (['siswa', 'perangkat', 'checklist', 'users', 'akun'] as $k) {
        if (strpos($s, "/$k/") !== false) return $k;
    }
    return strpos($s, 'tentang') !== false ? 'tentang' : 'dashboard';
}

function menu_item($kunci, $url, $ikon, $label, $aktif) {
    $on = $kunci === $aktif ? ' on' : '';
    return '<a class="menu' . $on . '" href="' . BASE_URL . '/' . $url . '">' . ikon($ikon) . '<span>' . e($label) . '</span></a>';
}

// Tanggal berbahasa Indonesia, contoh: 15 Okt 2026
function tgl_id($tanggal) {
    $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
}

// Panjang teks; memakai mbstring bila tersedia (tidak semua server mengaktifkannya).
function panjang($s) { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); }

// Membuat akun Siswa (tabel users) dari data di tabel siswa. Dipakai saat siswa pertama kali masuk
// dengan username = NIS dan password = NIS. Mengembalikan baris users, atau null jika NIS tidak terdaftar.
function buat_akun_siswa(PDO $pdo, $nis) {
    $st = $pdo->prepare('SELECT nis, nama FROM siswa WHERE nis = ?');
    $st->execute([(int)$nis]);
    $sw = $st->fetch();
    if (!$sw || (string)$sw['nis'] !== (string)$nis) return null;   // tolak mis. "012" (angka nol di depan)
    try {
        $pdo->prepare('INSERT INTO users (username, password, nama, level, nis) VALUES (?,?,?,?,?)')
            ->execute([(string)$nis, password_hash((string)$nis, PASSWORD_DEFAULT), $sw['nama'], 'Siswa', $sw['nis']]);
    } catch (PDOException $e) {
        return null;                                                  // mis. username sudah dipakai
    }
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([(string)$nis]);
    return $st->fetch() ?: null;
}

// ---------- Ilustrasi & grafik (SVG sebaris, tanpa gambar tambahan) ----------

// Bumi dengan petir kecil, dipakai di dasar sidebar.
function ilus_bumi() {
    return '<svg viewBox="0 0 120 110" aria-hidden="true"><defs><linearGradient id="gb" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#bfe0ff"/><stop offset="1" stop-color="#5b9cf0"/></linearGradient></defs>'
         . '<circle cx="58" cy="58" r="38" fill="url(#gb)"/>'
         . '<path d="M36 44c8-8 20-6 24 2s-6 10-4 18-12 8-16 0-12-12-4-20zM70 62c8-2 16 4 14 12s-12 8-16 2 0-12 2-14z" fill="#8fd0a8" opacity=".9"/>'
         . '<ellipse cx="46" cy="40" rx="12" ry="6" fill="#fff" opacity=".35" transform="rotate(-25 46 40)"/>'
         . '<path d="M20 86c18 14 52 14 76-6" fill="none" stroke="#a9d0ff" stroke-width="3" stroke-linecap="round" opacity=".8"/>'
         . '<path d="M92 22l-9 14h7l-3 12 12-16h-8z" fill="#ffd45e" stroke="#f0b429" stroke-width="1.5" stroke-linejoin="round"/></svg>';
}

// Bohlam menyala, dipakai di kartu "Checklist hari ini".
function ilus_bohlam() {
    return '<svg class="ilus-pojok" viewBox="0 0 80 80" aria-hidden="true">'
         . '<path d="M40 6v6M14 20l5 4M66 20l-5 4M8 44h6M72 44h-6" stroke="#f0b429" stroke-width="2.6" stroke-linecap="round" fill="none"/>'
         . '<circle cx="40" cy="38" r="17" fill="#ffe08a"/><circle cx="40" cy="38" r="17" fill="none" stroke="#f0b429" stroke-width="2"/>'
         . '<path d="M34 38l6 8 6-8" stroke="#f0b429" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
         . '<rect x="32" y="55" width="16" height="9" rx="3" fill="#a9d0ff"/><rect x="35" y="65" width="10" height="5" rx="2.5" fill="#7fb0f5"/></svg>';
}

// Sekolah dengan pohon, dipakai di kartu "Mari Bersama Jaga Energi!".
function ilus_sekolah() {
    return '<svg class="sekolah" viewBox="0 0 320 110" preserveAspectRatio="xMidYMax meet" aria-hidden="true">'
         . '<path d="M0 96c40-10 90-12 160-6s120 4 160-4v24H0z" fill="#d6e9ff"/>'
         . '<g fill="#8fd0a8"><circle cx="40" cy="74" r="14"/><circle cx="62" cy="80" r="11"/><circle cx="268" cy="72" r="15"/><circle cx="292" cy="80" r="11"/></g>'
         . '<g fill="#7bb892"><rect x="38" y="84" width="4" height="14"/><rect x="266" y="82" width="4" height="16"/></g>'
         . '<rect x="102" y="56" width="116" height="42" rx="3" fill="#cfe4ff" stroke="#a9d0ff" stroke-width="1.5"/>'
         . '<path d="M96 56L160 28l64 28z" fill="#8bbcf6"/><rect x="146" y="14" width="3" height="16" fill="#7fb0f5"/><path d="M149 14h12l-3 4 3 4h-12z" fill="#5b9cf0"/>'
         . '<g fill="#fff" stroke="#a9d0ff"><rect x="112" y="64" width="16" height="12" rx="2"/><rect x="136" y="64" width="16" height="12" rx="2"/><rect x="168" y="64" width="16" height="12" rx="2"/><rect x="192" y="64" width="16" height="12" rx="2"/></g>'
         . '<rect x="150" y="76" width="20" height="22" rx="3" fill="#7fb0f5"/></svg>';
}

// Kurva halus melewati titik-titik [[x,y],...] (Catmull-Rom -> Bezier), y dijaga di antara $yMin dan $yMax.
function kurva_halus(array $p, $yMin, $yMax) {
    $n = count($p);
    if ($n === 0) return '';
    $d = 'M' . round($p[0][0], 1) . ',' . round($p[0][1], 1);
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $p[max($i - 1, 0)]; $p1 = $p[$i]; $p2 = $p[$i + 1]; $p3 = $p[min($i + 2, $n - 1)];
        $c1y = min($yMax, max($yMin, $p1[1] + ($p2[1] - $p0[1]) / 6));
        $c2y = min($yMax, max($yMin, $p2[1] - ($p3[1] - $p1[1]) / 6));
        $d .= ' C' . round($p1[0] + ($p2[0] - $p0[0]) / 6, 1) . ',' . round($c1y, 1)
            . ' ' . round($p2[0] - ($p3[0] - $p1[0]) / 6, 1) . ',' . round($c2y, 1)
            . ' ' . round($p2[0], 1) . ',' . round($p2[1], 1);
    }
    return $d;
}

// Grafik garis + area. $data = [[label, persen], ...]. Mengembalikan SVG.
function grafik_garis(array $data) {
    $W = 640; $H = 230; $kiri = 38; $kanan = 14; $atas = 14; $bawah = 30;
    $n = count($data);
    if ($n === 0) return '';
    $lebar = $W - $kiri - $kanan; $tinggi = $H - $atas - $bawah;
    $pts = [];
    foreach ($data as $i => [$lb, $v]) {
        $pts[] = [$kiri + ($n > 1 ? $lebar * $i / ($n - 1) : $lebar / 2), $atas + $tinggi * (1 - max(0, min(100, $v)) / 100)];
    }
    $garis = kurva_halus($pts, $atas, $atas + $tinggi);
    $area  = $garis . ' L' . round(end($pts)[0], 1) . ',' . ($atas + $tinggi) . ' L' . round($pts[0][0], 1) . ',' . ($atas + $tinggi) . ' Z';
    $s = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Grafik kepatuhan hemat energi"><defs><linearGradient id="ga" x1="0" y1="0" x2="0" y2="1">'
       . '<stop offset="0" stop-color="#5b9cf0" stop-opacity=".30"/><stop offset="1" stop-color="#5b9cf0" stop-opacity="0"/></linearGradient></defs>';
    foreach ([0, 25, 50, 75, 100] as $g) {
        $y = $atas + $tinggi * (1 - $g / 100);
        $s .= '<line class="grid" x1="' . $kiri . '" x2="' . ($W - $kanan) . '" y1="' . $y . '" y2="' . $y . '"/><text x="' . ($kiri - 8) . '" y="' . ($y + 3) . '" text-anchor="end">' . $g . '%</text>';
    }
    $s .= '<path d="' . $area . '" fill="url(#ga)"/><path class="ln" d="' . $garis . '"/>';
    $langkah = max(1, (int)ceil($n / 8));
    foreach ($data as $i => [$lb, $v]) {
        $s .= '<circle class="dot" cx="' . round($pts[$i][0], 1) . '" cy="' . round($pts[$i][1], 1) . '" r="' . ($n > 14 ? 2.6 : 3.6) . '"><title>' . e($lb) . ': ' . (int)$v . '%</title></circle>';
        if ($i % $langkah === 0) $s .= '<text x="' . round($pts[$i][0], 1) . '" y="' . ($H - 8) . '" text-anchor="middle">' . e($lb) . '</text>';
    }
    return $s . '</svg>';
}
