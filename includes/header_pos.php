<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $page_title ?? APP_NAME; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css">

    <style>
        /* CSS Khusus Header POS */
        .pos-header {
            background-color: #4e73df; /* Warna Biru Utama */
            height: 70px;
            box-shadow: 0 .15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 100;
        }
        .pos-brand {
            color: white !important;
            font-weight: 800;
            font-size: 1.5rem;
            text-decoration: none;
            letter-spacing: 1px;
        }
        .pos-outlet-info {
            color: rgba(255,255,255,0.8);
            font-weight: 600;
            background: rgba(255,255,255,0.1);
            padding: 5px 15px;
            border-radius: 20px;
        }
        /* Mencegah scroll body utama, kita scroll di div konten nanti */
        body {
            overflow: hidden; 
            background-color: #f8f9fc;
        }
    </style>
</head>

<body id="page-top">

    <nav class="navbar navbar-expand pos-header static-top px-4 d-flex justify-content-between">

        <a class="pos-brand d-flex align-items-center" href="#">
            <i class="fas fa-tshirt fa-lg me-2"></i>
            <?php echo APP_NAME; ?> <span class="badge bg-white text-primary ms-2" style="font-size: 0.7rem; vertical-align: top;">POS</span>
        </a>

        <div class="d-none d-md-block">
            <div class="pos-outlet-info">
                <i class="fas fa-store me-2"></i>
                <?php echo htmlspecialchars($_SESSION['outlet_name'] ?? 'Outlet'); ?>
            </div>
        </div>

        <ul class="navbar-nav align-items-center">
            <li class="nav-item me-3 d-none d-sm-block text-white small">
                Halo, <b><?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Kasir'); ?></b>
            </li>
            <div class="topbar-divider d-none d-sm-block bg-light" style="height: 25px; opacity: 0.3;"></div>

            <li class="nav-item dropdown no-arrow ms-2">
                <a class="btn btn-danger btn-sm shadow-sm" href="#" onclick="konfirmasiLogout(event)">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-1"></i> Logout
                </a>
            </li>
        </ul>

    </nav>

    <script>
    function konfirmasiLogout(e) {
        e.preventDefault(); // Mencegah link langsung jalan
        Swal.fire({
            title: 'Tutup Kasir?',
            text: "Sesi Anda akan berakhir. Lanjutkan?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#858796',
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Arahkan ke file logout asli
                window.location.href = '../../admin/logout.php';
            }
        })
    }
    </script>

    <div id="wrapper" class="h-100">