<?php
// Ekspor tabel ke Excel (.xlsx) dan PDF tanpa library tambahan.
// kirim_ekspor($format, $namaFile, $judul, $info, $header, $rows, $lebar)
//   $info  = array baris keterangan di bawah judul, $header = array judul kolom,
//   $rows  = array of array (angka tetap angka), $lebar = bobot lebar tiap kolom.

function kirim_ekspor($format, $namaFile, $judul, array $info, array $header, array $rows, array $lebar) {
    $namaFile = preg_replace('/[^A-Za-z0-9_\-]/', '_', $namaFile);
    if ($format === 'pdf') {
        $data = ekspor_pdf($judul, $info, $header, $rows, $lebar);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $namaFile . '.pdf"');
    } elseif (class_exists('ZipArchive')) {
        $data = ekspor_xlsx($judul, $info, $header, $rows, $lebar);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $namaFile . '.xlsx"');
    } else {
        // Cadangan jika ekstensi zip PHP belum aktif: tabel HTML yang bisa dibuka Excel.
        $data = ekspor_xls_html($judul, $info, $header, $rows);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $namaFile . '.xls"');
    }
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: no-store');
    echo $data;
    exit;
}

// ---------- Excel (.xlsx) ----------
function xl_kolom($i) { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }
function xl_teks($v) { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$v), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function xl_sel($ref, $v, $gaya) {
    if (is_int($v) || is_float($v)) return '<c r="' . $ref . '" s="' . $gaya . '"><v>' . $v . '</v></c>';
    return '<c r="' . $ref . '" s="' . $gaya . '" t="inlineStr"><is><t xml:space="preserve">' . xl_teks($v) . '</t></is></c>';
}

