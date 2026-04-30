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
    
    <style>
        /* Gaya Kustom Sidebar Biru (Dasar) */
        .sidebar {
            background: linear-gradient(180deg, #007bff 0%, #0056b3 100%);
            color: white;
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
            transition: background-color 0.3s, color 0.3s;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar a:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar .active {
            background-color: #FFFFFF2C; 
            color: #FFFFFFFF; 
            font-weight: bold;
            border-left: 5px solid #00F7FFFF; 
        }
        .sidebar .nav-link i {
            font-size: 1.1em;
            width: 25px;
            margin-right: 10px;
        }
        .navbar-custom {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        /* Gaya Logout Container */
        .sidebar .logout-container {
            padding: 10px 15px; 
            background-color: #0069d9;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar .logout-link {
            background-color: #dc3545; 
            color: white !important;
            text-align: center;
            padding: 10px 15px;
            display: block;
        }
        .sidebar .logout-link:hover {
            background-color: #c82333 !important;
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