<?php
require_once __DIR__ . '/includes/functions.php';
login_wajib();
$judul = 'Dashboard';

$level  = user()['level'];
$siswa  = is_siswa();
$nis    = $siswa ? (int)(user()['nis'] ?? 0) : 0;
$awalBulan = date('Y-m-01');
$mulai7    = date('Y-m-d', strtotime('-6 days'));
$mulai30   = date('Y-m-d', strtotime('-29 days'));
$awalLalu  = date('Y-m-01', strtotime('first day of last month'));
$rentang   = (int)($_GET['hari'] ?? 7);
if (!in_array($rentang, [7, 14, 30], true)) $rentang = 7;
$mulaiGrf  = date('Y-m-d', strtotime('-' . ($rentang - 1) . ' days'));

// Siswa hanya melihat data miliknya: tambahkan syarat nis ke setiap query.
$fc = $siswa ? ' AND c.nis = :nis' : '';
$ambil = function ($sql, array $p = []) use ($pdo, $siswa, $nis) {
    if ($siswa && strpos($sql, ':nis') !== false) $p[':nis'] = $nis;
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st;
};

$jmlChecklist = (int)$ambil("SELECT COUNT(*) FROM checklist c WHERE c.tanggal >= :m $fc", [':m' => $awalBulan])->fetchColumn();
$r = $ambil("SELECT COUNT(*) total, SUM(d.status = 'Ya') ya FROM detail_checklist d
             JOIN checklist c ON c.id_checklist = d.id_checklist WHERE c.tanggal >= :m $fc", [':m' => $awalBulan])->fetch();
$persen = $r['total'] ? round($r['ya'] / $r['total'] * 100) : 0;

// Bulan lalu, untuk lencana naik/turun di kartu statistik
$jmlLalu = (int)$ambil("SELECT COUNT(*) FROM checklist c WHERE c.tanggal >= :a AND c.tanggal < :b $fc", [':a' => $awalLalu, ':b' => $awalBulan])->fetchColumn();
$r2 = $ambil("SELECT COUNT(*) total, SUM(d.status = 'Ya') ya FROM detail_checklist d
              JOIN checklist c ON c.id_checklist = d.id_checklist WHERE c.tanggal >= :a AND c.tanggal < :b $fc", [':a' => $awalLalu, ':b' => $awalBulan])->fetch();
$persenLalu = $r2['total'] ? round($r2['ya'] / $r2['total'] * 100) : null;

// Checklist hari ini: tahapan mana yang sudah dicatat (jumlah perangkat per tahapan)
$hariIni = [];
foreach ($ambil("SELECT d.tahapan, COUNT(DISTINCT d.id_perangkat) n FROM detail_checklist d
        JOIN checklist c ON c.id_checklist = d.id_checklist WHERE c.tanggal = :t $fc GROUP BY d.tahapan", [':t' => date('Y-m-d')])->fetchAll() as $x) $hariIni[$x['tahapan']] = (int)$x['n'];

$terbaru = $ambil("SELECT c.id_checklist, c.tanggal, c.jam, s.nama, s.kelas,
        COUNT(d.id_detail) jml, SUM(d.status = 'Ya') ya
    FROM checklist c JOIN siswa s ON s.nis = c.nis
    LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
    WHERE 1 = 1 $fc GROUP BY c.id_checklist ORDER BY c.tanggal DESC, c.jam DESC LIMIT 5")->fetchAll();

if (!$siswa) {
    $jmlSiswa     = $pdo->query('SELECT COUNT(*) FROM siswa')->fetchColumn();
    $jmlPerangkat = $pdo->query('SELECT COUNT(*) FROM perangkat')->fetchColumn();

    // Grafik: persentase "Ya" per hari selama 7/14/30 hari terakhir (dipilih lewat ?hari=)
    $per = [];
    foreach ($ambil("SELECT c.tanggal, ROUND(100 * SUM(d.status = 'Ya') / COUNT(*)) p
        FROM checklist c JOIN detail_checklist d ON d.id_checklist = c.id_checklist
        WHERE c.tanggal >= :m GROUP BY c.tanggal", [':m' => $mulaiGrf])->fetchAll() as $x) $per[$x['tanggal']] = (int)$x['p'];
    $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $grafik = [];
    for ($i = $rentang - 1; $i >= 0; $i--) {
        $t = date('Y-m-d', strtotime("-$i days"));
        $grafik[] = [$rentang === 7 ? $hari[(int)date('w', strtotime($t))] : date('j/n', strtotime($t)), $per[$t] ?? 0];
    }

    $jmlSiswaAktif = (int)$pdo->query("SELECT COUNT(DISTINCT nis) FROM checklist WHERE tanggal >= '$mulai30'")->fetchColumn();

    // Kondisi terakhir tiap perangkat (yang paling baru dicatat tampil lebih dulu)
    $kondisi = $pdo->query("SELECT p.id_perangkat, p.nama_perangkat, p.jenis, d.kondisi, d.status
        FROM perangkat p LEFT JOIN detail_checklist d ON d.id_detail = (
            SELECT d2.id_detail FROM detail_checklist d2 JOIN checklist c2 ON c2.id_checklist = d2.id_checklist
            WHERE d2.id_perangkat = p.id_perangkat ORDER BY c2.tanggal DESC, c2.jam DESC, d2.id_detail DESC LIMIT 1)
        ORDER BY (d.id_detail IS NULL), d.id_detail DESC, p.id_perangkat DESC LIMIT 5")->fetchAll();

    // Unit yang paling sering berstatus "Tidak" dalam 30 hari terakhir
    $perhatian = $ambil("SELECT p.nama_perangkat, COUNT(*) n FROM detail_checklist d
        JOIN perangkat p ON p.id_perangkat = d.id_perangkat
        JOIN checklist c ON c.id_checklist = d.id_checklist
        WHERE d.status = 'Tidak' AND c.tanggal >= :m GROUP BY p.id_perangkat ORDER BY n DESC LIMIT 5", [':m' => $mulai30])->fetchAll();

    if ($level === 'Guru') {
        $kelas = $ambil("SELECT s.kelas, ROUND(100 * SUM(d.status = 'Ya') / COUNT(*)) p FROM detail_checklist d
            JOIN checklist c ON c.id_checklist = d.id_checklist JOIN siswa s ON s.nis = c.nis
            WHERE c.tanggal >= :m GROUP BY s.kelas ORDER BY s.kelas", [':m' => $mulai30])->fetchAll();
    } else {
        $akun = $pdo->query('SELECT level, COUNT(*) n FROM users GROUP BY level')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

function kartu($ikon, $label, $nilai, $satuan = '', $warna = '', $delta = null, $akhir = '') {
    $b = '';
    if ($delta !== null) $b = '<span class="delta ' . ($delta < 0 ? 'down' : '') . '">' . ($delta < 0 ? '▼ ' : '▲ ') . e(abs($delta)) . '%</span>';
    return '<div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body stat"><div class="stat-ic ' . $warna . '">' . ikon($ikon) . '</div>'
         . '<div><div class="stat-k">' . e($label) . '</div><div class="stat-v">' . e($nilai) . ($satuan !== '' ? ' <small>' . e($satuan) . '</small>' : '') . $b . '</div></div>'
         . ($akhir ? '<span class="stat-end">' . ikon($akhir) . '</span>' : '') . '</div></div></div>';
}
$tugas = [
    'SEBELUM' => ['Cek perangkat sebelum dipakai', 'perangkat dicatat'],
    'SELAMA'  => ['Pantau perangkat saat dipakai', 'perangkat dicatat'],
    'SESUDAH' => ['Matikan perangkat setelah dipakai', 'perangkat dicatat'],
    '5R'      => ['Terapkan 5R (ringkas, rapi, resik, rawat, rajin)', 'perangkat dicatat'],
];
$dChk = $jmlLalu > 0 ? (int)round(($jmlChecklist - $jmlLalu) / $jmlLalu * 100) : null;
$dPsn = $persenLalu !== null ? (int)($persen - $persenLalu) : null;

require __DIR__ . '/includes/header.php';
?>
<div class="dash-hd">
  <div><h3><?= $siswa ? 'Halo, ' . e(user()['nama']) : 'Dashboard' ?></h3>
    <div class="muted">Selamat datang di Konservasi Energi Shafira, <?= e(user()['nama']) ?> ✨</div></div>
  <a class="btn btn-success btn-buat" href="<?= BASE_URL ?>/checklist/form.php"><?= ikon('plus') ?> Buat checklist</a>
</div>

<div class="row g-3">
  <?= kartu('list', 'Checklist Bulan Ini', $jmlChecklist, 'Data', '', $dChk) ?>
  <?= kartu('leaf', 'Kepatuhan Hemat Energi', $persen . '%', '', 'g', $dPsn) ?>
  <?php if (!$siswa): ?>
    <?= kartu('device', 'Perangkat Terdaftar', $jmlPerangkat, 'Unit', 'p') ?>
    <?= kartu('siswa', 'Siswa Peduli Energi', $jmlSiswaAktif, 'Orang', '', null, 'heart') ?>
  <?php endif; ?>
</div>

<div class="row g-3">
<?php if (!$siswa): ?>
  <div class="col-xl-8"><div class="card h-100"><div class="card-body">
    <div class="card-hd"><h6><?= ikon('chart') ?> Grafik Kepatuhan Hemat Energi</h6>
      <div class="dropdown"><button class="rentang dropdown-toggle" type="button" data-bs-toggle="dropdown"><?= $rentang ?> Hari Terakhir</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php foreach ([7, 14, 30] as $h): ?><li><a class="dropdown-item" href="?hari=<?= $h ?>"><?= $h ?> Hari Terakhir</a></li><?php endforeach; ?>
        </ul></div></div>
    <?= grafik_garis($grafik) ?>
  </div></div></div>
<?php endif; ?>
  <div class="<?= $siswa ? 'col-lg-6' : 'col-xl-4' ?>"><div class="card h-100" style="position:relative"><div class="card-body">
    <div class="card-hd"><h6><?= ikon('check') ?> Checklist Hari Ini</h6><a class="lihat" href="<?= BASE_URL ?>/checklist/">Lihat Semua →</a></div>
    <?php foreach ($tugas as $kunci => [$teks, $sat]): $n = $hariIni[$kunci] ?? 0; ?>
      <div class="todo <?= $n ? 'done' : '' ?>"><span class="cek"><?= ikon('check') ?></span>
        <div><b><?= e($teks) ?></b><small><?= $n ? $n . ' ' . e($sat) : 'Belum ada catatan hari ini' ?></small></div></div>
    <?php endforeach; ?>
    <?= ilus_bohlam() ?>
  </div></div></div>
</div>

<?php if (!$siswa): ?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card h-100"><div class="card-body pb-0">
    <div class="card-hd"><h6><?= ikon('device') ?> Kondisi Perangkat Lab RPL</h6><a class="lihat" href="<?= BASE_URL ?>/perangkat/">Lihat Semua →</a></div></div>
    <div class="table-responsive"><table class="table tbl-sm align-middle">
      <thead><tr><th style="width:44px">No</th><th>Nama Perangkat</th><th>Kondisi</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
      <?php foreach ($kondisi as $i => $x): $k = $x['kondisi'] === null ? 'kosong' : (strcasecmp($x['kondisi'], 'Mati') === 0 ? 'mati' : ''); ?>
        <tr><td><?= $i + 1 ?></td>
          <td><b class="fw-semibold"><?= e($x['nama_perangkat']) ?></b><span class="nama-sub"><?= e($x['jenis']) ?></span></td>
          <td><span class="kondisi <?= $k ?>"><?= $x['kondisi'] === null ? 'Belum dicatat' : e(norm_kondisi($x['kondisi'])) ?></span></td>
          <td><?php if ($x['status'] === null): ?><span class="chip nol">-</span><?php else: ?><span class="chip <?= $x['status'] === 'Ya' ? 'ya' : 'tidak' ?>"><?= e($x['status']) ?></span><?php endif; ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>/perangkat/form.php?id=<?= (int)$x['id_perangkat'] ?>"><?= ikon('eye') ?> Detail</a></td></tr>
      <?php endforeach; if (!$kondisi): ?>
        <tr><td colspan="5" class="text-center muted py-4">Belum ada perangkat.</td></tr>
      <?php endif; ?>
      </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card ajak h-100"><div class="card-body">
    <h6><?= ikon('leaf') ?> Mari Bersama Jaga Energi!</h6>
    <div class="muted small">Energi hari ini untuk masa depan nanti 🌱</div></div>
    <?= ilus_sekolah() ?></div></div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="<?= $siswa ? 'col-12' : 'col-xl-8' ?>"><div class="card h-100"><div class="card-body pb-0">
    <div class="card-hd"><h6><?= ikon('list') ?> <?= $siswa ? 'Riwayat checklist saya' : 'Checklist terbaru' ?></h6><a class="lihat" href="<?= BASE_URL ?>/checklist/">Lihat Semua →</a></div></div>
    <div class="table-responsive"><table class="table tbl-sm align-middle">
      <thead><tr><th>Tanggal</th><th>Jam</th><?php if (!$siswa): ?><th>Siswa</th><th>Kelas</th><?php endif; ?><th>Ya/Total</th><th class="text-end">Aksi</th></tr></thead><tbody>
      <?php foreach ($terbaru as $x): ?>
        <tr><td><?= e(tgl_id($x['tanggal'])) ?></td><td><?= e(substr($x['jam'], 0, 5)) ?></td>
          <?php if (!$siswa): ?><td><?= e($x['nama']) ?></td><td><?= e($x['kelas']) ?></td><?php endif; ?>
          <td><?= (int)$x['ya'] ?>/<?= (int)$x['jml'] ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>/checklist/detail.php?id=<?= $x['id_checklist'] ?>"><?= ikon('eye') ?> Buka</a></td></tr>
      <?php endforeach; if (!$terbaru): ?>
        <tr><td colspan="6" class="text-center muted py-4">Belum ada checklist. Klik "Buat checklist" untuk memulai.</td></tr>
      <?php endif; ?>
      </tbody></table></div></div></div>
<?php if (!$siswa): ?>
  <div class="col-xl-4 d-flex flex-column gap-3">
    <div class="card"><div class="card-body"><div class="card-hd"><h6><?= ikon('bolt') ?> Unit perlu perhatian</h6></div>
      <?php foreach ($perhatian as $x): ?>
        <div class="perhatian"><?= e($x['nama_perangkat']) ?><small>Status Tidak <?= (int)$x['n'] ?> kali dalam 30 hari</small></div>
      <?php endforeach; if (!$perhatian): ?><div class="muted">Belum ada unit bermasalah.</div><?php endif; ?>
    </div></div>
    <?php if ($level === 'Admin'): ?>
    <div class="card"><div class="card-body"><div class="card-hd"><h6><?= ikon('shield') ?> Pengguna</h6></div>
      <div class="muted mb-3">Admin <?= (int)($akun['Admin'] ?? 0) ?> &middot; Guru <?= (int)($akun['Guru'] ?? 0) ?> &middot; Siswa <?= (int)($akun['Siswa'] ?? 0) ?></div>
      <a class="btn btn-outline-success btn-sm" href="<?= BASE_URL ?>/users/">Kelola pengguna</a></div></div>
    <?php else: ?>
    <div class="card"><div class="card-body"><div class="card-hd"><h6><?= ikon('siswa') ?> Kepatuhan per kelas (30 hari)</h6></div>
      <?php foreach ($kelas as $k): ?>
        <div class="py-1"><?= e($k['kelas']) ?> <span class="muted">&middot; <?= (int)$k['p'] ?>%</span><div class="bar-bg"><i style="width:<?= (int)$k['p'] ?>%"></i></div></div>
      <?php endforeach; if (!$kelas): ?><div class="muted">Belum ada data.</div><?php endif; ?>
    </div></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
