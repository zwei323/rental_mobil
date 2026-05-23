-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 23, 2026 at 04:40 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rental_mobil`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `proc_tambah_sewa` (IN `p_id_user` INT, IN `p_id_mobil` INT, IN `p_id_penyewa` INT, IN `p_tgl_mulai` DATE, IN `p_tgl_selesai` DATE)   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sewa_mobil` (IN `p_id_user` INT, IN `p_id_mobil` INT, IN `p_jumlah` INT)   BEGIN

   INSERT INTO penyewaan(
       id_user,
       id_mobil,
       jumlah_sewa,
       tanggal_sewa
   )
   VALUES(
       p_id_user,
       p_id_mobil,
       p_jumlah,
       CURDATE()
   );

   UPDATE mobil
   SET jumlah = jumlah - p_jumlah
   WHERE id_mobil = p_id_mobil;

END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_bayar` (`p_id_mobil` INT, `p_hari` INT) RETURNS DECIMAL(15,2) DETERMINISTIC BEGIN
    DECLARE v_harga DECIMAL(15,2);
    SELECT harga_per_hari INTO v_harga FROM mobil WHERE id_mobil = p_id_mobil;
    RETURN v_harga * p_hari;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `status_mobil` (`jumlah` INT) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci  BEGIN

   DECLARE hasil VARCHAR(20);

   IF jumlah <= 0 THEN
       SET hasil = 'Tidak Tersedia';
   ELSE
       SET hasil = 'Tersedia';
   END IF;

   RETURN hasil;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `mobil`
--

CREATE TABLE `mobil` (
  `id_mobil` int(11) NOT NULL,
  `nama_mobil` varchar(100) DEFAULT NULL,
  `merek` varchar(100) DEFAULT NULL,
  `tahun` year(4) DEFAULT NULL,
  `harga_per_hari` decimal(15,2) DEFAULT NULL,
  `stok` int(11) DEFAULT 1,
  `status` enum('tersedia','habis') DEFAULT 'tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mobil`
--

INSERT INTO `mobil` (`id_mobil`, `nama_mobil`, `merek`, `tahun`, `harga_per_hari`, `stok`, `status`) VALUES
(1, 'Ruevelto Galardo', 'ferari', '2026', 1.00, 13, 'tersedia'),
(2, 'Avanza', 'Tompel', '2026', 5.00, 10, 'tersedia'),
(3, 'bmw f4', 'bmw', '2026', 10000.00, 4, 'tersedia');

-- --------------------------------------------------------

--
-- Table structure for table `penyewa`
--

CREATE TABLE `penyewa` (
  `id_penyewa` int(11) NOT NULL,
  `nama_penyewa` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penyewa`
--

INSERT INTO `penyewa` (`id_penyewa`, `nama_penyewa`, `no_hp`, `alamat`, `nik`, `created_at`) VALUES
(1, 'Jembul', '0896846467', 'jl. merdeka php', '9089089808', '2026-05-23 00:58:35'),
(2, 'maun', '908878989', 'jl . sultan agung', '9089089808', '2026-05-23 01:00:55'),
(3, 'xixixi', '089778765433', 'Jl. Babadan', '08977765432', '2026-05-23 01:02:14'),
(4, 'zwei', '90887976555', 'jl.merakk', '90887976555', '2026-05-23 01:04:57');

-- --------------------------------------------------------

--
-- Table structure for table `penyewaan`
--

CREATE TABLE `penyewaan` (
  `id_sewa` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_mobil` int(11) DEFAULT NULL,
  `jumlah_sewa` int(11) DEFAULT NULL,
  `tanggal_sewa` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sewa`
--

CREATE TABLE `sewa` (
  `id_sewa` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_mobil` int(11) DEFAULT NULL,
  `id_penyewa` int(11) DEFAULT NULL,
  `tgl_mulai` date DEFAULT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `total_hari` int(11) DEFAULT NULL,
  `total_bayar` decimal(15,2) DEFAULT NULL,
  `status` enum('aktif','selesai','dibatalkan') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sewa`
--

INSERT INTO `sewa` (`id_sewa`, `id_user`, `id_mobil`, `id_penyewa`, `tgl_mulai`, `tgl_selesai`, `total_hari`, `total_bayar`, `status`, `created_at`) VALUES
(1, 1, 1, 1, '2026-05-23', '2026-05-24', 1, 1.00, 'selesai', '2026-05-23 00:59:21'),
(2, 1, 2, 2, '2026-05-23', '2026-05-24', 1, 5.00, 'selesai', '2026-05-23 01:01:06'),
(3, 1, 3, 3, '2026-05-23', '2026-05-24', 1, 10000.00, 'selesai', '2026-05-23 01:03:09'),
(4, 2, 3, 4, '2026-05-23', '2026-05-24', 1, 10000.00, 'aktif', '2026-05-23 01:04:57');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `role` enum('admin','penyewa') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id_user`, `nama`, `username`, `password`, `role`) VALUES
(1, 'zwei1', 'user11', 'maun1234', 'admin'),
(2, 'zwei22', 'penyewa2', 'maun12345', 'penyewa'),
(4, NULL, 'admin', 'admin123', 'admin'),
(5, NULL, 'user1', 'user123', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mobil`
--
ALTER TABLE `mobil`
  ADD PRIMARY KEY (`id_mobil`);

--
-- Indexes for table `penyewa`
--
ALTER TABLE `penyewa`
  ADD PRIMARY KEY (`id_penyewa`);

--
-- Indexes for table `penyewaan`
--
ALTER TABLE `penyewaan`
  ADD PRIMARY KEY (`id_sewa`);

--
-- Indexes for table `sewa`
--
ALTER TABLE `sewa`
  ADD PRIMARY KEY (`id_sewa`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `mobil`
--
ALTER TABLE `mobil`
  MODIFY `id_mobil` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `penyewa`
--
ALTER TABLE `penyewa`
  MODIFY `id_penyewa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `penyewaan`
--
ALTER TABLE `penyewaan`
  MODIFY `id_sewa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sewa`
--
ALTER TABLE `sewa`
  MODIFY `id_sewa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