function ekspor_xlsx($judul, array $info, array $header, array $rows, array $lebar) {
    $x = ''; $r = 1;
    $x .= '<row r="' . $r++ . '">' . xl_sel('A1', $judul, 1) . '</row>';
    foreach ($info as $t) { $x .= '<row r="' . $r . '">' . xl_sel('A' . $r, $t, 0) . '</row>'; $r++; }
    $r++;
    $baris = '';
    foreach ($header as $i => $h) $baris .= xl_sel(xl_kolom($i) . $r, $h, 2);
    $x .= '<row r="' . $r++ . '">' . $baris . '</row>';
    foreach ($rows as $row) {
        $baris = '';
        foreach (array_values($row) as $i => $v) $baris .= xl_sel(xl_kolom($i) . $r, $v, 3);
        $x .= '<row r="' . $r++ . '">' . $baris . '</row>';
    }
    $total = array_sum($lebar) ?: 1; $cols = '';
    foreach ($lebar as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . round(max(8, $w / $total * 110), 1) . '" customWidth="1"/>';
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>' . $cols . '</cols><sheetData>' . $x . '</sheetData></worksheet>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF2F62A8"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4F86D9"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFDBE7F7"/></left><right style="thin"><color rgb="FFDBE7F7"/></right><top style="thin"><color rgb="FFDBE7F7"/></top><bottom style="thin"><color rgb="FFDBE7F7"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $z = new ZipArchive();
    $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Checklist" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/styles.xml', $styles);
    $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $z->close();
    $data = file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

function ekspor_xls_html($judul, array $info, array $header, array $rows) {
    $h = '<html><head><meta charset="utf-8"></head><body><h3>' . e($judul) . '</h3>';
    foreach ($info as $t) $h .= '<div>' . e($t) . '</div>';
    $h .= '<br><table border="1" cellpadding="4"><tr>';
    foreach ($header as $c) $h .= '<th style="background:#4f86d9;color:#fff">' . e($c) . '</th>';
    $h .= '</tr>';
    foreach ($rows as $row) { $h .= '<tr>'; foreach ($row as $v) $h .= '<td>' . e($v) . '</td>'; $h .= '</tr>'; }
    return $h . '</table></body></html>';
}

// ---------- PDF (A4 landscape, font Helvetica) ----------
function pdf_lebar_char($ch, $tebal) {
    static $w = null;
    if ($w === null) {
        $w = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
              1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
              333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    }
    $o = ord($ch);
    $v = ($o >= 32 && $o <= 126) ? $w[$o - 32] : 556;
    return $tebal ? $v * 1.06 : $v;
}
function pdf_ukur($s, $size, $tebal = false) { $t = 0; for ($i = 0, $n = strlen($s); $i < $n; $i++) $t += pdf_lebar_char($s[$i], $tebal); return $t * $size / 1000; }
function pdf_cp1252($s) { $r = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', (string)$s); return $r === false ? preg_replace('/[^\x20-\x7E]/', '?', (string)$s) : $r; }
function pdf_esc($s) { return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s); }
function pdf_bungkus($s, $maxW, $size) {
    $out = [];
    foreach (preg_split('/\n/', (string)$s) as $para) {
        $baris = '';
        foreach (preg_split('/ +/', $para) as $kata) {
            // kata yang lebih panjang dari kolom dipotong per huruf
            while (pdf_ukur($kata, $size) > $maxW && strlen($kata) > 1) {
                $pot = '';
                for ($i = 0; $i < strlen($kata) && pdf_ukur($pot . $kata[$i], $size) <= $maxW; $i++) $pot .= $kata[$i];
                if ($pot === '') $pot = $kata[0];
                if ($baris !== '') { $out[] = $baris; $baris = ''; }
                $out[] = $pot; $kata = substr($kata, strlen($pot));
            }
            $coba = $baris === '' ? $kata : $baris . ' ' . $kata;
            if ($baris !== '' && pdf_ukur($coba, $size) > $maxW) { $out[] = $baris; $baris = $kata; } else $baris = $coba;
        }
        $out[] = $baris;
    }
    return $out;
}

function ekspor_pdf($judul, array $info, array $header, array $rows, array $lebar) {
    $W = 842; $H = 595; $M = 36; $size = 9; $lh = 12; $pad = 4;
    $area = $W - 2 * $M; $tot = array_sum($lebar) ?: 1;
    $kol = []; foreach ($lebar as $w) $kol[] = $w / $tot * $area;
    $halaman = []; $c = ''; $y = 0;

    $kepala = function () use (&$c, &$y, $judul, $info, $M, $H, $W) {
        $c = ''; $y = $H - $M;
        $c .= "0.18 0.38 0.66 rg\nBT /F2 15 Tf " . $M . ' ' . ($y - 14) . ' Td (' . pdf_esc(pdf_cp1252($judul)) . ") Tj ET\n";
        $y -= 22;
        $c .= "0.35 0.42 0.55 rg\n";
        foreach ($info as $t) { $y -= 12; $c .= 'BT /F1 9 Tf ' . $M . ' ' . $y . ' Td (' . pdf_esc(pdf_cp1252($t)) . ") Tj ET\n"; }
        $y -= 14;
    };
    $barisHeader = function () use (&$c, &$y, $header, $kol, $M, $size, $lh, $pad) {
        $h = $lh + 2 * $pad;
        $c .= "0.31 0.525 0.85 rg\n" . $M . ' ' . ($y - $h) . ' ' . array_sum($kol) . ' ' . $h . " re f\n1 g\n";
        $x = $M;
        foreach ($header as $i => $t) { $c .= 'BT /F2 ' . $size . ' Tf ' . ($x + $pad) . ' ' . ($y - $pad - $size + 1) . ' Td (' . pdf_esc(pdf_cp1252($t)) . ") Tj ET\n"; $x += $kol[$i]; }
        $y -= $h;
    };
    $kepala(); $barisHeader();
    $zebra = false;
    foreach ($rows as $row) {
        $sel = []; $maks = 1;
        foreach (array_values($row) as $i => $v) { $sel[$i] = pdf_bungkus(pdf_cp1252($v), $kol[$i] - 2 * $pad, $size); $maks = max($maks, count($sel[$i])); }
        $h = $maks * $lh + 2 * $pad;
        if ($y - $h < $M + 14) { $halaman[] = $c; $kepala(); $barisHeader(); $zebra = false; }
        if ($zebra) $c .= "0.94 0.965 1 rg\n" . $M . ' ' . ($y - $h) . ' ' . array_sum($kol) . ' ' . $h . " re f\n";
        $zebra = !$zebra;
        $c .= "0.86 0.91 0.97 RG 0.5 w\n" . $M . ' ' . ($y - $h) . ' m ' . ($M + array_sum($kol)) . ' ' . ($y - $h) . " l S\n0.1 0.15 0.25 rg\n";
        $x = $M;
        foreach ($sel as $i => $lines) {
            foreach ($lines as $k => $t) $c .= 'BT /F1 ' . $size . ' Tf ' . ($x + $pad) . ' ' . ($y - $pad - $size + 1 - $k * $lh) . ' Td (' . pdf_esc($t) . ") Tj ET\n";
            $x += $kol[$i];
        }
        $y -= $h;
    }
    if (!$rows) { $c .= "0.35 0.42 0.55 rg\nBT /F1 10 Tf " . ($M + $pad) . ' ' . ($y - 16) . " Td (Tidak ada data.) Tj ET\n"; }
    $halaman[] = $c;

    // rakit objek PDF
    $n = count($halaman); $obj = [];
    $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = ''; for ($i = 0; $i < $n; $i++) $kids .= (5 + $i * 2) . ' 0 R ';
    $obj[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . $n . ' >>';
    $obj[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $obj[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    foreach ($halaman as $i => $isi) {
        $isi .= "0.35 0.42 0.55 rg\nBT /F1 8 Tf " . ($W - $M - 70) . ' ' . ($M - 14) . ' Td (Halaman ' . ($i + 1) . ' / ' . $n . ") Tj ET\n";
        $isi .= 'BT /F1 8 Tf ' . $M . ' ' . ($M - 14) . ' Td (Dicetak ' . date('d-m-Y H:i') . ") Tj ET\n";
        $obj[5 + $i * 2] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $W . ' ' . $H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . (6 + $i * 2) . ' 0 R >>';
        $obj[6 + $i * 2] = '<< /Length ' . strlen($isi) . " >>\nstream\n" . $isi . "endstream";
    }
    $pdf = "%PDF-1.4\n"; $off = [];
    ksort($obj);
    foreach ($obj as $no => $isi) { $off[$no] = strlen($pdf); $pdf .= $no . " 0 obj\n" . $isi . "\nendobj\n"; }
    $xref = strlen($pdf); $max = max(array_keys($obj));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) $pdf .= sprintf("%010d 00000 n \n", $off[$i]);
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}
