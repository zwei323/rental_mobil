
<?php
session_start();

if (!isset($_SESSION['role'])) { header("Location: login.php"); exit(); }
if ($_SESSION['role'] == 'admin') { header("Location: admin.php"); exit(); }

$conn = mysqli_connect("localhost", "root", "zwei1", "rental_mobil");
if (!$conn) die("Koneksi gagal: " . mysqli_connect_error());

$id_user = $_SESSION['id_user'];

// =============================================
// BUAT TABEL & PROCEDURE JIKA BELUM ADA
// =============================================
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS mobil (
    id_mobil INT AUTO_INCREMENT PRIMARY KEY, nama_mobil VARCHAR(100), merek VARCHAR(100),
    tahun INT, harga_per_hari DECIMAL(15,2), stok INT DEFAULT 1,
    status ENUM('tersedia','habis') DEFAULT 'tersedia')");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS penyewa (
    id_penyewa INT AUTO_INCREMENT PRIMARY KEY, nama_penyewa VARCHAR(100),
    no_hp VARCHAR(20), alamat TEXT, nik VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS sewa (
    id_sewa INT AUTO_INCREMENT PRIMARY KEY, id_user INT, id_mobil INT, id_penyewa INT,
    tgl_mulai DATE, tgl_selesai DATE, total_hari INT, total_bayar DECIMAL(15,2),
    status ENUM('aktif','selesai','dibatalkan') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
mysqli_query($conn, "DROP PROCEDURE IF EXISTS proc_tambah_sewa");
mysqli_query($conn, "CREATE PROCEDURE proc_tambah_sewa(
    IN p_id_user INT, IN p_id_mobil INT, IN p_id_penyewa INT, IN p_tgl_mulai DATE, IN p_tgl_selesai DATE)
BEGIN
    DECLARE v_harga DECIMAL(15,2); DECLARE v_stok INT;
    DECLARE v_hari INT; DECLARE v_total DECIMAL(15,2);
    SELECT harga_per_hari, stok INTO v_harga, v_stok FROM mobil WHERE id_mobil = p_id_mobil;
    SET v_hari = DATEDIFF(p_tgl_selesai, p_tgl_mulai);
    SET v_total = v_harga * v_hari;
    IF v_stok > 0 THEN
        INSERT INTO sewa (id_user, id_mobil, id_penyewa, tgl_mulai, tgl_selesai, total_hari, total_bayar)
        VALUES (p_id_user, p_id_mobil, p_id_penyewa, p_tgl_mulai, p_tgl_selesai, v_hari, v_total);
        UPDATE mobil SET stok = stok - 1 WHERE id_mobil = p_id_mobil;
        UPDATE mobil SET status = IF(stok <= 0, 'habis', 'tersedia') WHERE id_mobil = p_id_mobil;
    END IF;
END");

$msg = ""; $msg_type = "";
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'catalog';

// =============================================
// AKSI: PROSES PEMESANAN
// =============================================
if (isset($_POST['aksi']) && $_POST['aksi'] == 'pesan') {
    $nama        = mysqli_real_escape_string($conn, $_POST['nama_penyewa']);
    $nik         = mysqli_real_escape_string($conn, $_POST['nik']);
    $no_hp       = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $alamat      = mysqli_real_escape_string($conn, $_POST['alamat']);
    $id_mobil    = (int)$_POST['id_mobil'];
    $tgl_mulai   = mysqli_real_escape_string($conn, $_POST['tgl_mulai']);
    $tgl_selesai = mysqli_real_escape_string($conn, $_POST['tgl_selesai']);

    if ($tgl_selesai <= $tgl_mulai) {
        $msg = "Tanggal selesai harus lebih dari tanggal mulai!"; $msg_type = "error"; $tab = 'sewa';
    } else {
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stok, status FROM mobil WHERE id_mobil=$id_mobil"));
        if (!$cek || $cek['stok'] <= 0 || $cek['status'] == 'habis') {
            $msg = "Maaf, mobil ini sudah tidak tersedia."; $msg_type = "error"; $tab = 'sewa';
        } else {
            mysqli_query($conn, "INSERT INTO penyewa (nama_penyewa, no_hp, alamat, nik) VALUES ('$nama','$no_hp','$alamat','$nik')");
            $id_penyewa = mysqli_insert_id($conn);
            mysqli_query($conn, "CALL proc_tambah_sewa($id_user, $id_mobil, $id_penyewa, '$tgl_mulai', '$tgl_selesai')");
            $msg = "Pemesanan berhasil! Mobil sudah dipesan."; $msg_type = "success"; $tab = 'riwayat';
        }
    }
}

// Logout
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit(); }

// =============================================
// FILTER & SEARCH UNTUK KATALOG
// =============================================
$search_q  = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$filter_st = isset($_GET['filter']) ? $_GET['filter'] : 'semua';
$sort_by   = isset($_GET['sort']) ? $_GET['sort'] : 'nama';

$where_catalog = "WHERE 1=1";
if ($search_q)           $where_catalog .= " AND (nama_mobil LIKE '%$search_q%' OR merek LIKE '%$search_q%')";
if ($filter_st=='tersedia') $where_catalog .= " AND status='tersedia' AND stok > 0";
if ($filter_st=='habis')    $where_catalog .= " AND (status='habis' OR stok <= 0)";

$order_catalog = "ORDER BY ";
if ($sort_by=='harga_asc')  $order_catalog .= "harga_per_hari ASC";
elseif ($sort_by=='harga_desc') $order_catalog .= "harga_per_hari DESC";
elseif ($sort_by=='tahun')  $order_catalog .= "tahun DESC";
else $order_catalog .= "nama_mobil ASC";

// =============================================
// AMBIL DATA
// =============================================
$daftar_mobil   = mysqli_query($conn, "SELECT * FROM mobil $where_catalog $order_catalog");
$total_katalog  = mysqli_num_rows($daftar_mobil);
$mobil_tersedia = mysqli_query($conn, "SELECT * FROM mobil WHERE status='tersedia' AND stok>0 ORDER BY nama_mobil ASC");

$stat_tersedia_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM mobil WHERE status='tersedia' AND stok>0"))['c'];
$stat_habis_count    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM mobil WHERE status='habis' OR stok<=0"))['c'];
$stat_semua_count    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM mobil"))['c'];

