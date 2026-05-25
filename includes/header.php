<?php
// Perlu memastikan variabel $page_title, $nama_lengkap, dan $role_id sudah tersedia di file utama.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - ...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css">
    
    <!-- AOS Animation CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Link Web3 Theme -->
    <link rel="stylesheet" href="/mending/assets/css/web3.css?v=<?= time() ?>">
    
    <style>
        /* Gaya Kustom Struktur Sidebar (Warna dikendalikan web3.css) */
        .sidebar {
            min-height: 100vh;
            width: 250px;
            flex-shrink: 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
        }
        .sidebar a {
            color: #e9ecef;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar a:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.2);
        }
        .sidebar .active {
            background-color: rgba(255, 255, 255, 0.15); 
            color: #fff; 
            font-weight: bold;
            border-left: 5px solid var(--primary); 
            box-shadow: inset 0 0 10px rgba(0, 240, 255, 0.1);
        }
        .sidebar .nav-link i {
            font-size: 1.1em;
            width: 25px;
            margin-right: 10px;
        }
        
        /* Gaya Logout Container */
        .sidebar .logout-container {
            padding: 10px 15px; 
            background: transparent;
            border-top: 1px solid var(--glass-border);
        }
        .sidebar .logout-link {
            background: linear-gradient(90deg, #990033, var(--danger));
            color: white !important;
            text-align: center;
            padding: 10px 15px;
            display: block;
            border-radius: 12px;
            transition: 0.3s;
        }
        .sidebar .logout-link:hover {
            box-shadow: 0 0 15px rgba(255, 0, 85, 0.5);
            transform: translateY(-2px);
        }
@media (max-width: 767.98px) {
    .sidebar {
        position: fixed; /* Menempel di layar mobile */
        top: 0;
        left: 0;
        z-index: 1030;
        height: 100vh;
        margin-left: -250px; /* Sembunyikan dari layar */
        transition: margin-left 0.3s ease-in-out;
        overflow-y: hidden; /* Memungkinkan menu di-scroll di mobile */
    }

    .sidebar.show-mobile {
        margin-left: 0; /* Tampilkan ketika kelas ini ditambahkan oleh JS */
    }
    
    /* Overlay untuk menutup interaksi di luar menu */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1020;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }
    
    /* 2. AREA MENU (yang harus di-scroll) */
    .sidebar ul.nav {
        /* Tambahkan properti untuk membuat menu dapat di-scroll */
        max-height: calc(100vh - 120px); /* KUNCI: Tinggi sisa layar dikurangi header/footer */
        overflow-y: auto; 
    }
    
    /* 3. Area Logout menempel di bawah container utama */
    .sidebar .logout-container {
        /* Gunakan z-index tinggi agar tombol Logout selalu di atas menu */
        z-index: 1040; 
        box-shadow: 0 -5px 10px rgba(0, 0, 0, 0.3); /* Tambahkan shadow agar menonjol */
    }
}

    </style>
</head>
<body>

<div class="d-flex">
    <?php
    if(!isset($hide_sidebar) || $hide_sidebar === false) {
        include 'sidebar.php';
    }
?>
    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
            <div class="container-fluid">
                <button class="btn btn-primary d-block d-md-none me-3" type="button" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <a class="navbar-brand text-primary" href="#"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></a>
                
                <span class="navbar-text">
                    Selamat Datang, <b><?php echo htmlspecialchars($nama_lengkap ?? $_SESSION['nama_lengkap'] ?? 'Pengguna'); ?></b>!
                </span>
            </div>
        </nav>

        <div class="content p-4">