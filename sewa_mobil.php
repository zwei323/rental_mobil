<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php"); exit();
}
if ($_SESSION['role'] == 'admin') {
    header("Location: admin.php"); exit();
}

$conn = mysqli_connect("localhost", "root", "zwei1", "rental_mobil");
if (!$conn) die("Koneksi gagal: " . mysqli_connect_error());

$id_user = $_SESSION['id_user'];
$msg = ''; $msg_type = '';

// =============================================
// AKSI: PROSES PEMESANAN
// =============================================
if (isset($_POST['aksi']) && $_POST['aksi'] == 'pesan') {
    $nama_penyewa = mysqli_real_escape_string($conn, $_POST['nama_penyewa']);
    $nik          = mysqli_real_escape_string($conn, $_POST['nik']);
    $no_hp        = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $alamat       = mysqli_real_escape_string($conn, $_POST['alamat']);
    $id_mobil     = (int)$_POST['id_mobil'];
    $tgl_mulai    = mysqli_real_escape_string($conn, $_POST['tgl_mulai']);
    $tgl_selesai  = mysqli_real_escape_string($conn, $_POST['tgl_selesai']);

    // Validasi tanggal
    if ($tgl_selesai <= $tgl_mulai) {
        $msg = "Tanggal selesai harus lebih dari tanggal mulai."; $msg_type = "error";
    } else {
        // Cek stok
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stok, status FROM mobil WHERE id_mobil=$id_mobil"));
        if (!$cek || $cek['stok'] <= 0 || $cek['status'] == 'habis') {
            $msg = "Maaf, mobil ini sudah tidak tersedia."; $msg_type = "error";
        } else {
            // Simpan penyewa
            mysqli_query($conn, "INSERT INTO penyewa (nama_penyewa, no_hp, alamat, nik) VALUES ('$nama_penyewa','$no_hp','$alamat','$nik')");
            $id_penyewa = mysqli_insert_id($conn);
            // Panggil procedure
            $call = mysqli_query($conn, "CALL proc_tambah_sewa($id_user, $id_mobil, $id_penyewa, '$tgl_mulai', '$tgl_selesai')");
            if ($call) {
                header("Location: lihat_mobil.php?msg=" . urlencode("Pemesanan berhasil! Mobil berhasil dipesan.") . "&type=success");
                exit();
            } else {
                $msg = "Gagal memproses pemesanan. Coba lagi."; $msg_type = "error";
            }
        }
    }
}

// Logout
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit(); }

// Ambil id_mobil dari GET (dari tombol "Sewa" di lihat_mobil)
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_mobil = null;
if ($selected_id > 0) {
    $r = mysqli_query($conn, "SELECT * FROM mobil WHERE id_mobil=$selected_id");
    $selected_mobil = mysqli_fetch_assoc($r);
}