$riwayat_sewa = mysqli_query($conn, "
    SELECT s.*, m.nama_mobil, m.merek, m.harga_per_hari, p.nama_penyewa
    FROM sewa s JOIN mobil m ON s.id_mobil=m.id_mobil JOIN penyewa p ON s.id_penyewa=p.id_penyewa
    WHERE s.id_user=$id_user ORDER BY s.created_at DESC");

$stat_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM sewa WHERE id_user=$id_user"))['c'];
$stat_aktif   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM sewa WHERE id_user=$id_user AND status='aktif'"))['c'];
$stat_selesai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM sewa WHERE id_user=$id_user AND status='selesai'"))['c'];
$stat_bayar   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_bayar) t FROM sewa WHERE id_user=$id_user AND status='selesai'"))['t'] ?? 0;

$sewa_dari_admin = mysqli_query($conn, "
    SELECT s.*, m.nama_mobil, m.merek, m.harga_per_hari, p.nama_penyewa, p.no_hp,
           u.username AS dibuat_oleh
    FROM sewa s JOIN mobil m ON s.id_mobil=m.id_mobil JOIN penyewa p ON s.id_penyewa=p.id_penyewa
    LEFT JOIN user u ON s.id_user=u.id_user
    WHERE u.role='admin' ORDER BY s.created_at DESC");
$jumlah_sewa_admin = mysqli_num_rows($sewa_dari_admin);

// Pre-selected mobil dari GET ?sewa=id
$preselectMobil = isset($_GET['sewa']) ? (int)$_GET['sewa'] : 0;
if ($preselectMobil > 0) $tab = 'sewa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RentAuto — Dashboard</title>
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
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--dark);min-height:100vh;display:flex;}

    /* ── SIDEBAR ── */
    .sidebar{width:var(--sidebar);min-height:100vh;background:var(--dark);display:flex;flex-direction:column;position:fixed;left:0;top:0;overflow-y:auto;z-index:100;}
    .sidebar-brand{padding:1.75rem 1.5rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.07);}
    .brand-logo{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.02em;display:flex;align-items:center;gap:.5rem;}
    .brand-dot{color:var(--accent);}
    .brand-tagline{font-size:11px;color:rgba(255,255,255,0.35);margin-top:2px;}
    .sidebar-user{padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:.75rem;}
    .user-av{width:40px;height:40px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;flex-shrink:0;}
    .user-nm{font-size:14px;font-weight:600;color:#fff;}
    .user-rl{font-size:11px;color:rgba(255,255,255,0.4);}
    .sidebar-nav{flex:1;padding:1.25rem 1rem;}
    .nav-label{font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:0 .5rem;margin-bottom:.4rem;margin-top:1rem;}
    .nav-item{display:flex;align-items:center;gap:.65rem;padding:.65rem .75rem;border-radius:10px;color:rgba(255,255,255,0.55);font-size:14px;font-weight:500;text-decoration:none;transition:all .15s;margin-bottom:2px;cursor:pointer;}
    .nav-item:hover{background:rgba(255,255,255,0.07);color:#fff;}
    .nav-item.active{background:var(--accent);color:#fff;}
    .nav-item i{font-size:18px;flex-shrink:0;}
    .nav-badge{margin-left:auto;background:var(--accent);color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;}
    .nav-item.active .nav-badge{background:rgba(255,255,255,0.3);}
    .sidebar-footer{padding:1.25rem 1rem;border-top:1px solid rgba(255,255,255,0.07);}
    .logout-link{display:flex;align-items:center;gap:.65rem;padding:.65rem .75rem;border-radius:10px;color:rgba(255,255,255,0.4);font-size:13.5px;text-decoration:none;transition:all .15s;}
    .logout-link:hover{color:var(--accent);background:rgba(200,75,49,0.1);}

    /* ── MAIN ── */
    .main{margin-left:var(--sidebar);flex:1;}
    .topbar{background:#fff;border-bottom:1px solid var(--border);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;gap:1rem;}
    .page-ttl{font-size:17px;font-weight:700;white-space:nowrap;}
    .content{padding:2rem;}

    /* ── SEARCH BAR ── */
    .search-bar{display:flex;gap:.6rem;align-items:center;background:var(--card);border:1.5px solid var(--border);border-radius:10px;padding:0 1rem;height:40px;flex:1;max-width:340px;transition:border-color .15s;}
    .search-bar:focus-within{border-color:var(--accent);}
    .search-bar i{color:var(--muted);font-size:17px;flex-shrink:0;}
    .search-bar input{border:none;outline:none;font-family:'Outfit',sans-serif;font-size:13.5px;color:var(--dark);background:transparent;flex:1;width:100%;}
    .search-bar input::placeholder{color:var(--muted);}

    /* ── ALERT ── */
    .alert{padding:1rem 1.25rem;border-radius:var(--radius);font-size:14px;margin-bottom:1.5rem;display:flex;align-items:center;gap:.65rem;font-weight:500;}
    .alert-success{background:var(--green-bg);color:var(--green);border:1px solid #b7dfc8;}
    .alert-error{background:var(--red-bg);color:var(--red);border:1px solid #fecaca;}

    /* ── STATS ── */
    .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:1rem;margin-bottom:2rem;}
    .stat-box{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;}
    .stat-box-label{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem;}
    .stat-box-val{font-family:'Space Mono',monospace;font-size:26px;font-weight:700;}
    .stat-box.red .stat-box-val{color:var(--accent);}
    .stat-box.green .stat-box-val{color:var(--green);}
    .stat-box.blue .stat-box-val{color:var(--blue);}

    /* ── TAB ── */
    .tab-content{display:none;}
    .tab-content.active{display:block;}

    /* ── FILTER CHIPS ── */
    .filter-row{display:flex;align-items:center;gap:.65rem;margin-bottom:1.25rem;flex-wrap:wrap;}
    .chip{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .9rem;border-radius:20px;font-size:13px;font-weight:600;border:1.5px solid var(--border);color:var(--mid);background:#fff;text-decoration:none;transition:all .15s;}
    .chip:hover{border-color:var(--accent);color:var(--accent);}
    .chip.active{background:var(--accent);border-color:var(--accent);color:#fff;}
    .chip-count{font-size:10px;background:rgba(0,0,0,0.08);padding:1px 6px;border-radius:10px;}
    .chip.active .chip-count{background:rgba(255,255,255,0.25);}
    .sort-select{height:36px;border:1.5px solid var(--border);border-radius:9px;padding:0 .75rem;font-family:'Outfit',sans-serif;font-size:13px;color:var(--dark);background:#fff;outline:none;cursor:pointer;}
    .sort-select:focus{border-color:var(--accent);}
    .result-info{font-size:13px;color:var(--muted);margin-bottom:1.25rem;}
    .result-info strong{color:var(--dark);}

    /* ── CATALOG GRID ── */
    .catalog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(285px,1fr));gap:1.25rem;}
    .car-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:box-shadow .2s,transform .2s;display:flex;flex-direction:column;}
    .car-card:hover{box-shadow:0 8px 28px rgba(0,0,0,0.1);transform:translateY(-3px);}
    .car-card.unavailable{opacity:.6;}
    .car-img{height:155px;background:linear-gradient(135deg,#1a1612 0%,#2d2520 100%);display:flex;align-items:center;justify-content:center;font-size:78px;position:relative;overflow:hidden;}
    .car-img::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 70%,rgba(200,75,49,0.15) 0%,transparent 60%);}
    .car-badge{position:absolute;top:10px;right:10px;padding:.22rem .65rem;border-radius:20px;font-size:11px;font-weight:700;z-index:1;}
    .car-badge.avail{background:var(--green-bg);color:var(--green);}
    .car-badge.unavail{background:var(--red-bg);color:var(--red);}
    .stok-pill{position:absolute;top:10px;left:10px;background:rgba(26,22,18,0.75);color:#fff;backdrop-filter:blur(4px);padding:.2rem .6rem;border-radius:8px;font-size:10px;font-weight:700;z-index:1;}
    .car-body{padding:1.2rem;flex:1;display:flex;flex-direction:column;}
    .car-name{font-size:16px;font-weight:700;margin-bottom:2px;}
    .car-meta{font-size:12px;color:var(--muted);margin-bottom:.75rem;}
    .car-specs{display:flex;gap:.4rem;margin-bottom:.9rem;flex-wrap:wrap;}
    .spec-tag{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .55rem;border-radius:6px;background:#f5f3f0;font-size:11px;color:var(--mid);font-weight:500;}
    .spec-tag i{font-size:13px;}
    .car-price{font-family:'Space Mono',monospace;font-size:19px;font-weight:700;color:var(--accent);margin-bottom:.9rem;}
    .car-price span{font-size:11px;color:var(--muted);font-family:'Outfit',sans-serif;font-weight:400;}
    .car-actions{display:flex;gap:.5rem;margin-top:auto;}

    /* ── BUTTONS ── */
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:0 1.1rem;height:40px;border-radius:9px;font-size:13.5px;font-weight:600;border:none;cursor:pointer;font-family:'Outfit',sans-serif;transition:all .15s;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;flex:1;}
    .btn-primary:hover{background:var(--accent-dark);}
    .btn-outline{background:transparent;border:1.5px solid var(--border);color:var(--mid);}
    .btn-outline:hover{border-color:var(--accent);color:var(--accent);}
    .btn-disabled{background:var(--border);color:var(--muted);flex:1;cursor:not-allowed;}
    .btn-icon{width:40px;padding:0;flex-shrink:0;}
    .btn-full{width:100%;}
    .btn-lg{height:46px;font-size:15px;}

    /* ── FORM SEWA LAYOUT ── */
    .sewa-layout{display:grid;grid-template-columns:1fr 350px;gap:1.5rem;align-items:start;}
    .sewa-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:1.25rem;}
    .sewa-card:last-child{margin-bottom:0;}
    .sewa-card-header{padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.75rem;}
    .sewa-card-icon{width:34px;height:34px;border-radius:8px;background:var(--accent-light);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:17px;flex-shrink:0;}
    .sewa-card-title{font-size:14px;font-weight:700;}
    .sewa-card-sub{font-size:12px;color:var(--muted);margin-top:1px;}
    .sewa-card-body{padding:1.25rem 1.5rem;}

    /* ── FORM FIELDS ── */
    .form-group{margin-bottom:1rem;}
    .form-label{display:block;font-size:12px;font-weight:600;color:var(--mid);margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em;}
    .form-control{width:100%;height:44px;border:1.5px solid var(--border);border-radius:9px;padding:0 .9rem;font-family:'Outfit',sans-serif;font-size:14px;color:var(--dark);outline:none;transition:border-color .15s,background .15s;background:#fff;}
    .form-control:focus{border-color:var(--accent);background:var(--accent-light);}
    textarea.form-control{height:88px;padding:.7rem .9rem;resize:none;}
    select.form-control{cursor:pointer;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .form-hint{font-size:11.5px;color:var(--muted);margin-top:4px;}

    /* ── MOBIL PREVIEW (ringkasan kanan) ── */
    .mobil-dark-card{background:linear-gradient(135deg,#1a1612 0%,#2d2520 100%);border-radius:12px;padding:1.4rem;color:#fff;position:relative;overflow:hidden;margin-bottom:1.1rem;}
    .mobil-dark-card::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 80% 20%,rgba(200,75,49,0.28) 0%,transparent 60%);}
    .mdc-icon{font-size:60px;display:block;position:relative;z-index:1;margin-bottom:.6rem;}
    .mdc-name{font-size:18px;font-weight:800;position:relative;z-index:1;margin-bottom:2px;}
    .mdc-meta{font-size:12px;color:rgba(255,255,255,0.5);position:relative;z-index:1;margin-bottom:.9rem;}
    .mdc-price{font-family:'Space Mono',monospace;font-size:20px;font-weight:700;color:var(--accent);position:relative;z-index:1;}
    .mdc-price span{font-size:11px;color:rgba(255,255,255,0.4);font-family:'Outfit',sans-serif;font-weight:400;}
    .mdc-stok{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,0.1);backdrop-filter:blur(4px);border-radius:8px;padding:.3rem .7rem;font-size:11px;font-weight:600;z-index:1;}
    .no-car-placeholder{text-align:center;padding:2rem;color:var(--muted);}
    .no-car-placeholder i{font-size:40px;opacity:.25;display:block;margin-bottom:.75rem;}
    .no-car-placeholder p{font-size:13px;}

    /* ── RINGKASAN BOX ── */
    .summary-box{background:var(--green-bg);border:1.5px solid #b7dfc8;border-radius:12px;padding:1.1rem 1.2rem;margin-bottom:1rem;display:none;}
    .summary-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);margin-bottom:.7rem;}
    .summary-row{display:flex;justify-content:space-between;font-size:13px;color:var(--mid);padding:.25rem 0;}
    .summary-divider{height:1px;background:#b7dfc8;margin:.5rem 0;}
    .summary-total{display:flex;justify-content:space-between;font-size:15px;font-weight:700;color:var(--green);}
    .summary-total-val{font-family:'Space Mono',monospace;}

    /* ── TIPS ── */
    .tips-box{background:var(--yellow-bg);border:1px solid #fcd34d;border-radius:10px;padding:.9rem 1rem;margin-top:.9rem;}
    .tips-title{font-size:12px;font-weight:700;color:var(--yellow);margin-bottom:.5rem;display:flex;align-items:center;gap:.35rem;}
    .tips-list{list-style:none;}
    .tips-list li{font-size:12px;color:var(--mid);padding:.18rem 0;display:flex;align-items:flex-start;gap:.4rem;}
    .tips-list li::before{content:'•';color:var(--yellow);font-weight:700;flex-shrink:0;}

    /* ── TABLE ── */
    .section-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
    .section-header{padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .section-title{font-size:15px;font-weight:700;display:flex;align-items:center;gap:.5rem;}
    .table-wrap{overflow-x:auto;}
    table{width:100%;border-collapse:collapse;font-size:13.5px;}
    thead th{text-align:left;padding:.75rem 1.25rem;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap;background:#faf8f5;}
    tbody td{padding:.9rem 1.25rem;border-bottom:1px solid var(--border);vertical-align:middle;}
    tbody tr:last-child td{border-bottom:none;}
    tbody tr:hover{background:#faf8f5;}

    /* ── BADGE ── */
    .badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:20px;font-size:11px;font-weight:700;}
    .badge-green{background:var(--green-bg);color:var(--green);}
    .badge-blue{background:var(--blue-bg);color:var(--blue);}
    .badge-red{background:var(--red-bg);color:var(--red);}

    /* ── MODAL ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(26,22,18,0.72);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
    .modal-overlay.open{display:flex;}
    .modal{background:var(--card);border-radius:18px;width:100%;max-width:530px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,0.2);animation:modalIn .25s ease;}
    @keyframes modalIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
    .modal-header{padding:1.5rem 1.5rem 1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .modal-title{font-size:17px;font-weight:700;}
    .modal-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .15s;}
    .modal-close:hover{background:var(--red-bg);color:var(--red);border-color:#fecaca;}
    .modal-body{padding:1.5rem;}
    .modal-car-info{background:var(--accent-light);border:1px solid #f5c5b8;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:1rem;}
    .modal-car-icon{font-size:32px;}
    .modal-car-name{font-size:15px;font-weight:700;}
    .modal-car-price{font-size:13px;color:var(--accent);font-weight:600;margin-top:3px;}
    .modal-footer{padding:0 1.5rem 1.5rem;display:flex;gap:.75rem;}

    /* ── TOTAL PREVIEW ── */
    .total-preview{background:var(--green-bg);border:1.5px solid #b7dfc8;border-radius:10px;padding:1rem;text-align:center;margin-bottom:1rem;display:none;}
    .total-preview-label{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;margin-bottom:3px;}
    .total-preview-val{font-family:'Space Mono',monospace;font-size:22px;font-weight:700;color:var(--green);}
    .total-preview-detail{font-size:12px;color:var(--mid);margin-top:3px;}

    /* ── MISC ── */
    .money{font-family:'Space Mono',monospace;font-size:13px;}
    .empty-state{text-align:center;padding:3rem;color:var(--muted);}
    .empty-state i{font-size:48px;display:block;margin-bottom:.75rem;opacity:.3;}
    .empty-state p{font-size:14px;}
    .form-divider{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:.5rem 0;border-bottom:1px solid var(--border);margin-bottom:1rem;}
  </style>
</head>
<body>

<!-- ═══════════ SIDEBAR ═══════════ -->
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
    <div class="nav-label">Menu</div>
    <a href="?tab=catalog" class="nav-item <?= $tab=='catalog'?'active':'' ?>">
      <i class="ti ti-car"></i> Katalog Mobil
    </a>
    <a href="?tab=sewa" class="nav-item <?= $tab=='sewa'?'active':'' ?>">
      <i class="ti ti-file-plus"></i> Form Sewa
    </a>
    <a href="?tab=riwayat" class="nav-item <?= $tab=='riwayat'?'active':'' ?>">
      <i class="ti ti-clock-history"></i> Riwayat Sewa
      <?php if($stat_aktif>0): ?><span class="nav-badge"><?=$stat_aktif?></span><?php endif;?>
    </a>
    <a href="?tab=sewa_admin" class="nav-item <?= $tab=='sewa_admin'?'active':'' ?>">
      <i class="ti ti-shield-check"></i> Sewa dari Admin
      <?php if($jumlah_sewa_admin>0): ?><span class="nav-badge"><?=$jumlah_sewa_admin?></span><?php endif;?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="?logout=1" class="logout-link"><i class="ti ti-logout"></i> Keluar</a>
  </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<main class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="page-ttl">
      <?php
        if ($tab=='catalog')    echo '🚗 Katalog Mobil';
        elseif ($tab=='sewa')   echo '📋 Form Sewa Mobil';
        elseif ($tab=='riwayat')echo '🕐 Riwayat Sewa';
        else                    echo '🛡️ Sewa dari Admin';
      ?>
    </div>
    <!-- Search bar hanya muncul di tab catalog -->
    <?php if($tab=='catalog'): ?>
    <form method="GET" action="" style="display:flex;align-items:center;gap:.65rem;flex:1;max-width:420px;margin:0 1.5rem;">
      <input type="hidden" name="tab" value="catalog">
      <div class="search-bar" style="flex:1;max-width:100%;">
        <i class="ti ti-search"></i>
        <input type="text" name="q" placeholder="Cari nama atau merek mobil..." value="<?= htmlspecialchars($search_q) ?>">
      </div>
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_st) ?>">
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">
    </form>
    <?php endif; ?>
    <div style="font-size:13px;color:var(--muted);white-space:nowrap;"><i class="ti ti-calendar"></i> <?= date('d M Y') ?></div>
  </div>

  <div class="content">

    <!-- ALERT -->
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>">
      <i class="ti ti-<?= $msg_type=='success'?'circle-check':'alert-circle' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- STATS ROW -->
    <div class="stats-row">
      <div class="stat-box red">
        <div class="stat-box-label">Total Pemesanan</div>
        <div class="stat-box-val"><?= $stat_total ?></div>
      </div>
      <div class="stat-box blue">
        <div class="stat-box-label">Sedang Aktif</div>
        <div class="stat-box-val"><?= $stat_aktif ?></div>
      </div>
      <div class="stat-box green">
        <div class="stat-box-label">Selesai</div>
        <div class="stat-box-val"><?= $stat_selesai ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-box-label">Total Dibayar</div>
        <div class="stat-box-val" style="font-size:15px;color:var(--accent);">Rp <?= number_format($stat_bayar,0,',','.') ?></div>
      </div>
    </div>

    <!-- ══════════ TAB: KATALOG ══════════ -->
    <div class="tab-content <?= $tab=='catalog'?'active':'' ?>">

      <!-- Filter & Sort -->
      <div class="filter-row">
        <a href="?tab=catalog&filter=semua&sort=<?=$sort_by?>&q=<?=urlencode($search_q)?>" class="chip <?= $filter_st=='semua'?'active':'' ?>">
          Semua <span class="chip-count"><?= $stat_semua_count ?></span>
        </a>
        <a href="?tab=catalog&filter=tersedia&sort=<?=$sort_by?>&q=<?=urlencode($search_q)?>" class="chip <?= $filter_st=='tersedia'?'active':'' ?>">
          <i class="ti ti-check" style="font-size:12px;"></i> Tersedia <span class="chip-count"><?= $stat_tersedia_count ?></span>
        </a>
        <a href="?tab=catalog&filter=habis&sort=<?=$sort_by?>&q=<?=urlencode($search_q)?>" class="chip <?= $filter_st=='habis'?'active':'' ?>">
          <i class="ti ti-ban" style="font-size:12px;"></i> Habis <span class="chip-count"><?= $stat_habis_count ?></span>
        </a>
        <div style="margin-left:auto;display:flex;align-items:center;gap:.5rem;">
          <span style="font-size:12px;color:var(--muted);">Urut:</span>
          <select class="sort-select" onchange="applySort(this.value)">
            <option value="nama"       <?= $sort_by=='nama'?'selected':'' ?>>Nama A–Z</option>
            <option value="harga_asc"  <?= $sort_by=='harga_asc'?'selected':'' ?>>Harga ↑</option>
            <option value="harga_desc" <?= $sort_by=='harga_desc'?'selected':'' ?>>Harga ↓</option>
            <option value="tahun"      <?= $sort_by=='tahun'?'selected':'' ?>>Tahun Terbaru</option>
          </select>
        </div>
      </div>

      <div class="result-info">
        Menampilkan <strong><?= $total_katalog ?></strong> mobil
        <?php if($search_q): ?> · pencarian "<strong><?= htmlspecialchars($search_q) ?></strong>"<?php endif; ?>
        <?php if($filter_st=='tersedia'): ?> · hanya <strong>tersedia</strong><?php endif; ?>
      </div>

      <?php if ($total_katalog == 0): ?>
      <div class="section-card">
        <div class="empty-state">
          <i class="ti ti-car-off"></i>
          <p>Tidak ada mobil ditemukan.<br>
          <a href="?tab=catalog" style="color:var(--accent);font-weight:600;">Reset filter</a></p>
        </div>
      </div>
      <?php else: ?>
      <div class="catalog-grid">
        <?php
        $icons_c = ['🚗','🚙','🏎️','🚕','🛻','🚐']; $ci=0;
        while($m = mysqli_fetch_assoc($daftar_mobil)):
          $ic = $icons_c[$ci % count($icons_c)]; $ci++;
          $ok = ($m['status']=='tersedia' && $m['stok']>0);
        ?>
        <div class="car-card <?= !$ok?'unavailable':'' ?>">
          <div class="car-img">
            <?= $ic ?>
            <span class="stok-pill">📦 <?= $m['stok'] ?> unit</span>
            <span class="car-badge <?= $ok?'avail':'unavail' ?>"><?= $ok?'✓ Tersedia':'✗ Habis' ?></span>
          </div>
          <div class="car-body">
            <div class="car-name"><?= htmlspecialchars($m['nama_mobil']) ?></div>
            <div class="car-meta"><?= htmlspecialchars($m['merek']) ?> · <?= $m['tahun'] ?></div>
            <div class="car-specs">
              <span class="spec-tag"><i class="ti ti-calendar"></i> <?= $m['tahun'] ?></span>
              <span class="spec-tag"><i class="ti ti-stack-2"></i> <?= $m['stok'] ?> unit</span>
              <?php if($ok): ?>
              <span class="spec-tag" style="background:var(--green-bg);color:var(--green);"><i class="ti ti-circle-check"></i> Ready</span>
              <?php else: ?>
              <span class="spec-tag" style="background:var(--red-bg);color:var(--red);"><i class="ti ti-circle-x"></i> Kosong</span>
              <?php endif;?>
            </div>
            <div class="car-price">Rp <?= number_format($m['harga_per_hari'],0,',','.') ?> <span>/ hari</span></div>
            <div class="car-actions">
              <?php if($ok): ?>
              <button class="btn btn-primary"
                onclick="openQuickModal(<?=$m['id_mobil']?>,'<?=addslashes($m['nama_mobil'])?>','<?=addslashes($m['merek'])?>',<?=$m['tahun']?>,<?=$m['harga_per_hari']?>,<?=$m['stok']?>, '<?=$ic?>')">
                <i class="ti ti-car"></i> Sewa Cepat
              </button>
              <a href="?tab=sewa&sewa=<?=$m['id_mobil']?>" class="btn btn-icon btn-outline" title="Form Sewa Lengkap">
                <i class="ti ti-external-link"></i>
              </a>
              <?php else: ?>
              <button class="btn btn-disabled" disabled><i class="ti ti-ban"></i> Tidak Tersedia</button>
              <?php endif;?>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══════════ TAB: FORM SEWA ══════════ -->
    <div class="tab-content <?= $tab=='sewa'?'active':'' ?>">
      <form method="POST" action="?tab=sewa" id="formSewa">
        <input type="hidden" name="aksi" value="pesan">
        <div class="sewa-layout">

          <!-- KIRI: FORM -->
          <div>
            <!-- Pilih Mobil -->
            <div class="sewa-card">
              <div class="sewa-card-header">
                <div class="sewa-card-icon"><i class="ti ti-car"></i></div>
                <div>
                  <div class="sewa-card-title">Pilih Mobil</div>
                  <div class="sewa-card-sub">Dari stok yang disediakan admin</div>
                </div>
              </div>
              <div class="sewa-card-body">
                <div class="form-group">
                  <label class="form-label">Daftar Mobil Tersedia</label>
                  <select name="id_mobil" id="pilihMobil" class="form-control" required onchange="updatePreview()">
                    <option value="">-- Pilih Mobil --</option>
                    <?php
                    $icons_s=['🚗','🚙','🏎️','🚕','🛻','🚐']; $si=0;
                    while($ms = mysqli_fetch_assoc($mobil_tersedia)):
                      $ics = $icons_s[$si%count($icons_s)]; $si++;
                      $sel = ($preselectMobil==$ms['id_mobil'])?'selected':'';
                    ?>
                    <option value="<?=$ms['id_mobil']?>"
                      data-nama="<?=htmlspecialchars($ms['nama_mobil'])?>"
                      data-merek="<?=htmlspecialchars($ms['merek'])?>"
                      data-tahun="<?=$ms['tahun']?>"
                      data-harga="<?=$ms['harga_per_hari']?>"
                      data-stok="<?=$ms['stok']?>"
                      data-icon="<?=$ics?>" <?=$sel?>>
                      <?=$ics?> <?=htmlspecialchars($ms['nama_mobil'])?> (<?=$ms['merek']?>) — Rp <?=number_format($ms['harga_per_hari'],0,',','.')?>/hari · Stok: <?=$ms['stok']?>
                    </option>
                    <?php endwhile;?>
                  </select>
                  <?php if(mysqli_num_rows($mobil_tersedia)==0): ?>
                  <div class="form-hint" style="color:var(--red);"><i class="ti ti-alert-circle" style="font-size:13px;"></i> Tidak ada mobil tersedia. Hubungi admin.</div>
                  <?php endif;?>
                </div>
              </div>
            </div>

            <!-- Data Diri -->
            <div class="sewa-card">
              <div class="sewa-card-header">
                <div class="sewa-card-icon"><i class="ti ti-user"></i></div>
                <div>
                  <div class="sewa-card-title">Data Diri Penyewa</div>
                  <div class="sewa-card-sub">Isi sesuai kartu identitas (KTP)</div>
                </div>
              </div>
              <div class="sewa-card-body">
                <div class="form-group">
                  <label class="form-label">Nama Lengkap</label>
                  <input type="text" name="nama_penyewa" class="form-control" placeholder="Nama sesuai KTP" required>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">NIK / No. KTP</label>
                    <input type="text" name="nik" class="form-control" placeholder="16 digit NIK" maxlength="16">
                  </div>
                  <div class="form-group">
                    <label class="form-label">No. HP / WhatsApp</label>
                    <input type="text" name="no_hp" class="form-control" placeholder="08xx-xxxx-xxxx" required>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Alamat Lengkap</label>
                  <textarea name="alamat" class="form-control" placeholder="Jalan, Kecamatan, Kota" required></textarea>
                </div>
              </div>
            </div>

            <!-- Tanggal -->
            <div class="sewa-card">
              <div class="sewa-card-header">
                <div class="sewa-card-icon"><i class="ti ti-calendar-event"></i></div>
                <div>
                  <div class="sewa-card-title">Periode Sewa</div>
                  <div class="sewa-card-sub">Tentukan tanggal mulai dan selesai</div>
                </div>
              </div>
              <div class="sewa-card-body">
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="tgl_mulai" id="sw_tgl1" class="form-control"
                           value="<?=date('Y-m-d')?>" min="<?=date('Y-m-d')?>" required onchange="hitungSummary()">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="date" name="tgl_selesai" id="sw_tgl2" class="form-control"
                           value="<?=date('Y-m-d',strtotime('+1 day'))?>" min="<?=date('Y-m-d',strtotime('+1 day'))?>" required onchange="hitungSummary()">
                  </div>
                </div>
                <div class="form-hint"><i class="ti ti-info-circle" style="font-size:13px;"></i> Pengembalian sebelum pukul 10.00 pada tanggal selesai.</div>
              </div>
            </div>
          </div>

          <!-- KANAN: RINGKASAN -->
          <div style="position:sticky;top:80px;">
            <div class="sewa-card">
              <div class="sewa-card-header">
                <div class="sewa-card-icon"><i class="ti ti-receipt"></i></div>
                <div>
                  <div class="sewa-card-title">Ringkasan Pesanan</div>
                  <div class="sewa-card-sub">Estimasi biaya sewa</div>
                </div>
              </div>
              <div class="sewa-card-body">
                <!-- Preview Mobil -->
                <div id="mobilPreview">
                  <div class="no-car-placeholder">
                    <i class="ti ti-car-off"></i>
                    <p>Pilih mobil untuk melihat detail</p>
                  </div>
                </div>

                <!-- Summary -->
                <div class="summary-box" id="summaryBox">
                  <div class="summary-label">Rincian Biaya</div>
                  <div class="summary-row"><span>Harga/hari</span><span id="s_harga">—</span></div>
                  <div class="summary-row"><span>Durasi</span><span id="s_hari">—</span></div>
                  <div class="summary-row"><span>Mulai</span><span id="s_tgl1">—</span></div>
                  <div class="summary-row"><span>Selesai</span><span id="s_tgl2">—</span></div>
                  <div class="summary-divider"></div>
                  <div class="summary-total"><span>Total Bayar</span><span class="summary-total-val" id="s_total">—</span></div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                  <i class="ti ti-check"></i> Konfirmasi Pemesanan
                </button>
                <a href="?tab=catalog" class="btn btn-outline btn-full" style="margin-top:.6rem;">
                  <i class="ti ti-arrow-left"></i> Kembali ke Katalog
                </a>

                <div class="tips-box">
                  <div class="tips-title"><i class="ti ti-bulb" style="font-size:14px;"></i> Info Sewa</div>
                  <ul class="tips-list">
                    <li>Bawa KTP asli saat pengambilan</li>
                    <li>Pembayaran di tempat / transfer</li>
                    <li>Pengembalian sebelum pukul 10.00</li>
                    <li>Keterlambatan kena biaya tambahan</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

        </div>
      </form>
    </div>

    <!-- ══════════ TAB: RIWAYAT ══════════ -->
    <div class="tab-content <?= $tab=='riwayat'?'active':'' ?>">
      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-clock-history"></i> Riwayat Pemesanan Saya</div>
          <a href="?tab=sewa" class="btn btn-outline" style="height:34px;font-size:12px;padding:0 .9rem;flex:unset;">
            <i class="ti ti-plus"></i> Sewa Baru
          </a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>#</th><th>Mobil</th><th>Tgl Mulai</th><th>Tgl Selesai</th><th>Durasi</th><th>Total Bayar</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php $no=1;
              if(mysqli_num_rows($riwayat_sewa)==0): ?>
              <tr><td colspan="7"><div class="empty-state">
                <i class="ti ti-file-off"></i>
                <p>Belum ada riwayat sewa.<br><a href="?tab=catalog" style="color:var(--accent);font-weight:600;">Mulai pesan sekarang</a></p>
              </div></td></tr>
              <?php else: while($s=mysqli_fetch_assoc($riwayat_sewa)): ?>
              <tr>
                <td style="color:var(--muted)"><?=$no++?></td>
                <td>
                  <div style="font-weight:700"><?=htmlspecialchars($s['nama_mobil'])?></div>
                  <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($s['merek'])?></div>
                </td>
                <td><?=date('d M Y',strtotime($s['tgl_mulai']))?></td>
                <td><?=date('d M Y',strtotime($s['tgl_selesai']))?></td>
                <td><?=$s['total_hari']?> hari</td>
                <td class="money">Rp <?=number_format($s['total_bayar'],0,',','.')?></td>
                <td>
                  <?php if($s['status']=='aktif'): ?><span class="badge badge-blue">🔵 Aktif</span>
                  <?php elseif($s['status']=='selesai'): ?><span class="badge badge-green">✅ Selesai</span>
                  <?php else: ?><span class="badge badge-red">❌ Dibatalkan</span><?php endif;?>
                </td>
              </tr>
              <?php endwhile; endif;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ══════════ TAB: SEWA DARI ADMIN ══════════ -->
    <div class="tab-content <?= $tab=='sewa_admin'?'active':'' ?>">
      <div style="background:linear-gradient(135deg,#1a1612 60%,#2a1f18);border:1px solid var(--accent);border-radius:var(--radius);padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;">
        <div style="width:44px;height:44px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🛡️</div>
        <div>
          <div style="font-weight:700;color:#fff;font-size:15px;">Transaksi yang Dibuat oleh Admin</div>
          <div style="font-size:12.5px;color:var(--muted);margin-top:2px;">Data sewa yang diinput langsung oleh admin. Hubungi admin jika ada pertanyaan.</div>
        </div>
        <?php if($jumlah_sewa_admin>0): ?>
        <div style="margin-left:auto;text-align:right;flex-shrink:0;">
          <div style="font-size:26px;font-weight:800;color:var(--accent);"><?=$jumlah_sewa_admin?></div>
          <div style="font-size:11px;color:var(--muted);">Transaksi</div>
        </div>
        <?php endif;?>
      </div>

      <div class="section-card">
        <div class="section-header">
          <div class="section-title"><i class="ti ti-table"></i> Daftar Sewa dari Admin</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>#</th><th>Mobil</th><th>Penyewa</th><th>Tgl Mulai</th><th>Tgl Selesai</th><th>Durasi</th><th>Total Bayar</th><th>Oleh</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php $no=1;
              if($jumlah_sewa_admin==0): ?>
              <tr><td colspan="9"><div class="empty-state">
                <i class="ti ti-shield-off"></i>
                <p>Belum ada transaksi dari admin.</p>
              </div></td></tr>
              <?php else: mysqli_data_seek($sewa_dari_admin,0); while($sa=mysqli_fetch_assoc($sewa_dari_admin)): ?>
              <tr>
                <td style="color:var(--muted)"><?=$no++?></td>
                <td>
                  <div style="font-weight:700"><?=htmlspecialchars($sa['nama_mobil'])?></div>
                  <div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($sa['merek'])?></div>
                  <div style="font-size:11px;color:var(--accent)">Rp <?=number_format($sa['harga_per_hari'],0,',','.')?>/hari</div>
                </td>
                <td>
                  <div style="font-weight:600"><?=htmlspecialchars($sa['nama_penyewa'])?></div>
                  <div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($sa['no_hp'])?></div>
                </td>
                <td><?=date('d M Y',strtotime($sa['tgl_mulai']))?></td>
                <td><?=date('d M Y',strtotime($sa['tgl_selesai']))?></td>
                <td><span style="background:var(--blue-bg);color:var(--blue);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;"><?=$sa['total_hari']?> hari</span></td>
                <td class="money" style="font-weight:700;color:var(--accent);">Rp <?=number_format($sa['total_bayar'],0,',','.')?></td>
                <td><span style="display:inline-flex;align-items:center;gap:4px;background:rgba(200,75,49,0.1);color:var(--accent);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;"><i class="ti ti-shield-check" style="font-size:12px;"></i><?=htmlspecialchars($sa['dibuat_oleh']??'Admin')?></span></td>
                <td>
                  <?php if($sa['status']=='aktif'): ?><span class="badge badge-blue">🔵 Aktif</span>
                  <?php elseif($sa['status']=='selesai'): ?><span class="badge badge-green">✅ Selesai</span>
                  <?php else: ?><span class="badge badge-red">❌ Dibatalkan</span><?php endif;?>
                </td>
              </tr>
              <?php endwhile; endif;?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if($jumlah_sewa_admin>0):
        mysqli_data_seek($sewa_dari_admin,0);
        $tb=0; $ab=0; $sb=0;
        while($tmp=mysqli_fetch_assoc($sewa_dari_admin)){$tb+=$tmp['total_bayar'];if($tmp['status']=='aktif')$ab++;if($tmp['status']=='selesai')$sb++;}
      ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:1rem;margin-top:1.25rem;">
        <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Total</div>
          <div style="font-size:26px;font-weight:800;color:var(--dark);margin-top:4px;"><?=$jumlah_sewa_admin?></div>
        </div>
        <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Aktif</div>
          <div style="font-size:26px;font-weight:800;color:var(--blue);margin-top:4px;"><?=$ab?></div>
        </div>
        <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Selesai</div>
          <div style="font-size:26px;font-weight:800;color:var(--green);margin-top:4px;"><?=$sb?></div>
        </div>
        <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;text-align:center;">
          <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Nilai</div>
          <div style="font-size:15px;font-weight:800;color:var(--accent);margin-top:6px;">Rp <?=number_format($tb,0,',','.')?></div>
        </div>
      </div>
      <?php endif;?>
    </div>

  </div>
</main>

<!-- ══════════ MODAL SEWA CEPAT ══════════ -->
<div class="modal-overlay" id="quickModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🚗 Sewa Cepat</div>
      <button class="modal-close" onclick="closeQuickModal()"><i class="ti ti-x"></i></button>
    </div>
    <form method="POST" action="?tab=riwayat">
      <input type="hidden" name="aksi" value="pesan">
      <input type="hidden" name="id_mobil" id="qm_id">
      <div class="modal-body">
        <div class="modal-car-info">
          <div class="modal-car-icon" id="qm_icon">🚗</div>
          <div style="flex:1;">
            <div class="modal-car-name" id="qm_name">—</div>
            <div style="font-size:12px;color:var(--muted);" id="qm_sub">—</div>
            <div class="modal-car-price" id="qm_price">—</div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:10px;color:var(--muted);">Stok</div>
            <div style="font-size:20px;font-weight:800;" id="qm_stok">—</div>
            <div style="font-size:10px;color:var(--muted);">unit</div>
          </div>
        </div>

        <div class="form-divider">Data Diri Penyewa</div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama_penyewa" class="form-control" placeholder="Sesuai KTP" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">NIK / KTP</label>
            <input type="text" name="nik" class="form-control" placeholder="16 digit" maxlength="16">
          </div>
          <div class="form-group">
            <label class="form-label">No. HP / WA</label>
            <input type="text" name="no_hp" class="form-control" placeholder="08xx-xxxx-xxxx" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Alamat</label>
          <textarea name="alamat" class="form-control" placeholder="Alamat lengkap" required></textarea>
        </div>

        <div class="form-divider">Tanggal Sewa</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tgl Mulai</label>
            <input type="date" name="tgl_mulai" id="qm_t1" class="form-control"
                   value="<?=date('Y-m-d')?>" min="<?=date('Y-m-d')?>" required onchange="hitungQuick()">
          </div>
          <div class="form-group">
            <label class="form-label">Tgl Selesai</label>
            <input type="date" name="tgl_selesai" id="qm_t2" class="form-control"
                   value="<?=date('Y-m-d',strtotime('+1 day'))?>" min="<?=date('Y-m-d',strtotime('+1 day'))?>" required onchange="hitungQuick()">
          </div>
        </div>

        <div class="total-preview" id="qm_total_box">
          <div class="total-preview-label">Estimasi Total Biaya</div>
          <div class="total-preview-val" id="qm_total_val">—</div>
          <div class="total-preview-detail" id="qm_total_det">—</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeQuickModal()" class="btn btn-outline" style="flex:1;">Batal</button>
        <button type="submit" class="btn btn-primary" style="flex:2;">
          <i class="ti ti-check"></i> Konfirmasi
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── SORT KATALOG ──
function applySort(val){
  const u=new URL(window.location.href);
  u.searchParams.set('sort',val);
  window.location.href=u.toString();
}

// ── FORM SEWA: PREVIEW ──
let swHarga=0;
const bulanId=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const fmtRp=(n)=>'Rp '+parseInt(n).toLocaleString('id-ID');
const fmtTgl=(str)=>{const d=new Date(str);return d.getDate()+' '+bulanId[d.getMonth()]+' '+d.getFullYear();};

function updatePreview(){
  const sel=document.getElementById('pilihMobil');
  const opt=sel.options[sel.selectedIndex];
  const wrap=document.getElementById('mobilPreview');
  if(!opt||!opt.value){
    swHarga=0;
    wrap.innerHTML=`<div class="no-car-placeholder"><i class="ti ti-car-off"></i><p>Pilih mobil untuk melihat detail</p></div>`;
    document.getElementById('summaryBox').style.display='none';
    return;
  }
  swHarga=parseFloat(opt.dataset.harga);
  wrap.innerHTML=`
    <div class="mobil-dark-card">
      <span class="mdc-stok">📦 ${opt.dataset.stok} unit</span>
      <span class="mdc-icon">${opt.dataset.icon||'🚗'}</span>
      <div class="mdc-name">${opt.dataset.nama}</div>
      <div class="mdc-meta">${opt.dataset.merek} · ${opt.dataset.tahun}</div>
      <div class="mdc-price">${fmtRp(swHarga)} <span>/ hari</span></div>
    </div>`;
  hitungSummary();
}

function hitungSummary(){
  if(!swHarga){return;}
  const t1=document.getElementById('sw_tgl1').value;
  const t2=document.getElementById('sw_tgl2').value;
  const box=document.getElementById('summaryBox');
  if(!t1||!t2){box.style.display='none';return;}
  const hari=Math.ceil((new Date(t2)-new Date(t1))/86400000);
  if(hari<=0){box.style.display='none';return;}
  document.getElementById('s_harga').textContent=fmtRp(swHarga);
  document.getElementById('s_hari').textContent=hari+' hari';
  document.getElementById('s_tgl1').textContent=fmtTgl(t1);
  document.getElementById('s_tgl2').textContent=fmtTgl(t2);
  document.getElementById('s_total').textContent=fmtRp(swHarga*hari);
  box.style.display='block';
}

// ── MODAL SEWA CEPAT ──
let qmHarga=0;
function openQuickModal(id,nama,merek,tahun,harga,stok,icon){
  qmHarga=harga;
  document.getElementById('qm_id').value=id;
  document.getElementById('qm_icon').textContent=icon||'🚗';
  document.getElementById('qm_name').textContent=nama;
  document.getElementById('qm_sub').textContent=merek+' · '+tahun;
  document.getElementById('qm_price').textContent=fmtRp(harga)+' / hari';
  document.getElementById('qm_stok').textContent=stok;
  document.getElementById('quickModal').classList.add('open');
  hitungQuick();
}
function closeQuickModal(){document.getElementById('quickModal').classList.remove('open');}
document.getElementById('quickModal').addEventListener('click',function(e){if(e.target===this)closeQuickModal();});

function hitungQuick(){
  const t1=document.getElementById('qm_t1').value;
  const t2=document.getElementById('qm_t2').value;
  const box=document.getElementById('qm_total_box');
  if(!t1||!t2||!qmHarga){box.style.display='none';return;}
  const hari=Math.ceil((new Date(t2)-new Date(t1))/86400000);
  if(hari<=0){box.style.display='none';return;}
  document.getElementById('qm_total_val').textContent=fmtRp(qmHarga*hari);
  document.getElementById('qm_total_det').textContent=hari+' hari × '+fmtRp(qmHarga)+'/hari';
  box.style.display='block';
}

// ── INIT ──
window.addEventListener('DOMContentLoaded',()=>{
  updatePreview();
  hitungSummary();
});
</script>
</body>
</html>
