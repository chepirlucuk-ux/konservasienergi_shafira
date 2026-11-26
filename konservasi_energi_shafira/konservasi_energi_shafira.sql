-- =====================================================================
-- KONSERVASI ENERGI SHAFIRA - satu file SQL (struktur + data + perangkat lengkap)
-- Cara pakai: phpMyAdmin > tab SQL > tempel seluruh isi file ini > Go
-- PERHATIAN: tabel lama dengan nama yang sama akan DIHAPUS lalu dibuat ulang.
-- Login contoh: admin / 111, chepir / 123 (password = angka id_user)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS konservasi_energi_shafira CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE konservasi_energi_shafira;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detail_checklist;
DROP TABLE IF EXISTS cheklist;
DROP TABLE IF EXISTS checklist;
DROP TABLE IF EXISTS perangkat;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS siswa;
SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE siswa (
  nis   INT(10) NOT NULL,
  nama  VARCHAR(100) NOT NULL,
  kelas VARCHAR(10) NOT NULL,
  PRIMARY KEY (nis)
) ENGINE=InnoDB;

-- Kolom nis menghubungkan akun level Siswa ke data siswanya.
-- Aturan: akun Siswa WAJIB punya NIS; akun Admin/Guru tidak memakai NIS (NULL).
CREATE TABLE users (
  id_user  INT(11) NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  nama     VARCHAR(100) NOT NULL,
  level    ENUM('Admin','Guru','Siswa') NOT NULL,
  nis      INT(10) NULL DEFAULT NULL,
  PRIMARY KEY (id_user),
  UNIQUE KEY uk_username (username),
  KEY idx_users_nis (nis),
  -- ON UPDATE RESTRICT (bukan CASCADE): kolom yang dipakai di CHECK tidak boleh punya aksi CASCADE
  CONSTRAINT fk_users_siswa FOREIGN KEY (nis) REFERENCES siswa (nis)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_users_nis CHECK ((level = 'Siswa' AND nis IS NOT NULL) OR (level <> 'Siswa' AND nis IS NULL))
) ENGINE=InnoDB;

CREATE TABLE perangkat (
  id_perangkat   INT(11) NOT NULL AUTO_INCREMENT,
  nama_perangkat VARCHAR(100) NOT NULL,
  jenis          VARCHAR(50) NOT NULL,
  keterangan     TEXT,
  PRIMARY KEY (id_perangkat)
) ENGINE=InnoDB;

