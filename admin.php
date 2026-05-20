<?php
session_start();

// Proteksi halaman admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "zwei1", "kasir_mini");
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// =============================================
// PROCEDURE & FUNCTION (dibuat otomatis jika belum ada)
// =============================================
mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS mobil (
    id_mobil INT AUTO_INCREMENT PRIMARY KEY,
    nama_mobil VARCHAR(100),
    merek VARCHAR(100),
    tahun INT,
    harga_per_hari DECIMAL(15,2),
    stok INT DEFAULT 1,
    status ENUM('tersedia','habis') DEFAULT 'tersedia',
    foto VARCHAR(255) DEFAULT NULL
)");

mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS penyewa (
    id_penyewa INT AUTO_INCREMENT PRIMARY KEY,
    nama_penyewa VARCHAR(100),
    no_hp VARCHAR(20),
    alamat TEXT,
    nik VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS sewa (
    id_sewa INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    id_mobil INT,
    id_penyewa INT,
    tgl_mulai DATE,
    tgl_selesai DATE,
    total_hari INT,
    total_bayar DECIMAL(15,2),
    status ENUM('aktif','selesai','dibatalkan') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Stored Procedure: tambah sewa & kurangi stok
mysqli_query($conn, "DROP PROCEDURE IF EXISTS proc_tambah_sewa");
mysqli_query($conn, "
CREATE PROCEDURE proc_tambah_sewa(
    IN p_id_user INT,
    IN p_id_mobil INT,
    IN p_id_penyewa INT,
    IN p_tgl_mulai DATE,
    IN p_tgl_selesai DATE
)
BEGIN
    DECLARE v_harga DECIMAL(15,2);
    DECLARE v_stok INT;
    DECLARE v_hari INT;
    DECLARE v_total DECIMAL(15,2);
    
    SELECT harga_per_hari, stok INTO v_harga, v_stok FROM mobil WHERE id_mobil = p_id_mobil;
    SET v_hari = DATEDIFF(p_tgl_selesai, p_tgl_mulai);
    SET v_total = v_harga * v_hari;
    
    IF v_stok > 0 THEN
        INSERT INTO sewa (id_user, id_mobil, id_penyewa, tgl_mulai, tgl_selesai, total_hari, total_bayar)
        VALUES (p_id_user, p_id_mobil, p_id_penyewa, p_tgl_mulai, p_tgl_selesai, v_hari, v_total);
        UPDATE mobil SET stok = stok - 1 WHERE id_mobil = p_id_mobil;
        UPDATE mobil SET status = IF(stok <= 0, 'habis', 'tersedia') WHERE id_mobil = p_id_mobil;
    END IF;
END
");

// Function: hitung total bayar
mysqli_query($conn, "DROP FUNCTION IF EXISTS fn_total_bayar");
mysqli_query($conn, "
CREATE FUNCTION fn_total_bayar(p_id_mobil INT, p_hari INT)
RETURNS DECIMAL(15,2)
DETERMINISTIC
BEGIN
    DECLARE v_harga DECIMAL(15,2);
    SELECT harga_per_hari INTO v_harga FROM mobil WHERE id_mobil = p_id_mobil;
    RETURN v_harga * p_hari;
END
");

$msg = "";
$msg_type = "";
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// =============================================
// AKSI MOBIL
// =============================================
if (isset($_POST['aksi_mobil'])) {
    $aksi = $_POST['aksi_mobil'];
    if ($aksi == 'tambah') {
        $nama  = mysqli_real_escape_string($conn, $_POST['nama_mobil']);
        $merek = mysqli_real_escape_string($conn, $_POST['merek']);
        $tahun = (int)$_POST['tahun'];
        $harga = (float)$_POST['harga_per_hari'];
        $stok  = (int)$_POST['stok'];
        $status = $stok > 0 ? 'tersedia' : 'habis';
        mysqli_query($conn, "INSERT INTO mobil (nama_mobil, merek, tahun, harga_per_hari, stok, status) VALUES ('$nama','$merek',$tahun,$harga,$stok,'$status')");
        $msg = "Mobil berhasil ditambahkan!"; $msg_type = "success"; $tab = 'mobil';
    } elseif ($aksi == 'edit') {
        $id    = (int)$_POST['id_mobil'];
        $nama  = mysqli_real_escape_string($conn, $_POST['nama_mobil']);
        $merek = mysqli_real_escape_string($conn, $_POST['merek']);
        $tahun = (int)$_POST['tahun'];
        $harga = (float)$_POST['harga_per_hari'];
        $stok  = (int)$_POST['stok'];
        $status = $stok > 0 ? 'tersedia' : 'habis';
        mysqli_query($conn, "UPDATE mobil SET nama_mobil='$nama', merek='$merek', tahun=$tahun, harga_per_hari=$harga, stok=$stok, status='$status' WHERE id_mobil=$id");
        $msg = "Data mobil diperbarui!"; $msg_type = "success"; $tab = 'mobil';
    } elseif ($aksi == 'hapus') {
        $id = (int)$_POST['id_mobil'];
        mysqli_query($conn, "DELETE FROM mobil WHERE id_mobil=$id");
        $msg = "Mobil dihapus!"; $msg_type = "warning"; $tab = 'mobil';
    }
}

// =============================================
// AKSI PENYEWA
// =============================================
if (isset($_POST['aksi_penyewa'])) {
    $aksi = $_POST['aksi_penyewa'];
    if ($aksi == 'tambah') {
        $nama    = mysqli_real_escape_string($conn, $_POST['nama_penyewa']);
        $no_hp   = mysqli_real_escape_string($conn, $_POST['no_hp']);
        $alamat  = mysqli_real_escape_string($conn, $_POST['alamat']);
        $nik     = mysqli_real_escape_string($conn, $_POST['nik']);
        mysqli_query($conn, "INSERT INTO penyewa (nama_penyewa, no_hp, alamat, nik) VALUES ('$nama','$no_hp','$alamat','$nik')");
        $msg = "Penyewa berhasil ditambahkan!"; $msg_type = "success"; $tab = 'penyewa';
    } elseif ($aksi == 'hapus') {
        $id = (int)$_POST['id_penyewa'];
        mysqli_query($conn, "DELETE FROM penyewa WHERE id_penyewa=$id");
        $msg = "Penyewa dihapus!"; $msg_type = "warning"; $tab = 'penyewa';
    }
}

// =============================================
// AKSI SEWA
// =============================================
if (isset($_POST['aksi_sewa'])) {
    $aksi = $_POST['aksi_sewa'];
    if ($aksi == 'tambah') {
        $id_mobil   = (int)$_POST['id_mobil'];
        $id_penyewa = (int)$_POST['id_penyewa'];
        $tgl_mulai  = mysqli_real_escape_string($conn, $_POST['tgl_mulai']);
        $tgl_selesai= mysqli_real_escape_string($conn, $_POST['tgl_selesai']);
        $id_user    = $_SESSION['id_user'];
        mysqli_query($conn, "CALL proc_tambah_sewa($id_user, $id_mobil, $id_penyewa, '$tgl_mulai', '$tgl_selesai')");
        $msg = "Transaksi sewa berhasil!"; $msg_type = "success"; $tab = 'sewa';
    } elseif ($aksi == 'selesai') {
        $id_sewa  = (int)$_POST['id_sewa'];
        $id_mobil = (int)$_POST['id_mobil'];
        mysqli_query($conn, "UPDATE sewa SET status='selesai' WHERE id_sewa=$id_sewa");
        mysqli_query($conn, "UPDATE mobil SET stok=stok+1, status='tersedia' WHERE id_mobil=$id_mobil");
        $msg = "Sewa selesai, stok mobil dikembalikan!"; $msg_type = "success"; $tab = 'sewa';
    } elseif ($aksi == 'batal') {
        $id_sewa  = (int)$_POST['id_sewa'];
        $id_mobil = (int)$_POST['id_mobil'];
        mysqli_query($conn, "UPDATE sewa SET status='dibatalkan' WHERE id_sewa=$id_sewa");
        mysqli_query($conn, "UPDATE mobil SET stok=stok+1, status='tersedia' WHERE id_mobil=$id_mobil");
        $msg = "Sewa dibatalkan!"; $msg_type = "warning"; $tab = 'sewa';
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// =============================================
// AMBIL DATA
// =============================================
$daftar_mobil   = mysqli_query($conn, "SELECT * FROM mobil ORDER BY id_mobil DESC");
$daftar_penyewa = mysqli_query($conn, "SELECT * FROM penyewa ORDER BY id_penyewa DESC");
$daftar_sewa    = mysqli_query($conn, "
    SELECT s.*, m.nama_mobil, m.merek, p.nama_penyewa, u.username
    FROM sewa s
    JOIN mobil m ON s.id_mobil = m.id_mobil
    JOIN penyewa p ON s.id_penyewa = p.id_penyewa
    LEFT JOIN users u ON s.id_user = u.id_user
    ORDER BY s.created_at DESC
");

// Statistik
$stat_mobil   = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM mobil"));
$stat_tersedia= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM mobil WHERE status='tersedia'"))['c'];
$stat_penyewa = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM penyewa"));
$stat_aktif   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM sewa WHERE status='aktif'"))['c'];
$stat_pendapatan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_bayar) as total FROM sewa WHERE status='selesai'"))['total'];
if (!$stat_pendapatan) $stat_pendapatan = 0;

// Data untuk modal edit mobil
$edit_mobil = null;
if (isset($_GET['edit_mobil'])) {
    $edit_id = (int)$_GET['edit_mobil'];
    $r = mysqli_query($conn, "SELECT * FROM mobil WHERE id_mobil=$edit_id");
    $edit_mobil = mysqli_fetch_assoc($r);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Rental Mobil</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    :root {
      --bg: #0a0f1e;
      --surface: #111827;
      --surface2: #1a2236;
      --border: rgba(255,255,255,0.07);
      --accent: #f97316;
      --accent2: #fb923c;
      --blue: #3b82f6;
      --green: #22c55e;
      --red: #ef4444;
      --yellow: #eab308;
      --text: #f1f5f9;
      --muted: #64748b;
      --sidebar-w: 240px;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
    }

    /* SIDEBAR */
    .sidebar {
      width: var(--sidebar-w);
      min-height: 100vh;
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      position: fixed;
      left: 0; top: 0;
      z-index: 100;
    }
    .sidebar-brand {
      padding: 1.5rem 1.25rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .brand-icon {
      width: 36px; height: 36px;
      background: var(--accent);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
    }
    .brand-name {
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      line-height: 1.2;
    }
    .brand-sub { font-size: 11px; color: var(--muted); font-weight: 400; }

    .sidebar-nav { flex: 1; padding: 1rem 0.75rem; }
    .nav-label {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.1em;
      color: var(--muted);
      text-transform: uppercase;
      padding: 0.5rem 0.5rem 0.25rem;
      margin-top: 0.5rem;
    }
    .nav-item {
      display: flex; align-items: center;
      gap: 0.65rem;
      padding: 0.65rem 0.75rem;
      border-radius: 8px;
      color: #94a3b8;
      font-size: 13.5px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.15s;
      cursor: pointer;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      margin-bottom: 2px;
    }
    .nav-item:hover { background: var(--surface2); color: var(--text); }
    .nav-item.active { background: rgba(249,115,22,0.15); color: var(--accent); }
    .nav-item i { font-size: 17px; }

    .sidebar-footer {
      padding: 1rem 0.75rem;
      border-top: 1px solid var(--border);
    }
    .user-info {
      display: flex; align-items: center; gap: 0.65rem;
      padding: 0.65rem 0.75rem;
      border-radius: 8px;
      background: var(--surface2);
    }
    .user-avatar {
      width: 32px; height: 32px;
      background: var(--accent);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Syne', sans-serif;
      font-size: 13px;
      font-weight: 700;
    }
    .user-name { font-size: 13px; font-weight: 500; }
    .user-role { font-size: 11px; color: var(--muted); }
    .logout-btn {
      display: flex; align-items: center; gap: 0.65rem;
      padding: 0.65rem 0.75rem;
      border-radius: 8px;
      color: var(--red);
      font-size: 13.5px;
      font-weight: 500;
      text-decoration: none;
      margin-top: 0.5rem;
      transition: background 0.15s;
    }
    .logout-btn:hover { background: rgba(239,68,68,0.1); }

    /* MAIN */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .topbar {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky; top: 0; z-index: 50;
    }
    .page-title {
      font-family: 'Syne', sans-serif;
      font-size: 18px;
      font-weight: 700;
    }
    .topbar-right { display: flex; align-items: center; gap: 1rem; }
    .date-badge {
      font-size: 12px;
      color: var(--muted);
      background: var(--surface2);
      padding: 0.4rem 0.75rem;
      border-radius: 6px;
    }

    .content { padding: 2rem; }

    /* ALERT */
    .alert {
      padding: 0.85rem 1rem;
      border-radius: 10px;
      font-size: 13.5px;
      margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 0.65rem;
    }
    .alert-success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.25); color: #4ade80; }
    .alert-warning { background: rgba(234,179,8,0.12); border: 1px solid rgba(234,179,8,0.25); color: #facc15; }

    /* STATS */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 1.25rem;
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
    }
    .stat-card.orange::before { background: var(--accent); }
    .stat-card.blue::before { background: var(--blue); }
    .stat-card.green::before { background: var(--green); }
    .stat-card.yellow::before { background: var(--yellow); }
    .stat-label { font-size: 11px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.5rem; }
    .stat-value { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 700; line-height: 1; }
    .stat-icon { font-size: 28px; opacity: 0.15; position: absolute; right: 1rem; bottom: 0.75rem; }
    .stat-card.orange .stat-value { color: var(--accent); }
    .stat-card.blue .stat-value { color: var(--blue); }
    .stat-card.green .stat-value { color: var(--green); }
    .stat-card.yellow .stat-value { color: var(--yellow); }

    /* SECTION */
    .section-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      margin-bottom: 1.5rem;
      overflow: hidden;
    }
    .section-header {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .section-title {
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
      display: flex; align-items: center; gap: 0.5rem;
    }

    /* FORM */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; padding: 1.25rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-label { font-size: 12px; font-weight: 500; color: #94a3b8; }
    .form-control {
      height: 40px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0 0.85rem;
      color: var(--text);
      font-size: 13.5px;
      font-family: 'DM Sans', sans-serif;
      outline: none;
      transition: border-color 0.15s;
      width: 100%;
    }
    .form-control:focus { border-color: var(--accent); }
    select.form-control { cursor: pointer; }
    textarea.form-control { height: 80px; padding: 0.65rem 0.85rem; resize: none; }
    .form-footer { padding: 0 1.25rem 1.25rem; display: flex; gap: 0.75rem; justify-content: flex-end; }

    /* BUTTONS */
    .btn {
      display: inline-flex; align-items: center; gap: 0.4rem;
      padding: 0 1rem;
      height: 36px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.15s;
      text-decoration: none;
    }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { background: var(--accent2); }
    .btn-sm { height: 30px; padding: 0 0.75rem; font-size: 12px; }
    .btn-danger { background: rgba(239,68,68,0.15); color: var(--red); border: 1px solid rgba(239,68,68,0.2); }
    .btn-danger:hover { background: rgba(239,68,68,0.25); }
    .btn-success { background: rgba(34,197,94,0.15); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
    .btn-success:hover { background: rgba(34,197,94,0.25); }
    .btn-warn { background: rgba(234,179,8,0.15); color: var(--yellow); border: 1px solid rgba(234,179,8,0.2); }
    .btn-warn:hover { background: rgba(234,179,8,0.25); }

    /* TABLE */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    thead th {
      text-align: left;
      padding: 0.75rem 1.25rem;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    tbody td {
      padding: 0.85rem 1.25rem;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: rgba(255,255,255,0.02); }

    /* BADGES */
    .badge {
      display: inline-flex; align-items: center;
      padding: 0.2rem 0.6rem;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
    }
    .badge-green { background: rgba(34,197,94,0.15); color: var(--green); }
    .badge-red { background: rgba(239,68,68,0.15); color: var(--red); }
    .badge-yellow { background: rgba(234,179,8,0.15); color: var(--yellow); }
    .badge-blue { background: rgba(59,130,246,0.15); color: var(--blue); }
    .badge-gray { background: rgba(100,116,139,0.15); color: var(--muted); }

    /* TAB CONTENT */
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* EMPTY STATE */
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: var(--muted);
    }
    .empty-state i { font-size: 40px; margin-bottom: 0.75rem; display: block; opacity: 0.4; }
    .empty-state p { font-size: 13.5px; }

    /* MONEY */
    .money { font-family: 'Syne', sans-serif; font-size: 13px; }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🚗</div>
    <div>
      <div class="brand-name">RentAuto</div>
      <div class="brand-sub">Admin Panel</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>
    <a href="?tab=dashboard" class="nav-item <?= $tab=='dashboard'?'active':'' ?>">
      <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a href="?tab=mobil" class="nav-item <?= $tab=='mobil'?'active':'' ?>">
      <i class="ti ti-car"></i> Data Mobil
    </a>
    <a href="?tab=penyewa" class="nav-item <?= $tab=='penyewa'?'active':'' ?>">
      <i class="ti ti-users"></i> Data Penyewa
    </a>
    <a href="?tab=sewa" class="nav-item <?= $tab=='sewa'?'active':'' ?>">
      <i class="ti ti-file-invoice"></i> Transaksi Sewa
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
        <div class="user-role">Administrator</div>
      </div>
    </div>
    <a href="?logout=1" class="logout-btn">
      <i class="ti ti-logout"></i> Keluar
    </a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div class="page-title">
      <?php
        $titles = ['dashboard'=>'Dashboard','mobil'=>'Data Mobil','penyewa'=>'Data Penyewa','sewa'=>'Transaksi Sewa'];
        echo $titles[$tab] ?? 'Dashboard';
      ?>
    </div>
    <div class="topbar-right">
      <span class="date-badge"><i class="ti ti-calendar"></i> <?= date('d M Y') ?></span>
    </div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>">
      <i class="ti ti-<?= $msg_type=='success'?'check':'alert-triangle' ?>"></i>
      <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- ==================== DASHBOARD ==================== -->
    <div class="tab-content <?= $tab=='dashboard'?'active':'' ?>">
      <div class="stats-grid">
        <div class="stat-card orange">
          <div class="stat-label">Total Mobil</div>
          <div class="stat-value"><?= $stat_mobil ?></div>
          <i class="ti ti-car stat-icon"></i>
        </div>
        <div class="stat-card green">
          <div class="stat-label">Mobil Tersedia</div>
          <div class="stat-value"><?= $stat_tersedia ?></div>
          <i class="ti ti-check stat-icon"></i>
        </div>
        <div class="stat-card blue">
          <div class="stat-label">Total Penyewa</div>
          <div class="stat-value"><?= $stat_penyewa ?></div>
          <i class="ti ti-users stat-icon"></i>
        </div>
        <div class="stat-card yellow">
          <div class="stat-label">Sewa Aktif</div>
          <div class="stat-value"><?= $stat_aktif ?></div>
          <i class="ti ti-file-invoice stat-icon"></i>
        </div>
      </div>

      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-trending-up"></i> Total Pendapatan</div>
        </div>
        <div style="padding:1.5rem; text-align:center;">
          <div style="font-family:'Syne',sans-serif; font-size:42px; font-weight:800; color:var(--green);">
            Rp <?= number_format($stat_pendapatan,0,',','.') ?>
          </div>
          <div style="color:var(--muted); font-size:13px; margin-top:0.25rem;">Dari transaksi yang telah selesai</div>
        </div>
      </div>

      <!-- Transaksi Terakhir -->
      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-clock"></i> Transaksi Terbaru</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Penyewa</th>
                <th>Mobil</th>
                <th>Tgl Mulai</th>
                <th>Tgl Selesai</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sewa_baru = mysqli_query($conn, "SELECT s.*, m.nama_mobil, p.nama_penyewa FROM sewa s JOIN mobil m ON s.id_mobil=m.id_mobil JOIN penyewa p ON s.id_penyewa=p.id_penyewa ORDER BY s.created_at DESC LIMIT 5");
              if (mysqli_num_rows($sewa_baru) == 0): ?>
              <tr><td colspan="6"><div class="empty-state"><i class="ti ti-file-off"></i><p>Belum ada transaksi</p></div></td></tr>
              <?php else: while($r = mysqli_fetch_assoc($sewa_baru)): ?>
              <tr>
                <td><?= htmlspecialchars($r['nama_penyewa']) ?></td>
                <td><?= htmlspecialchars($r['nama_mobil']) ?></td>
                <td><?= date('d/m/Y', strtotime($r['tgl_mulai'])) ?></td>
                <td><?= date('d/m/Y', strtotime($r['tgl_selesai'])) ?></td>
                <td class="money">Rp <?= number_format($r['total_bayar'],0,',','.') ?></td>
                <td>
                  <?php if($r['status']=='aktif'): ?>
                    <span class="badge badge-blue">Aktif</span>
                  <?php elseif($r['status']=='selesai'): ?>
                    <span class="badge badge-green">Selesai</span>
                  <?php else: ?>
                    <span class="badge badge-red">Dibatalkan</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== MOBIL ==================== -->
    <div class="tab-content <?= $tab=='mobil'?'active':'' ?>">
      <!-- Form Tambah/Edit -->
      <div class="section-card">
        <div class="section-header">
          <div class="section-title">
            <i class="ti ti-<?= $edit_mobil?'pencil':'plus' ?>"></i>
            <?= $edit_mobil ? 'Edit Mobil' : 'Tambah Mobil' ?>
          </div>
        </div>
        <form method="POST" action="?tab=mobil">
          <?php if ($edit_mobil): ?>
            <input type="hidden" name="id_mobil" value="<?= $edit_mobil['id_mobil'] ?>">
            <input type="hidden" name="aksi_mobil" value="edit">
          <?php else: ?>
            <input type="hidden" name="aksi_mobil" value="tambah">
          <?php endif; ?>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Nama Mobil</label>
              <input type="text" name="nama_mobil" class="form-control" placeholder="Avanza" required
                     value="<?= $edit_mobil ? htmlspecialchars($edit_mobil['nama_mobil']) : '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Merek</label>
              <input type="text" name="merek" class="form-control" placeholder="Toyota" required
                     value="<?= $edit_mobil ? htmlspecialchars($edit_mobil['merek']) : '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Tahun</label>
              <input type="number" name="tahun" class="form-control" placeholder="2023" required min="1990" max="2030"
                     value="<?= $edit_mobil ? $edit_mobil['tahun'] : date('Y') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Harga / Hari (Rp)</label>
              <input type="number" name="harga_per_hari" class="form-control" placeholder="350000" required min="0"
                     value="<?= $edit_mobil ? $edit_mobil['harga_per_hari'] : '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Stok Unit</label>
              <input type="number" name="stok" class="form-control" placeholder="3" required min="0"
                     value="<?= $edit_mobil ? $edit_mobil['stok'] : '1' ?>">
            </div>
          </div>
          <div class="form-footer">
            <?php if ($edit_mobil): ?>
              <a href="?tab=mobil" class="btn btn-sm" style="background:var(--surface2);color:var(--muted);">Batal</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="ti ti-<?= $edit_mobil?'check':'plus' ?>"></i>
              <?= $edit_mobil ? 'Simpan Perubahan' : 'Tambah Mobil' ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Tabel Mobil -->
      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-list"></i> Daftar Mobil</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Nama Mobil</th>
                <th>Merek</th>
                <th>Tahun</th>
                <th>Harga/Hari</th>
                <th>Stok</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              mysqli_data_seek($daftar_mobil, 0);
              $no = 1;
              if (mysqli_num_rows($daftar_mobil) == 0): ?>
              <tr><td colspan="8"><div class="empty-state"><i class="ti ti-car-off"></i><p>Belum ada data mobil</p></div></td></tr>
              <?php else: while($m = mysqli_fetch_assoc($daftar_mobil)): ?>
              <tr>
                <td style="color:var(--muted)"><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($m['nama_mobil']) ?></td>
                <td><?= htmlspecialchars($m['merek']) ?></td>
                <td><?= $m['tahun'] ?></td>
                <td class="money">Rp <?= number_format($m['harga_per_hari'],0,',','.') ?></td>
                <td><strong><?= $m['stok'] ?></strong></td>
                <td>
                  <?php if($m['status']=='tersedia'): ?>
                    <span class="badge badge-green">Tersedia</span>
                  <?php else: ?>
                    <span class="badge badge-red">Habis</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                  <a href="?tab=mobil&edit_mobil=<?= $m['id_mobil'] ?>" class="btn btn-sm btn-warn">
                    <i class="ti ti-pencil"></i> Edit
                  </a>
                  <form method="POST" action="?tab=mobil" style="display:inline;" onsubmit="return confirm('Hapus mobil ini?')">
                    <input type="hidden" name="id_mobil" value="<?= $m['id_mobil'] ?>">
                    <input type="hidden" name="aksi_mobil" value="hapus">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i> Hapus</button>
                  </form>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== PENYEWA ==================== -->
    <div class="tab-content <?= $tab=='penyewa'?'active':'' ?>">
      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-user-plus"></i> Tambah Penyewa</div>
        </div>
        <form method="POST" action="?tab=penyewa">
          <input type="hidden" name="aksi_penyewa" value="tambah">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Nama Penyewa</label>
              <input type="text" name="nama_penyewa" class="form-control" placeholder="Budi Santoso" required>
            </div>
            <div class="form-group">
              <label class="form-label">NIK</label>
              <input type="text" name="nik" class="form-control" placeholder="3578xxxxxxxxxxxx">
            </div>
            <div class="form-group">
              <label class="form-label">No. HP</label>
              <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Alamat</label>
              <textarea name="alamat" class="form-control" placeholder="Jl. Contoh No. 1, Surabaya"></textarea>
            </div>
          </div>
          <div class="form-footer">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="ti ti-plus"></i> Tambah Penyewa
            </button>
          </div>
        </form>
      </div>

      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-users"></i> Daftar Penyewa</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Nama Penyewa</th>
                <th>NIK</th>
                <th>No. HP</th>
                <th>Alamat</th>
                <th>Terdaftar</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 1;
              if (mysqli_num_rows($daftar_penyewa) == 0): ?>
              <tr><td colspan="7"><div class="empty-state"><i class="ti ti-user-off"></i><p>Belum ada data penyewa</p></div></td></tr>
              <?php else: while($p = mysqli_fetch_assoc($daftar_penyewa)): ?>
              <tr>
                <td style="color:var(--muted)"><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($p['nama_penyewa']) ?></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['nik']) ?></td>
                <td><?= htmlspecialchars($p['no_hp']) ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['alamat']) ?></td>
                <td style="color:var(--muted);font-size:12px"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                <td>
                  <form method="POST" action="?tab=penyewa" style="display:inline;" onsubmit="return confirm('Hapus penyewa ini?')">
                    <input type="hidden" name="id_penyewa" value="<?= $p['id_penyewa'] ?>">
                    <input type="hidden" name="aksi_penyewa" value="hapus">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i> Hapus</button>
                  </form>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== SEWA ==================== -->
    <div class="tab-content <?= $tab=='sewa'?'active':'' ?>">
      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-file-plus"></i> Tambah Transaksi Sewa</div>
        </div>
        <form method="POST" action="?tab=sewa">
          <input type="hidden" name="aksi_sewa" value="tambah">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Pilih Mobil</label>
              <select name="id_mobil" class="form-control" required onchange="hitungTotal()">
                <option value="">-- Pilih Mobil --</option>
                <?php
                $qm = mysqli_query($conn, "SELECT * FROM mobil WHERE status='tersedia' ORDER BY nama_mobil");
                while($m = mysqli_fetch_assoc($qm)):
                ?>
                <option value="<?= $m['id_mobil'] ?>" data-harga="<?= $m['harga_per_hari'] ?>">
                  <?= htmlspecialchars($m['nama_mobil']) ?> (<?= $m['merek'] ?>) — Rp <?= number_format($m['harga_per_hari'],0,',','.') ?>/hari
                </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Pilih Penyewa</label>
              <select name="id_penyewa" class="form-control" required>
                <option value="">-- Pilih Penyewa --</option>
                <?php
                $qp = mysqli_query($conn, "SELECT * FROM penyewa ORDER BY nama_penyewa");
                while($p = mysqli_fetch_assoc($qp)):
                ?>
                <option value="<?= $p['id_penyewa'] ?>"><?= htmlspecialchars($p['nama_penyewa']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Tanggal Mulai</label>
              <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control" required
                     value="<?= date('Y-m-d') ?>" onchange="hitungTotal()">
            </div>
            <div class="form-group">
              <label class="form-label">Tanggal Selesai</label>
              <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control" required
                     value="<?= date('Y-m-d', strtotime('+1 day')) ?>" onchange="hitungTotal()">
            </div>
          </div>
          <div style="padding:0 1.25rem 1rem;">
            <div id="preview-total" style="background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:0.75rem 1rem; display:none;">
              <span style="color:var(--muted);font-size:12px;">Estimasi Total:</span>
              <span id="total-text" style="font-family:'Syne',sans-serif; font-size:18px; font-weight:700; color:var(--green); margin-left:0.5rem;"></span>
            </div>
          </div>
          <div class="form-footer">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="ti ti-check"></i> Buat Transaksi
            </button>
          </div>
        </form>
      </div>

      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-list"></i> Daftar Transaksi</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Penyewa</th>
                <th>Mobil</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Hari</th>
                <th>Total</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $no = 1;
              if (mysqli_num_rows($daftar_sewa) == 0): ?>
              <tr><td colspan="9"><div class="empty-state"><i class="ti ti-file-off"></i><p>Belum ada transaksi</p></div></td></tr>
              <?php else: while($s = mysqli_fetch_assoc($daftar_sewa)): ?>
              <tr>
                <td style="color:var(--muted)"><?= $no++ ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($s['nama_penyewa']) ?></td>
                <td><?= htmlspecialchars($s['nama_mobil']) ?> <span style="color:var(--muted);font-size:11px"><?= $s['merek'] ?></span></td>
                <td><?= date('d/m/Y', strtotime($s['tgl_mulai'])) ?></td>
                <td><?= date('d/m/Y', strtotime($s['tgl_selesai'])) ?></td>
                <td><?= $s['total_hari'] ?> hari</td>
                <td class="money">Rp <?= number_format($s['total_bayar'],0,',','.') ?></td>
                <td>
                  <?php if($s['status']=='aktif'): ?>
                    <span class="badge badge-blue">Aktif</span>
                  <?php elseif($s['status']=='selesai'): ?>
                    <span class="badge badge-green">Selesai</span>
                  <?php else: ?>
                    <span class="badge badge-red">Dibatalkan</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                  <?php if($s['status']=='aktif'): ?>
                  <form method="POST" action="?tab=sewa" style="display:inline;" onsubmit="return confirm('Tandai sebagai selesai?')">
                    <input type="hidden" name="aksi_sewa" value="selesai">
                    <input type="hidden" name="id_sewa" value="<?= $s['id_sewa'] ?>">
                    <input type="hidden" name="id_mobil" value="<?= $s['id_mobil'] ?>">
                    <button type="submit" class="btn btn-sm btn-success"><i class="ti ti-check"></i> Selesai</button>
                  </form>
                  <form method="POST" action="?tab=sewa" style="display:inline;" onsubmit="return confirm('Batalkan sewa ini?')">
                    <input type="hidden" name="aksi_sewa" value="batal">
                    <input type="hidden" name="id_sewa" value="<?= $s['id_sewa'] ?>">
                    <input type="hidden" name="id_mobil" value="<?= $s['id_mobil'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-x"></i> Batal</button>
                  </form>
                  <?php else: ?>
                  <span style="color:var(--muted);font-size:12px">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</main>

<script>
function hitungTotal() {
  const sel = document.querySelector('select[name="id_mobil"]');
  const opt = sel.options[sel.selectedIndex];
  const harga = parseFloat(opt?.dataset?.harga || 0);
  const tgl1 = document.getElementById('tgl_mulai')?.value;
  const tgl2 = document.getElementById('tgl_selesai')?.value;
  const div = document.getElementById('preview-total');
  const span = document.getElementById('total-text');

  if (!harga || !tgl1 || !tgl2) { div.style.display='none'; return; }
  const d1 = new Date(tgl1), d2 = new Date(tgl2);
  const hari = Math.ceil((d2 - d1) / (1000*60*60*24));
  if (hari <= 0) { div.style.display='none'; return; }
  const total = harga * hari;
  span.textContent = 'Rp ' + total.toLocaleString('id-ID') + ' (' + hari + ' hari)';
  div.style.display = 'block';
}
hitungTotal();
</script>

</body>
</html>