// Data untuk dropdown
$daftar_mobil_tersedia = mysqli_query($conn, "SELECT * FROM mobil WHERE status='tersedia' AND stok > 0 ORDER BY nama_mobil ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RentAuto — Form Sewa Mobil</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    :root {
      --bg:#f8f5f0; --card:#fff; --border:#e8e2d9;
      --accent:#c84b31; --accent-dark:#a33a23; --accent-light:#fdf0ec;
      --dark:#1a1612; --mid:#6b635a; --muted:#a09890;
      --green:#2d6a4f; --green-bg:#eaf4ee;
      --blue:#1d4ed8; --blue-bg:#eff6ff;
      --red:#b91c1c; --red-bg:#fef2f2;
      --yellow:#92400e; --yellow-bg:#fffbeb;
      --sidebar:260px; --radius:14px;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--dark); min-height:100vh; display:flex; }

    /* SIDEBAR */
    .sidebar { width:var(--sidebar); min-height:100vh; background:var(--dark); display:flex; flex-direction:column; position:fixed; left:0; top:0; overflow-y:auto; z-index:100; }
    .sidebar-brand { padding:1.75rem 1.5rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.07); }
    .brand-logo { font-size:22px; font-weight:800; color:#fff; letter-spacing:-0.02em; display:flex; align-items:center; gap:0.5rem; }
    .brand-dot { color:var(--accent); }
    .brand-tagline { font-size:11px; color:rgba(255,255,255,0.35); margin-top:2px; }
    .sidebar-user { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; align-items:center; gap:0.75rem; }
    .user-av { width:40px; height:40px; border-radius:50%; background:var(--accent); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:15px; color:#fff; flex-shrink:0; }
    .user-nm { font-size:14px; font-weight:600; color:#fff; }
    .user-rl { font-size:11px; color:rgba(255,255,255,0.4); }
    .sidebar-nav { flex:1; padding:1.25rem 1rem; }
    .nav-label { font-size:10px; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:rgba(255,255,255,0.25); padding:0 0.5rem; margin-bottom:0.4rem; margin-top:1rem; }
    .nav-item { display:flex; align-items:center; gap:0.65rem; padding:0.65rem 0.75rem; border-radius:10px; color:rgba(255,255,255,0.55); font-size:14px; font-weight:500; text-decoration:none; transition:all 0.15s; margin-bottom:2px; }
    .nav-item:hover { background:rgba(255,255,255,0.07); color:#fff; }
    .nav-item.active { background:var(--accent); color:#fff; }
    .nav-item i { font-size:18px; flex-shrink:0; }
    .sidebar-footer { padding:1.25rem 1rem; border-top:1px solid rgba(255,255,255,0.07); }
    .logout-link { display:flex; align-items:center; gap:0.65rem; padding:0.65rem 0.75rem; border-radius:10px; color:rgba(255,255,255,0.4); font-size:13.5px; text-decoration:none; transition:all 0.15s; }
    .logout-link:hover { color:var(--accent); background:rgba(200,75,49,0.1); }

    /* MAIN */
    .main { margin-left:var(--sidebar); flex:1; }
    .topbar { background:#fff; border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
    .page-ttl { font-size:17px; font-weight:700; }
    .content { padding:2rem; max-width:900px; }

    /* ALERT */
    .alert { padding:1rem 1.25rem; border-radius:var(--radius); font-size:14px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.65rem; }
    .alert-success { background:var(--green-bg); border:1px solid #b7dfc8; color:var(--green); }
    .alert-error { background:var(--red-bg); border:1px solid #fecaca; color:var(--red); }

    /* LAYOUT */
    .form-layout { display:grid; grid-template-columns:1fr 360px; gap:1.5rem; align-items:start; }
    @media(max-width:900px) { .form-layout { grid-template-columns:1fr; } }

    /* CARD */
    .card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .card-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:0.65rem; }
    .card-header-icon { width:36px; height:36px; border-radius:9px; background:var(--accent-light); display:flex; align-items:center; justify-content:center; font-size:18px; color:var(--accent); flex-shrink:0; }
    .card-title { font-size:15px; font-weight:700; }
    .card-sub { font-size:12px; color:var(--muted); margin-top:1px; }
    .card-body { padding:1.5rem; }

    /* FORM */
    .form-group { margin-bottom:1.1rem; }
    .form-label { display:block; font-size:12.5px; font-weight:600; color:var(--mid); margin-bottom:5px; text-transform:uppercase; letter-spacing:0.04em; }
    .form-control { width:100%; height:44px; border:1.5px solid var(--border); border-radius:9px; padding:0 0.9rem; font-family:'Outfit',sans-serif; font-size:14px; color:var(--dark); outline:none; transition:border-color 0.15s, background 0.15s; background:#fff; }
    .form-control:focus { border-color:var(--accent); background:var(--accent-light); }
    textarea.form-control { height:90px; padding:0.75rem 0.9rem; resize:none; }
    select.form-control { cursor:pointer; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-hint { font-size:11.5px; color:var(--muted); margin-top:4px; }

    /* STEP DIVIDER */
    .step-divider {
      display:flex; align-items:center; gap:0.75rem;
      font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;
      color:var(--muted); margin:1.25rem 0 1rem;
    }
    .step-divider::before, .step-divider::after { content:''; flex:1; height:1px; background:var(--border); }
    .step-num { width:22px; height:22px; border-radius:50%; background:var(--accent); color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

    /* MOBIL PREVIEW CARD */
    .mobil-preview {
      background:linear-gradient(135deg,#1a1612 0%,#2d2520 100%);
      border-radius:var(--radius); padding:1.5rem; margin-bottom:1.25rem; color:#fff; position:relative; overflow:hidden;
    }
    .mobil-preview::before {
      content:''; position:absolute; inset:0;
      background:radial-gradient(circle at 80% 20%, rgba(200,75,49,0.3) 0%, transparent 60%);
    }
    .preview-icon { font-size:64px; margin-bottom:0.75rem; display:block; position:relative; z-index:1; }
    .preview-name { font-size:20px; font-weight:800; position:relative; z-index:1; margin-bottom:2px; }
    .preview-meta { font-size:13px; color:rgba(255,255,255,0.55); position:relative; z-index:1; margin-bottom:1rem; }
    .preview-price { font-family:'Space Mono',monospace; font-size:22px; font-weight:700; color:var(--accent); position:relative; z-index:1; }
    .preview-price span { font-size:12px; color:rgba(255,255,255,0.4); font-family:'Outfit',sans-serif; font-weight:400; }
    .preview-stok { position:absolute; top:1rem; right:1rem; background:rgba(255,255,255,0.1); backdrop-filter:blur(4px); border-radius:8px; padding:0.4rem 0.75rem; font-size:12px; font-weight:600; z-index:1; }

    .no-mobil-msg { text-align:center; padding:2rem; color:var(--muted); }
    .no-mobil-msg i { font-size:40px; display:block; opacity:0.3; margin-bottom:0.75rem; }
    .no-mobil-msg p { font-size:13px; }

    /* SUMMARY BOX */
    .summary-box { background:var(--green-bg); border:1.5px solid #b7dfc8; border-radius:12px; padding:1.25rem; margin-bottom:1.25rem; display:none; }
    .summary-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--mid); margin-bottom:0.75rem; }
    .summary-row { display:flex; justify-content:space-between; font-size:13px; margin-bottom:0.4rem; color:var(--mid); }
    .summary-total { display:flex; justify-content:space-between; font-size:16px; font-weight:700; padding-top:0.6rem; border-top:1px solid #b7dfc8; margin-top:0.6rem; color:var(--green); }
    .summary-total-val { font-family:'Space Mono',monospace; }

    /* BADGE */
    .badge-avail { display:inline-flex; align-items:center; gap:4px; background:var(--green-bg); color:var(--green); padding:0.25rem 0.7rem; border-radius:20px; font-size:12px; font-weight:700; }
    .badge-habis { display:inline-flex; align-items:center; gap:4px; background:var(--red-bg); color:var(--red); padding:0.25rem 0.7rem; border-radius:20px; font-size:12px; font-weight:700; }

    /* BUTTONS */
    .btn { display:inline-flex; align-items:center; justify-content:center; gap:0.5rem; padding:0 1.25rem; height:44px; border-radius:10px; font-size:14px; font-weight:600; border:none; cursor:pointer; font-family:'Outfit',sans-serif; transition:all 0.15s; text-decoration:none; }
    .btn-primary { background:var(--accent); color:#fff; width:100%; }
    .btn-primary:hover { background:var(--accent-dark); }
    .btn-outline { background:transparent; border:1.5px solid var(--border); color:var(--mid); width:100%; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }

    /* TIPS */
    .tips-box { background:var(--yellow-bg); border:1px solid #fcd34d; border-radius:10px; padding:1rem 1.1rem; margin-top:1rem; }
    .tips-title { font-size:12px; font-weight:700; color:var(--yellow); margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem; }
    .tips-list { list-style:none; }
    .tips-list li { font-size:12px; color:var(--mid); padding:0.2rem 0; display:flex; align-items:flex-start; gap:0.4rem; }
    .tips-list li::before { content:'•'; color:var(--yellow); font-weight:700; flex-shrink:0; }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">Rent<span class="brand-dot">Auto</span></div>
    <div class="brand-tagline">Platform Sewa Mobil Online</div>
  </div>
  <div class="sidebar-user">
    <div class="user-av"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
    <div>
      <div class="user-nm"><?= htmlspecialchars($_SESSION['username']) ?></div>
      <div class="user-rl">Member</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Menu Utama</div>
    <a href="dasboard.php?tab=catalog" class="nav-item">
      <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a href="lihat_mobil.php" class="nav-item">
      <i class="ti ti-car"></i> Katalog Mobil
    </a>
    <a href="sewa_mobil.php" class="nav-item active">
      <i class="ti ti-file-plus"></i> Form Sewa
    </a>
    <a href="dasboard.php?tab=riwayat" class="nav-item">
      <i class="ti ti-clock-history"></i> Riwayat Sewa
    </a>
    <a href="dasboard.php?tab=sewa_admin" class="nav-item">
      <i class="ti ti-shield-check"></i> Sewa dari Admin
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="?logout=1" class="logout-link"><i class="ti ti-logout"></i> Keluar</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <a href="lihat_mobil.php" style="color:var(--muted);text-decoration:none;font-size:22px;line-height:1;display:flex;align-items:center;"><i class="ti ti-arrow-left"></i></a>
      <div class="page-ttl">📋 Form Sewa Mobil</div>
    </div>
    <div style="font-size:13px;color:var(--muted);"><i class="ti ti-calendar"></i> <?= date('d M Y') ?></div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>">
      <i class="ti ti-<?= $msg_type=='success'?'circle-check':'alert-circle' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="sewa_mobil.php" id="formSewa">
      <input type="hidden" name="aksi" value="pesan">

      <div class="form-layout">
        <!-- KIRI: FORM -->
        <div>
          <!-- PILIH MOBIL -->
          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <div class="card-header-icon"><i class="ti ti-car"></i></div>
              <div>
                <div class="card-title">Pilih Mobil</div>
                <div class="card-sub">Pilih dari stok yang tersedia dari admin</div>
              </div>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Daftar Mobil Tersedia</label>
                <select name="id_mobil" id="pilihMobil" class="form-control" required onchange="pilihanMobil()">
                  <option value="">-- Pilih Mobil --</option>
                  <?php
                  $icons_list = ['🚗','🚙','🏎️','🚕','🛻','🚐'];
                  $ki = 0;
                  mysqli_data_seek($daftar_mobil_tersedia, 0);
                  while($m = mysqli_fetch_assoc($daftar_mobil_tersedia)):
                    $ikon = $icons_list[$ki % count($icons_list)]; $ki++;
                    $sel = ($selected_id == $m['id_mobil']) ? 'selected' : '';
                  ?>
                  <option value="<?= $m['id_mobil'] ?>"
                    data-nama="<?= htmlspecialchars($m['nama_mobil']) ?>"
                    data-merek="<?= htmlspecialchars($m['merek']) ?>"
                    data-tahun="<?= $m['tahun'] ?>"
                    data-harga="<?= $m['harga_per_hari'] ?>"
                    data-stok="<?= $m['stok'] ?>"
                    data-icon="<?= $ikon ?>"
                    <?= $sel ?>>
                    <?= $ikon ?> <?= htmlspecialchars($m['nama_mobil']) ?> (<?= $m['merek'] ?>) — Rp <?= number_format($m['harga_per_hari'],0,',','.') ?>/hari · Stok: <?= $m['stok'] ?>
                  </option>
                  <?php endwhile; ?>
                </select>
                <?php if(mysqli_num_rows($daftar_mobil_tersedia) == 0): ?>
                <div class="form-hint" style="color:var(--red);"><i class="ti ti-alert-circle" style="font-size:13px;"></i> Tidak ada mobil tersedia saat ini. Hubungi admin.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- DATA PENYEWA -->
          <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
              <div class="card-header-icon"><i class="ti ti-user"></i></div>
              <div>
                <div class="card-title">Data Diri Penyewa</div>
                <div class="card-sub">Isi sesuai kartu identitas (KTP)</div>
              </div>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama_penyewa" class="form-control" placeholder="Nama sesuai KTP" required>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">NIK / No. KTP</label>
                  <input type="text" name="nik" class="form-control" placeholder="16 digit NIK" maxlength="16">
                  <div class="form-hint">Nomor KTP 16 digit</div>
                </div>
                <div class="form-group">
                  <label class="form-label">No. HP / WhatsApp</label>
                  <input type="text" name="no_hp" class="form-control" placeholder="08xx-xxxx-xxxx" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Alamat Lengkap</label>
                <textarea name="alamat" class="form-control" placeholder="Jalan, No. Rumah, Kelurahan, Kecamatan, Kota" required></textarea>
              </div>
            </div>
          </div>

          <!-- TANGGAL -->
          <div class="card">
            <div class="card-header">
              <div class="card-header-icon"><i class="ti ti-calendar-event"></i></div>
              <div>
                <div class="card-title">Periode Sewa</div>
                <div class="card-sub">Tentukan tanggal mulai dan selesai</div>
              </div>
            </div>
            <div class="card-body">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Tanggal Mulai</label>
                  <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control"
                         value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required onchange="hitungSummary()">
                </div>
                <div class="form-group">
                  <label class="form-label">Tanggal Selesai</label>
                  <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control"
                         value="<?= date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required onchange="hitungSummary()">
                </div>
              </div>
              <div class="form-hint" style="margin-top:-0.5rem;">
                <i class="ti ti-info-circle" style="font-size:13px;"></i>
                Pengembalian dilakukan sebelum pukul 10.00 pada tanggal selesai.
              </div>
            </div>
          </div>
        </div>

        <!-- KANAN: PREVIEW & SUMMARY -->
        <div>
          <!-- PREVIEW MOBIL -->
          <div class="card" style="margin-bottom:1.25rem; position:sticky; top:80px;">
            <div class="card-header">
              <div class="card-header-icon"><i class="ti ti-receipt"></i></div>
              <div>
                <div class="card-title">Ringkasan Pesanan</div>
                <div class="card-sub">Estimasi biaya sewa</div>
              </div>
            </div>
            <div class="card-body">
              <!-- Preview Mobil -->
              <div id="mobilPreviewWrap">
                <?php if($selected_mobil): ?>
                <div class="mobil-preview">
                  <span class="preview-stok">📦 <?= $selected_mobil['stok'] ?> unit</span>
                  <span class="preview-icon">🚗</span>
                  <div class="preview-name"><?= htmlspecialchars($selected_mobil['nama_mobil']) ?></div>
                  <div class="preview-meta"><?= htmlspecialchars($selected_mobil['merek']) ?> · <?= $selected_mobil['tahun'] ?></div>
                  <div class="preview-price">Rp <?= number_format($selected_mobil['harga_per_hari'],0,',','.') ?> <span>/ hari</span></div>
                </div>
                <?php else: ?>
                <div class="no-mobil-msg">
                  <i class="ti ti-car-off"></i>
                  <p>Pilih mobil terlebih dahulu<br>untuk melihat preview</p>
                </div>
                <?php endif; ?>
              </div>

              <!-- Summary Harga -->
              <div class="summary-box" id="summaryBox">
                <div class="summary-label">Rincian Biaya</div>
                <div class="summary-row">
                  <span>Harga/hari</span>
                  <span id="s_harga">—</span>
                </div>
                <div class="summary-row">
                  <span>Durasi</span>
                  <span id="s_hari">—</span>
                </div>
                <div class="summary-row">
                  <span>Mulai</span>
                  <span id="s_tgl1">—</span>
                </div>
                <div class="summary-row">
                  <span>Selesai</span>
                  <span id="s_tgl2">—</span>
                </div>
                <div class="summary-total">
                  <span>Total Bayar</span>
                  <span class="summary-total-val" id="s_total">—</span>
                </div>
              </div>

              <!-- Submit -->
              <button type="submit" class="btn btn-primary" id="btnSubmit">
                <i class="ti ti-check"></i> Konfirmasi Pemesanan
              </button>
              <a href="lihat_mobil.php" class="btn btn-outline" style="margin-top:0.65rem;">
                <i class="ti ti-arrow-left"></i> Kembali ke Katalog
              </a>

              <!-- Tips -->
              <div class="tips-box">
                <div class="tips-title"><i class="ti ti-bulb" style="font-size:14px;"></i> Informasi Sewa</div>
                <ul class="tips-list">
                  <li>Bawa KTP asli saat pengambilan mobil</li>
                  <li>Pembayaran dilakukan di tempat / transfer</li>
                  <li>Pengembalian sebelum pukul 10.00</li>
                  <li>Keterlambatan dikenakan biaya tambahan</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</main>

<script>
let hargaPerHari = 0;
const ikonList = ['🚗','🚙','🏎️','🚕','🛻','🚐'];

function pilihanMobil() {
  const sel = document.getElementById('pilihMobil');
  const opt = sel.options[sel.selectedIndex];
  const wrap = document.getElementById('mobilPreviewWrap');

  if (!opt || !opt.value) {
    wrap.innerHTML = `<div class="no-mobil-msg"><i class="ti ti-car-off"></i><p>Pilih mobil terlebih dahulu<br>untuk melihat preview</p></div>`;
    hargaPerHari = 0;
    document.getElementById('summaryBox').style.display = 'none';
    return;
  }

  hargaPerHari = parseFloat(opt.dataset.harga);
  const ikon = opt.dataset.icon || '🚗';
  const stok = opt.dataset.stok;
  const hargaFmt = 'Rp ' + parseInt(hargaPerHari).toLocaleString('id-ID');

  wrap.innerHTML = `
    <div class="mobil-preview">
      <span class="preview-stok">📦 ${stok} unit</span>
      <span class="preview-icon">${ikon}</span>
      <div class="preview-name">${opt.dataset.nama}</div>
      <div class="preview-meta">${opt.dataset.merek} · ${opt.dataset.tahun}</div>
      <div class="preview-price">${hargaFmt} <span>/ hari</span></div>
    </div>`;

  hitungSummary();
}

function hitungSummary() {
  if (!hargaPerHari) return;
  const t1 = document.getElementById('tgl_mulai').value;
  const t2 = document.getElementById('tgl_selesai').value;
  const box = document.getElementById('summaryBox');

  if (!t1 || !t2) { box.style.display='none'; return; }
  const hari = Math.ceil((new Date(t2) - new Date(t1)) / 86400000);
  if (hari <= 0) { box.style.display='none'; return; }

  const total = hargaPerHari * hari;
  const fmt = (n) => 'Rp ' + parseInt(n).toLocaleString('id-ID');

  const bulanId = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const fmtTgl = (str) => {
    const d = new Date(str);
    return d.getDate() + ' ' + bulanId[d.getMonth()] + ' ' + d.getFullYear();
  };

  document.getElementById('s_harga').textContent = fmt(hargaPerHari);
  document.getElementById('s_hari').textContent = hari + ' hari';
  document.getElementById('s_tgl1').textContent = fmtTgl(t1);
  document.getElementById('s_tgl2').textContent = fmtTgl(t2);
  document.getElementById('s_total').textContent = fmt(total);
  box.style.display = 'block';
}

// Jalankan saat load jika sudah ada mobil terpilih
window.addEventListener('DOMContentLoaded', () => {
  pilihanMobil();
  hitungSummary();
});
</script>
</body>
</html>