CREATE TABLE checklist (
  id_checklist INT(11) NOT NULL AUTO_INCREMENT,
  nis          INT(10) NOT NULL,
  tanggal      DATE NOT NULL,
  jam          TIME NOT NULL,
  PRIMARY KEY (id_checklist),
  KEY idx_nis (nis),
  CONSTRAINT fk_checklist_siswa FOREIGN KEY (nis) REFERENCES siswa (nis)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE detail_checklist (
  id_detail    INT(11) NOT NULL AUTO_INCREMENT,
  id_checklist INT(11) NOT NULL,
  id_perangkat INT(11) NOT NULL,
  tahapan      ENUM('SEBELUM','SELAMA','SESUDAH','5R') NOT NULL,
  kondisi      VARCHAR(100) NOT NULL,
  status       ENUM('Ya','Tidak') NOT NULL,
  keterangan   TEXT,
  PRIMARY KEY (id_detail),
  KEY idx_checklist (id_checklist),
  KEY idx_perangkat (id_perangkat),
  CONSTRAINT fk_detail_checklist FOREIGN KEY (id_checklist) REFERENCES checklist (id_checklist)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detail_perangkat FOREIGN KEY (id_perangkat) REFERENCES perangkat (id_perangkat)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------- DATA: SISWA ----------
-- NIS = angka dari chat. Kelas sementara '10' - ubah sesuai aslinya.
INSERT INTO siswa (nis, nama, kelas) VALUES
(123, 'Chepir', '10'),
(124, 'Nata',   '10'),
(125, 'Novi',   '10'),
(126, 'Dine',   '10'),
(127, 'Sifa',   '10');

-- ---------- DATA: USERS ----------
-- Akun Siswa memakai NIS = id_user (wajib oleh aturan tabel); Admin/Guru tanpa NIS.
INSERT INTO users (id_user, username, password, nama, level, nis) VALUES
(111, 'admin', '$2y$10$i/IEwi3YG5bC/RJ/9uGFbehObvuD/zWNZBpAo.LoDyPDYIgFyQA6i', 'Admin', 'Admin', NULL),
(150, 'bu bherta', '$2y$10$10rTPPHZVjp44arxYSkRKeBWfnweK.uqAl2tsT5uub1/UGngboavq', 'Bu Bherta', 'Guru', NULL),
(152, 'bu zulfa', '$2y$10$be2tgqYGoNfUD0mi3ogeHulF0vx9PRR.Pbj6p3FbI1JU.eEOaXD0C', 'Bu Zulfa', 'Guru', NULL),
(153, 'pak rofik', '$2y$10$ujjrvm73Lk6QPgpW6DtodeYzbkbb4kLDp7Y5R29ydMzysV7lnvdxG', 'Pak Rofik', 'Guru', NULL),
(154, 'pak heru', '$2y$10$nrcJNHYqHlDmodNqZ2EKhOQq.qrq5IGCWxA8SWrptVU3XOZDrX2wi', 'Pak Heru', 'Guru', NULL),
(155, 'bu nia', '$2y$10$hln23RLQlX2edJpeIvpP7.kv3cCGxWHTIYXF2Hk68AG.0LB9fGnmm', 'Bu Nia', 'Guru', NULL),
(123, 'chepir', '$2y$10$IHnv11Qp1LN3Hmm0F7RMwu1swAWHd0ZmVm66EECoH95ZTqM5nnH1K', 'Chepir', 'Siswa', 123),
(124, 'nata', '$2y$10$WrB4FuSw0uoOWTJtVU2/Lup4GvBhncY.0CEa2Fbp3eHbtwEA4wyLu', 'Nata', 'Siswa', 124),
(125, 'novi', '$2y$10$uwa7aeWIh/WxlyYx95GkvelRNrRCkEeURV9B45YuS6mUq2l85t7Su', 'Novi', 'Siswa', 125),
(126, 'dine', '$2y$10$tXHgXv3WxN7IaOSRk0fxRuTtRXYCSDlJWGh9a0srqTYy7NPZZxNoC', 'Dine', 'Siswa', 126),
(127, 'sifa', '$2y$10$8pBqPaJrYUPHBji.mpbcfef6b12/JKZmP1U.sxXkhZQxYgwej1u8G', 'Sifa', 'Siswa', 127);

-- ---------- DATA: PERANGKAT LAB RPL (45 perangkat, sudah termasuk tambahan) ----------
INSERT INTO perangkat (nama_perangkat, jenis, keterangan) VALUES
('PC Lab 01', 'Komputer', 'Komputer siswa nomor 1 (CPU, monitor, keyboard, mouse)'),
('PC Lab 02', 'Komputer', 'Komputer siswa nomor 2 (CPU, monitor, keyboard, mouse)'),
('PC Lab 03', 'Komputer', 'Komputer siswa nomor 3 (CPU, monitor, keyboard, mouse)'),
('PC Lab 04', 'Komputer', 'Komputer siswa nomor 4 (CPU, monitor, keyboard, mouse)'),
('PC Lab 05', 'Komputer', 'Komputer siswa nomor 5 (CPU, monitor, keyboard, mouse)'),
('PC Lab 06', 'Komputer', 'Komputer siswa nomor 6 (CPU, monitor, keyboard, mouse)'),
('PC Lab 07', 'Komputer', 'Komputer siswa nomor 7 (CPU, monitor, keyboard, mouse)'),
('PC Lab 08', 'Komputer', 'Komputer siswa nomor 8 (CPU, monitor, keyboard, mouse)'),
('PC Lab 09', 'Komputer', 'Komputer siswa nomor 9 (CPU, monitor, keyboard, mouse)'),
('PC Lab 10', 'Komputer', 'Komputer siswa nomor 10 (CPU, monitor, keyboard, mouse)'),
('PC Lab 11', 'Komputer', 'Komputer siswa nomor 11 (CPU, monitor, keyboard, mouse)'),
('PC Lab 12', 'Komputer', 'Komputer siswa nomor 12 (CPU, monitor, keyboard, mouse)'),
('PC Lab 13', 'Komputer', 'Komputer siswa nomor 13 (CPU, monitor, keyboard, mouse)'),
('PC Lab 14', 'Komputer', 'Komputer siswa nomor 14 (CPU, monitor, keyboard, mouse)'),
('PC Lab 15', 'Komputer', 'Komputer siswa nomor 15 (CPU, monitor, keyboard, mouse)'),
('PC Lab 16', 'Komputer', 'Komputer siswa nomor 16 (CPU, monitor, keyboard, mouse)'),
('PC Lab 17', 'Komputer', 'Komputer siswa nomor 17 (CPU, monitor, keyboard, mouse)'),
('PC Lab 18', 'Komputer', 'Komputer siswa nomor 18 (CPU, monitor, keyboard, mouse)'),
('PC Lab 19', 'Komputer', 'Komputer siswa nomor 19 (CPU, monitor, keyboard, mouse)'),
('PC Lab 20', 'Komputer', 'Komputer siswa nomor 20 (CPU, monitor, keyboard, mouse)'),
('PC Guru', 'Komputer', 'Komputer meja guru'),
('Laptop Guru', 'Laptop', 'Laptop pengajar untuk presentasi'),
('Server Lab', 'Server', 'Server lab (web/database lokal)'),
('Proyektor Lab', 'Proyektor', 'Proyektor ruang lab'),
('Layar Proyektor', 'Proyektor', 'Layar proyektor elektrik'),
('TV Display Lab', 'Display', 'Layar TV informasi dan presentasi'),
('AC Lab RPL 1', 'AC', 'Pendingin ruangan sisi kiri'),
('AC Lab RPL 2', 'AC', 'Pendingin ruangan sisi kanan'),
('Kipas Angin Dinding', 'Kipas', 'Kipas angin dinding cadangan'),
('Exhaust Fan', 'Kipas', 'Pembuangan udara ruang lab'),
('Lampu Lab RPL 1', 'Lampu', 'Lampu LED penerangan baris 1'),
('Lampu Lab RPL 2', 'Lampu', 'Lampu LED penerangan baris 2'),
('Lampu Lab RPL 3', 'Lampu', 'Lampu LED penerangan baris 3'),
('Lampu Lab RPL 4', 'Lampu', 'Lampu LED penerangan baris 4'),
('Lampu Teras / Depan Lab', 'Lampu', 'Lampu luar ruangan lab'),
('Switch Jaringan 24 Port', 'Jaringan', 'Switch penghubung seluruh PC'),
('Router Lab', 'Jaringan', 'Router jaringan lab'),
('Access Point WiFi', 'Jaringan', 'Access point WiFi lab'),
('UPS Lab', 'Kelistrikan', 'Cadangan daya server dan PC guru'),
('Stabilizer Listrik', 'Kelistrikan', 'Penstabil tegangan'),
('Terminal Stop Kontak Meja', 'Kelistrikan', 'Stop kontak dan kabel roll meja siswa'),
('Panel Listrik / MCB Lab', 'Kelistrikan', 'Panel pemutus daya ruang lab'),
('Printer Lab', 'Printer', 'Printer cetak tugas'),
('Speaker Aktif', 'Audio', 'Speaker untuk presentasi'),
('Dispenser Lab', 'Elektronik', 'Dispenser air minum');
