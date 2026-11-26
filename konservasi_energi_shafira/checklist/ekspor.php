<?php
// Ekspor checklist ke Excel/PDF.
//   ekspor.php?format=xlsx|pdf&id=NN   -> detail satu checklist
//   ekspor.php?format=xlsx|pdf&q=...   -> daftar checklist (mengikuti pencarian)
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ekspor.php';
login_wajib();
$format = ($_GET['format'] ?? '') === 'pdf' ? 'pdf' : 'xlsx';
$cetak  = date('d-m-Y H:i');

if (isset($_GET['id'])) {
    $c = ambil_checklist($pdo, $_GET['id']);   // siswa hanya boleh miliknya sendiri
    $st = $pdo->prepare('SELECT d.*, p.nama_perangkat, p.jenis
        FROM detail_checklist d JOIN perangkat p ON p.id_perangkat = d.id_perangkat
        WHERE d.id_checklist = ?
        ORDER BY FIELD(d.tahapan, "SEBELUM","SELAMA","SESUDAH","5R"), p.nama_perangkat');
    $st->execute([$c['id_checklist']]);
    $rows = []; $no = 1; $ya = 0;
    foreach ($st->fetchAll() as $r) {
        if ($r['status'] === 'Ya') $ya++;
        $rows[] = [$no++, $r['tahapan'], $r['nama_perangkat'], $r['jenis'], norm_kondisi($r['kondisi']), $r['status'], $r['keterangan']];
    }
    kirim_ekspor($format, 'checklist_' . $c['nis'] . '_' . $c['tanggal'],
        'Checklist Konservasi Energi Shafira',
        ['Siswa: ' . $c['nama'] . ' (NIS ' . $c['nis'] . ') - Kelas ' . $c['kelas'],
         'Tanggal: ' . $c['tanggal'] . ' pukul ' . substr($c['jam'], 0, 5),
         'Item Ya: ' . $ya . ' dari ' . count($rows)],
        ['No', 'Tahapan', 'Perangkat', 'Jenis', 'Kondisi', 'Status', 'Keterangan'], $rows, [4, 9, 22, 10, 22, 8, 25]);
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT c.tanggal, c.jam, s.nis, s.nama, s.kelas,
               COUNT(d.id_detail) AS jml_item, COALESCE(SUM(d.status = "Ya"), 0) AS jml_ya
        FROM checklist c
        JOIN siswa s ON s.nis = c.nis
        LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
        WHERE (s.nama LIKE :a OR s.kelas LIKE :b OR c.tanggal LIKE :c)';
$par = [':a' => "%$q%", ':b' => "%$q%", ':c' => "%$q%"];
if (is_siswa()) { $sql .= ' AND c.nis = :nis'; $par[':nis'] = (int)(user()['nis'] ?? 0); }
$sql .= ' GROUP BY c.id_checklist ORDER BY c.tanggal DESC, c.jam DESC';
$st = $pdo->prepare($sql);
$st->execute($par);
$rows = []; $no = 1;
foreach ($st->fetchAll() as $r) {
    $rows[] = [$no++, $r['tanggal'], substr($r['jam'], 0, 5), $r['nis'], $r['nama'], $r['kelas'], (int)$r['jml_ya'], (int)$r['jml_item']];
}
kirim_ekspor($format, 'daftar_checklist_' . date('Ymd'),
    'Daftar Checklist Konservasi Energi Shafira',
    [$q !== '' ? 'Filter pencarian: ' . $q : 'Semua checklist', 'Jumlah checklist: ' . count($rows), 'Dicetak: ' . $cetak],
    ['No', 'Tanggal', 'Jam', 'NIS', 'Siswa', 'Kelas', 'Item Ya', 'Total Item'], $rows, [4, 11, 7, 9, 28, 9, 9, 10]);
