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

// Filter & pencarian
$search  = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$filter  = isset($_GET['filter']) ? $_GET['filter'] : 'semua';
$sort    = isset($_GET['sort']) ? $_GET['sort'] : 'nama';

$where = "WHERE 1=1";
if ($search) $where .= " AND (nama_mobil LIKE '%$search%' OR merek LIKE '%$search%')";
if ($filter == 'tersedia') $where .= " AND status='tersedia' AND stok > 0";
if ($filter == 'habis')    $where .= " AND (status='habis' OR stok <= 0)";

$order = "ORDER BY ";
if ($sort == 'harga_asc')  $order .= "harga_per_hari ASC";
elseif ($sort == 'harga_desc') $order .= "harga_per_hari DESC";
elseif ($sort == 'tahun')  $order .= "tahun DESC";
else $order .= "nama_mobil ASC";

$daftar_mobil = mysqli_query($conn, "SELECT * FROM mobil $where $order");
$total_mobil  = mysqli_num_rows($daftar_mobil);

$stat_tersedia = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM mobil WHERE status='tersedia' AND stok>0"))['c'];
$stat_habis    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM mobil WHERE status='habis' OR stok<=0"))['c'];
$stat_semua    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM mobil"))['c'];

// Logout
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RentAuto — Katalog Mobil</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    :root {
      --bg: #f8f5f0;
      --card: #ffffff;
      --border: #e8e2d9;
      --accent: #c84b31;
      --accent-dark: #a33a23;
      --accent-light: #fdf0ec;
      --dark: #1a1612;
      --mid: #6b635a;
      --muted: #a09890;
      --green: #2d6a4f;
      --green-bg: #eaf4ee;
      --blue: #1d4ed8;
      --blue-bg: #eff6ff;
      --red: #b91c1c;
      --red-bg: #fef2f2;
      --sidebar: 260px;
      --radius: 14px;
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
    .topbar { background:#fff; border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; gap:1rem; }
    .page-ttl { font-size:17px; font-weight:700; white-space:nowrap; }
    .content { padding:2rem; }

    /* SEARCH BAR */
    .search-bar {
      display: flex; gap:0.75rem; align-items:center;
      background:var(--card); border:1.5px solid var(--border); border-radius:10px;
      padding:0 1rem; height:42px; flex:1; max-width:360px;
    }
    .search-bar i { color:var(--muted); font-size:18px; flex-shrink:0; }
    .search-bar input { border:none; outline:none; font-family:'Outfit',sans-serif; font-size:14px; color:var(--dark); background:transparent; flex:1; width:100%; }
    .search-bar input::placeholder { color:var(--muted); }

    /* FILTER CHIPS */
    .filter-row { display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .chip {
      display:inline-flex; align-items:center; gap:0.4rem;
      padding:0.4rem 1rem; border-radius:20px;
      font-size:13px; font-weight:600;
      border:1.5px solid var(--border);
      color:var(--mid); background:#fff;
      text-decoration:none; transition:all 0.15s;
      cursor:pointer;
    }
    .chip:hover { border-color:var(--accent); color:var(--accent); }
    .chip.active { background:var(--accent); border-color:var(--accent); color:#fff; }
    .chip-count { font-size:10px; background:rgba(0,0,0,0.08); padding:1px 6px; border-radius:10px; }
    .chip.active .chip-count { background:rgba(255,255,255,0.25); }

    /* SORT */
    .sort-select { height:38px; border:1.5px solid var(--border); border-radius:9px; padding:0 0.75rem; font-family:'Outfit',sans-serif; font-size:13px; color:var(--dark); background:#fff; outline:none; cursor:pointer; }
    .sort-select:focus { border-color:var(--accent); }

    /* GRID */
    .catalog-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(290px,1fr)); gap:1.25rem; }

    /* CARD */
    .car-card {
      background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
      overflow:hidden; transition:box-shadow 0.2s, transform 0.2s;
      display:flex; flex-direction:column;
    }
    .car-card:hover { box-shadow:0 8px 30px rgba(0,0,0,0.1); transform:translateY(-3px); }
    .car-card.unavailable { opacity:0.65; }

    .car-img {
      height:160px;
      background:linear-gradient(135deg,#1a1612 0%,#2d2520 100%);
      display:flex; align-items:center; justify-content:center;
      font-size:80px; position:relative; overflow:hidden;
    }
    .car-img::after {
      content:''; position:absolute; inset:0;
      background:radial-gradient(circle at 30% 70%, rgba(200,75,49,0.15) 0%, transparent 60%);
    }
    .car-badge {
      position:absolute; top:12px; right:12px;
      padding:0.25rem 0.7rem; border-radius:20px;
      font-size:11px; font-weight:700; z-index:1;
    }
    .car-badge.avail { background:var(--green-bg); color:var(--green); }
    .car-badge.unavail { background:var(--red-bg); color:var(--red); }
    .stok-badge {
      position:absolute; top:12px; left:12px;
      padding:0.2rem 0.6rem; border-radius:8px;
      font-size:10px; font-weight:700; z-index:1;
      background:rgba(26,22,18,0.75); color:#fff; backdrop-filter:blur(4px);
    }

    .car-body { padding:1.25rem; flex:1; display:flex; flex-direction:column; }
    .car-name { font-size:17px; font-weight:700; margin-bottom:2px; }
    .car-meta { font-size:12px; color:var(--muted); margin-bottom:0.75rem; display:flex; align-items:center; gap:0.4rem; }
    .car-meta-dot { width:3px; height:3px; border-radius:50%; background:var(--muted); }

    .car-specs { display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap; }
    .spec-tag {
      display:inline-flex; align-items:center; gap:0.3rem;
      padding:0.25rem 0.6rem; border-radius:6px;
      background:#f5f3f0; font-size:11px; color:var(--mid); font-weight:500;
    }
    .spec-tag i { font-size:13px; }

    .car-price {
      font-family:'Space Mono',monospace; font-size:20px; font-weight:700;
      color:var(--accent); margin-bottom:0.25rem;
    }
    .car-price span { font-size:11px; color:var(--muted); font-family:'Outfit',sans-serif; font-weight:400; }

    .car-actions { display:flex; gap:0.5rem; margin-top:auto; padding-top:1rem; }

    /* BUTTONS */
    .btn { display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; padding:0 1.1rem; height:40px; border-radius:9px; font-size:13.5px; font-weight:600; border:none; cursor:pointer; font-family:'Outfit',sans-serif; transition:all 0.15s; text-decoration:none; }
    .btn-primary { background:var(--accent); color:#fff; flex:1; }
    .btn-primary:hover { background:var(--accent-dark); }
    .btn-outline { background:transparent; border:1.5px solid var(--border); color:var(--mid); }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); }
    .btn-disabled { background:var(--border); color:var(--muted); flex:1; cursor:not-allowed; }
    .btn-icon { width:40px; padding:0; flex-shrink:0; }

    /* MODAL */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(26,22,18,0.7); z-index:999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--card); border-radius:18px; width:100%; max-width:540px; max-height:92vh; overflow-y:auto; box-shadow:0 24px 64px rgba(0,0,0,0.2); animation:modalIn 0.25s ease; }
    @keyframes modalIn { from{opacity:0;transform:translateY(20px) scale(0.97)} to{opacity:1;transform:translateY(0) scale(1)} }
    .modal-header { padding:1.5rem 1.5rem 1rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .modal-title { font-size:18px; font-weight:700; }
    .modal-close { width:34px; height:34px; border-radius:8px; border:1px solid var(--border); background:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:16px; transition:all 0.15s; }
    .modal-close:hover { background:var(--red-bg); color:var(--red); border-color:#fecaca; }
    .modal-body { padding:1.5rem; }
    .modal-car-info { background:var(--accent-light); border:1px solid #f5c5b8; border-radius:12px; padding:1.1rem 1.25rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem; }
    .modal-car-icon { font-size:36px; }
    .modal-car-name { font-size:15px; font-weight:700; }
    .modal-car-sub { font-size:12px; color:var(--muted); margin-top:2px; }
    .modal-car-price { font-family:'Space Mono',monospace; font-size:14px; color:var(--accent); font-weight:700; margin-top:4px; }

    .form-group { margin-bottom:1rem; }
    .form-label { display:block; font-size:12.5px; font-weight:600; color:var(--mid); margin-bottom:5px; text-transform:uppercase; letter-spacing:0.04em; }
    .form-control { width:100%; height:44px; border:1.5px solid var(--border); border-radius:9px; padding:0 0.9rem; font-family:'Outfit',sans-serif; font-size:14px; color:var(--dark); outline:none; transition:border-color 0.15s, background 0.15s; background:#fff; }
    .form-control:focus { border-color:var(--accent); background:var(--accent-light); }
    textarea.form-control { height:80px; padding:0.65rem 0.9rem; resize:none; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-divider { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); padding:0.5rem 0; border-bottom:1px solid var(--border); margin-bottom:1rem; }

    .total-box { background:var(--green-bg); border:1.5px solid #b7dfc8; border-radius:12px; padding:1.1rem 1.25rem; text-align:center; margin-bottom:1rem; display:none; }
    .total-box-label { font-size:11px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:4px; }
    .total-box-val { font-family:'Space Mono',monospace; font-size:24px; font-weight:700; color:var(--green); }
    .total-box-detail { font-size:12px; color:var(--mid); margin-top:3px; }

    .modal-footer { padding:0 1.5rem 1.5rem; display:flex; gap:0.75rem; }

    /* EMPTY */
    .empty-state { text-align:center; padding:4rem 2rem; color:var(--muted); }
    .empty-state i { font-size:56px; display:block; margin-bottom:1rem; opacity:0.25; }
    .empty-state h3 { font-size:16px; font-weight:700; color:var(--dark); margin-bottom:0.5rem; }
    .empty-state p { font-size:14px; }

    /* RESULT INFO */
    .result-info { font-size:13px; color:var(--muted); margin-bottom:1.25rem; }
    .result-info strong { color:var(--dark); }

    /* TOAST */
    .toast {
      position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
      padding:0.85rem 1.25rem; border-radius:12px; font-size:14px; font-weight:600;
      display:flex; align-items:center; gap:0.65rem;
      box-shadow:0 8px 24px rgba(0,0,0,0.15);
      animation:toastIn 0.3s ease;
    }
    @keyframes toastIn { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .toast-success { background:#fff; border-left:4px solid var(--green); color:var(--green); }
    .toast-error { background:#fff; border-left:4px solid var(--red); color:var(--red); }
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
    <a href="lihat_mobil.php" class="nav-item active">
      <i class="ti ti-car"></i> Katalog Mobil
    </a>
    <a href="sewa_mobil.php" class="nav-item">
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
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="page-ttl">🚗 Katalog Mobil</div>
    <form method="GET" action="lihat_mobil.php" style="display:flex;gap:0.75rem;align-items:center;flex:1;max-width:500px;margin:0 auto;">
      <div class="search-bar" style="flex:1;max-width:100%;">
        <i class="ti ti-search"></i>
        <input type="text" name="q" placeholder="Cari nama atau merek mobil..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    </form>
    <div style="font-size:13px;color:var(--muted);white-space:nowrap;">
      <i class="ti ti-calendar"></i> <?= date('d M Y') ?>
    </div>
  </div>

  <div class="content">

    <!-- FILTER ROW -->
    <div class="filter-row">
      <a href="?filter=semua&sort=<?= $sort ?>&q=<?= urlencode($search) ?>" class="chip <?= $filter=='semua'?'active':'' ?>">
        Semua <span class="chip-count"><?= $stat_semua ?></span>
      </a>
      <a href="?filter=tersedia&sort=<?= $sort ?>&q=<?= urlencode($search) ?>" class="chip <?= $filter=='tersedia'?'active':'' ?>">
        <i class="ti ti-check" style="font-size:13px;"></i> Tersedia <span class="chip-count"><?= $stat_tersedia ?></span>
      </a>
      <a href="?filter=habis&sort=<?= $sort ?>&q=<?= urlencode($search) ?>" class="chip <?= $filter=='habis'?'active':'' ?>">
        <i class="ti ti-ban" style="font-size:13px;"></i> Habis <span class="chip-count"><?= $stat_habis ?></span>
      </a>
      <div style="margin-left:auto;display:flex;align-items:center;gap:0.5rem;">
        <span style="font-size:12px;color:var(--muted);">Urutkan:</span>
        <select class="sort-select" onchange="applySort(this.value)">
          <option value="nama"      <?= $sort=='nama'?'selected':'' ?>>Nama A–Z</option>
          <option value="harga_asc" <?= $sort=='harga_asc'?'selected':'' ?>>Harga Terendah</option>
          <option value="harga_desc"<?= $sort=='harga_desc'?'selected':'' ?>>Harga Tertinggi</option>
          <option value="tahun"     <?= $sort=='tahun'?'selected':'' ?>>Tahun Terbaru</option>
        </select>
      </div>
    </div>

    <!-- RESULT INFO -->
    <div class="result-info">
      Menampilkan <strong><?= $total_mobil ?></strong> mobil
      <?php if($search): ?> untuk pencarian "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
      <?php if($filter=='tersedia'): ?> · hanya yang <strong>tersedia</strong><?php endif; ?>
    </div>

    <!-- GRID -->
    <?php if($total_mobil == 0): ?>
    <div class="empty-state">
      <i class="ti ti-car-off"></i>
      <h3>Tidak Ada Mobil Ditemukan</h3>
      <p>Coba ubah kata kunci pencarian atau filter yang digunakan.</p>
      <a href="lihat_mobil.php" class="btn btn-outline" style="margin-top:1rem;display:inline-flex;">Reset Filter</a>
    </div>
    <?php else: ?>
    <div class="catalog-grid">
      <?php
      $icons = ['🚗','🚙','🏎️','🚕','🛻','🚐'];
      $i = 0;
      while($m = mysqli_fetch_assoc($daftar_mobil)):
        $icon = $icons[$i % count($icons)]; $i++;
        $tersedia = ($m['status']=='tersedia' && $m['stok'] > 0);
      ?>
      <div class="car-card <?= !$tersedia ? 'unavailable' : '' ?>">
        <div class="car-img">
          <?= $icon ?>
          <span class="stok-badge"><i class="ti ti-stack-2" style="font-size:11px;"></i> <?= $m['stok'] ?> unit</span>
          <?php if($tersedia): ?>
          <span class="car-badge avail"><i class="ti ti-check" style="font-size:10px;"></i> Tersedia</span>
          <?php else: ?>
          <span class="car-badge unavail"><i class="ti ti-ban" style="font-size:10px;"></i> Habis</span>
          <?php endif; ?>
        </div>
        <div class="car-body">
          <div class="car-name"><?= htmlspecialchars($m['nama_mobil']) ?></div>
          <div class="car-meta">
            <?= htmlspecialchars($m['merek']) ?>
            <span class="car-meta-dot"></span>
            <?= $m['tahun'] ?>
          </div>
          <div class="car-specs">
            <span class="spec-tag"><i class="ti ti-calendar"></i> <?= $m['tahun'] ?></span>
            <span class="spec-tag"><i class="ti ti-stack-2"></i> Stok <?= $m['stok'] ?></span>
            <?php if($tersedia): ?>
            <span class="spec-tag" style="background:var(--green-bg);color:var(--green);"><i class="ti ti-circle-check"></i> Ready</span>
            <?php else: ?>
            <span class="spec-tag" style="background:var(--red-bg);color:var(--red);"><i class="ti ti-circle-x"></i> Kosong</span>
            <?php endif; ?>
          </div>
          <div class="car-price">
            Rp <?= number_format($m['harga_per_hari'],0,',','.') ?>
            <span>/ hari</span>
          </div>
          <div class="car-actions">
            <?php if($tersedia): ?>
            <button class="btn btn-primary"
              onclick="openModal(<?= $m['id_mobil'] ?>,'<?= addslashes($m['nama_mobil']) ?>','<?= addslashes($m['merek']) ?>',<?= $m['tahun'] ?>,<?= $m['harga_per_hari'] ?>,<?= $m['stok'] ?>)">
              <i class="ti ti-car"></i> Sewa Sekarang
            </button>
            <a href="sewa_mobil.php?id=<?= $m['id_mobil'] ?>" class="btn btn-icon btn-outline" title="Form Sewa Lengkap">
              <i class="ti ti-external-link"></i>
            </a>
            <?php else: ?>
            <button class="btn btn-disabled" disabled>
              <i class="ti ti-ban"></i> Tidak Tersedia
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>

  </div>
</main>

<!-- MODAL SEWA -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🚗 Pesan Mobil</div>
      <button class="modal-close" onclick="closeModal()"><i class="ti ti-x"></i></button>
    </div>
    <form method="POST" action="sewa_mobil.php">
      <input type="hidden" name="aksi" value="pesan">
      <input type="hidden" name="id_mobil" id="modal_id_mobil">
      <div class="modal-body">
        <!-- Info Mobil -->
        <div class="modal-car-info">
          <div class="modal-car-icon" id="modal_icon">🚗</div>
          <div style="flex:1;">
            <div class="modal-car-name" id="modal_car_name">—</div>
            <div class="modal-car-sub" id="modal_car_sub">—</div>
            <div class="modal-car-price" id="modal_car_price">—</div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:11px;color:var(--muted);">Stok</div>
            <div style="font-size:20px;font-weight:800;" id="modal_stok">—</div>
            <div style="font-size:10px;color:var(--muted);">unit</div>
          </div>
        </div>

        <div class="form-divider">📋 Data Diri Penyewa</div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama_penyewa" class="form-control" placeholder="Sesuai KTP" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">NIK / No. KTP</label>
            <input type="text" name="nik" class="form-control" placeholder="16 digit NIK" maxlength="16">
          </div>
          <div class="form-group">
            <label class="form-label">No. HP / WA</label>
            <input type="text" name="no_hp" class="form-control" placeholder="08xx-xxxx-xxxx" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Alamat Lengkap</label>
          <textarea name="alamat" class="form-control" placeholder="Jalan, Kecamatan, Kota" required></textarea>
        </div>

        <div class="form-divider">📅 Tanggal Sewa</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" id="m_tgl_mulai" class="form-control"
                   value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required onchange="hitungTotal()">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" id="m_tgl_selesai" class="form-control"
                   value="<?= date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required onchange="hitungTotal()">
          </div>
        </div>

        <div class="total-box" id="totalBox">
          <div class="total-box-label">Estimasi Total Biaya</div>
          <div class="total-box-val" id="totalVal">—</div>
          <div class="total-box-detail" id="totalDetail">—</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal()" class="btn btn-outline" style="flex:1;">Batal</button>
        <button type="submit" class="btn btn-primary" style="flex:2;">
          <i class="ti ti-check"></i> Konfirmasi Pemesanan
        </button>
      </div>
    </form>
  </div>
</div>

<?php
// Tampilkan toast jika ada pesan dari redirect
if (isset($_GET['msg'])): ?>
<div class="toast toast-<?= $_GET['type']??'success' ?>" id="toast">
  <i class="ti ti-<?= ($_GET['type']??'success')=='success'?'circle-check':'alert-circle' ?>"></i>
  <?= htmlspecialchars($_GET['msg']) ?>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t)t.style.opacity='0'; }, 3500);</script>
<?php endif; ?>

<script>
let modalHarga = 0;
const icons = ['🚗','🚙','🏎️','🚕','🛻','🚐'];

function openModal(id, nama, merek, tahun, harga, stok) {
  modalHarga = harga;
  document.getElementById('modal_id_mobil').value = id;
  document.getElementById('modal_car_name').textContent = nama;
  document.getElementById('modal_car_sub').textContent = merek + ' · ' + tahun;
  document.getElementById('modal_car_price').textContent = 'Rp ' + parseInt(harga).toLocaleString('id-ID') + ' / hari';
  document.getElementById('modal_stok').textContent = stok;
  document.getElementById('modal_icon').textContent = icons[id % icons.length];
  document.getElementById('modalOverlay').classList.add('open');
  hitungTotal();
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

function hitungTotal() {
  const t1 = document.getElementById('m_tgl_mulai').value;
  const t2 = document.getElementById('m_tgl_selesai').value;
  const box = document.getElementById('totalBox');
  if (!t1 || !t2 || !modalHarga) { box.style.display='none'; return; }
  const hari = Math.ceil((new Date(t2) - new Date(t1)) / 86400000);
  if (hari <= 0) { box.style.display='none'; return; }
  const total = modalHarga * hari;
  document.getElementById('totalVal').textContent = 'Rp ' + total.toLocaleString('id-ID');
  document.getElementById('totalDetail').textContent = hari + ' hari × Rp ' + parseInt(modalHarga).toLocaleString('id-ID') + '/hari';
  box.style.display = 'block';
}

function applySort(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('sort', val);
  window.location.href = url.toString();
}
</script>
</body>
</html>
