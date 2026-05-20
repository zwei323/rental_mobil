<?php
session_start();

// Redirect jika sudah login
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: dasboard.php");
    }
    exit();
}

// Proses login
$error = "";
if (isset($_POST['username'])) {
    $conn = mysqli_connect("localhost", "root", "zwei1", "rental_mobil");

    if (!$conn) {
        die("Koneksi gagal: " . mysqli_connect_error());
    }

    // Buat tabel users jika belum ada
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS user (
            id_user INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Seed: buat akun default jika belum ada
    $cek_admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM user WHERE username='admin'"));
    if ($cek_admin['c'] == 0) {
        mysqli_query($conn, "INSERT INTO user (username, password, role) VALUES ('admin', 'admin123', 'admin')");
        mysqli_query($conn, "INSERT INTO user (username, password, role) VALUES ('user1', 'user123', 'user')");
    }

    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = mysqli_real_escape_string($conn, $_POST['password']);

    $data = mysqli_query($conn, "SELECT * FROM user WHERE username='$user' AND password='$pass'");
    $cek  = mysqli_num_rows($data);

    if ($cek > 0) {
        $d = mysqli_fetch_assoc($data);
        $_SESSION['role']     = $d['role'];
        $_SESSION['username'] = $d['username'];
        $_SESSION['id_user']  = $d['id_user'];

        if ($d['role'] == 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: dasboard.php");
        }
        exit();
    } else {
        $error = "Username atau password salah!";
    }

    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — RentAuto</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    :root {
      --bg: #0f0c09;
      --card: #1a1612;
      --border: rgba(255,255,255,0.08);
      --accent: #c84b31;
      --accent-light: rgba(200,75,49,0.12);
      --text: #f5f0eb;
      --muted: #7a6e65;
      --input-bg: rgba(255,255,255,0.05);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Outfit', sans-serif;
      min-height: 100vh;
      display: flex;
      background: var(--bg);
      color: var(--text);
      overflow: hidden;
    }

    /* Left decorative panel */
    .left-panel {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: flex-start;
      padding: 4rem;
      position: relative;
      background: radial-gradient(ellipse at 30% 50%, rgba(200,75,49,0.15) 0%, transparent 60%);
    }
    .left-panel::before {
      content: '🚗';
      position: absolute;
      font-size: 280px;
      opacity: 0.04;
      bottom: -40px;
      left: -30px;
      transform: rotate(-15deg);
      pointer-events: none;
    }
    .hero-tag {
      background: var(--accent-light);
      border: 1px solid rgba(200,75,49,0.3);
      color: var(--accent);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 0.35rem 0.85rem;
      border-radius: 20px;
      margin-bottom: 1.5rem;
      display: inline-block;
    }
    .hero-title {
      font-size: 52px;
      font-weight: 800;
      line-height: 1.1;
      letter-spacing: -0.02em;
      margin-bottom: 1.25rem;
    }
    .hero-accent { color: var(--accent); }
    .hero-desc {
      font-size: 16px;
      color: var(--muted);
      max-width: 380px;
      line-height: 1.7;
      font-weight: 400;
    }

    .feature-list {
      margin-top: 2.5rem;
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
    }
    .feature-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 14px;
      color: rgba(255,255,255,0.55);
    }
    .feature-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
    }

    /* Right: login form */
    .right-panel {
      width: 460px;
      min-height: 100vh;
      background: var(--card);
      border-left: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem 2.5rem;
    }
    .form-header { width: 100%; margin-bottom: 2.25rem; }
    .form-logo {
      font-size: 26px;
      font-weight: 800;
      margin-bottom: 0.75rem;
    }
    .form-logo span { color: var(--accent); }
    .form-welcome { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
    .form-sub { font-size: 14px; color: var(--muted); }

    .error-box {
      width: 100%;
      background: rgba(200,75,49,0.1);
      border: 1px solid rgba(200,75,49,0.3);
      border-radius: 10px;
      padding: 0.85rem 1rem;
      font-size: 13.5px;
      color: #f87060;
      display: flex;
      align-items: center;
      gap: 0.6rem;
      margin-bottom: 1.25rem;
    }

    .field-group { width: 100%; margin-bottom: 1.1rem; }
    .field-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: rgba(255,255,255,0.5);
      margin-bottom: 6px;
      letter-spacing: 0.01em;
    }
    .field-wrap { position: relative; }
    .field-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: 17px;
      pointer-events: none;
    }
    .field-input {
      width: 100%;
      height: 46px;
      padding: 0 14px 0 40px;
      background: var(--input-bg);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-size: 14.5px;
      font-family: 'Outfit', sans-serif;
      outline: none;
      transition: border-color 0.2s, background 0.2s;
    }
    .field-input:focus {
      border-color: var(--accent);
      background: var(--accent-light);
    }
    .field-input::placeholder { color: var(--muted); }

    .btn-login {
      width: 100%;
      height: 48px;
      margin-top: 1.5rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 700;
      font-family: 'Outfit', sans-serif;
      cursor: pointer;
      letter-spacing: 0.01em;
      transition: background 0.15s, transform 0.1s;
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .btn-login:hover { background: #a33a23; }
    .btn-login:active { transform: scale(0.98); }

    .demo-accounts {
      width: 100%;
      margin-top: 2rem;
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1rem;
    }
    .demo-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin-bottom: 0.65rem; }
    .demo-row {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 12.5px;
      color: rgba(255,255,255,0.45);
      margin-bottom: 4px;
    }
    .demo-chip {
      background: rgba(255,255,255,0.06);
      padding: 0.15rem 0.5rem;
      border-radius: 5px;
      font-family: monospace;
      font-size: 12px;
      color: rgba(255,255,255,0.65);
    }

    @media (max-width: 768px) {
      .left-panel { display: none; }
      .right-panel { width: 100%; border: none; }
    }
  </style>
</head>
<body>

  <!-- LEFT DECORATIVE -->
  <div class="left-panel">
    <div class="hero-tag">🚗 Sistem Rental Mobil</div>
    <h1 class="hero-title">
      Sewa mobil<br><span class="hero-accent">mudah &amp;</span><br>cepat.
    </h1>
    <p class="hero-desc">
      Platform manajemen rental mobil lengkap untuk admin dan pengguna. Kelola armada, penyewa, dan transaksi dalam satu sistem.
    </p>
    <div class="feature-list">
      <div class="feature-item"><div class="feature-dot"></div>Kelola data mobil & stok realtime</div>
      <div class="feature-item"><div class="feature-dot"></div>Transaksi sewa otomatis dengan stored procedure</div>
      <div class="feature-item"><div class="feature-dot"></div>Riwayat pemesanan lengkap per user</div>
      <div class="feature-item"><div class="feature-dot"></div>Dashboard statistik pendapatan & armada</div>
    </div>
  </div>

  <!-- RIGHT: FORM -->
  <div class="right-panel">
    <div class="form-header">
      <div class="form-logo">Rent<span>Auto</span></div>
      <div class="form-welcome">Selamat datang kembali</div>
      <div class="form-sub">Masuk untuk mengakses sistem</div>
    </div>

    <?php if ($error): ?>
    <div class="error-box">
      <i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" style="width:100%">
      <div class="field-group">
        <label class="field-label" for="username">Username</label>
        <div class="field-wrap">
          <i class="ti ti-user field-icon"></i>
          <input class="field-input" type="text" id="username" name="username"
                 placeholder="Masukkan username" required autocomplete="username">
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="password">Password</label>
        <div class="field-wrap">
          <i class="ti ti-lock field-icon"></i>
          <input class="field-input" type="password" id="password" name="password"
                 placeholder="Masukkan password" required autocomplete="current-password">
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="ti ti-login"></i> Masuk ke Sistem
      </button>
    </form>

    <div class="demo-accounts">
      <div class="demo-label">Akun Demo</div>
      <div class="demo-row">
        <i class="ti ti-shield" style="color:#c84b31"></i>
        Admin: <span class="demo-chip">admin</span> / <span class="demo-chip">admin123</span>
      </div>
      <div class="demo-row">
        <i class="ti ti-user" style="color:#3b82f6"></i>
        User: <span class="demo-chip">user1</span> / <span class="demo-chip">user123</span>
      </div>
    </div>
  </div>

</body>
</html>
