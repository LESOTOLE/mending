</div>
        </div>
    </div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// --- FUNGSI GLOBAL UNTUK TOGGLE SIDEBAR ---
function initSidebarToggle() {
    const sidebar = document.querySelector('.sidebar');
    const toggleButton = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (toggleButton && sidebar && sidebarOverlay) {
        
        const toggleSidebar = () => {
            sidebar.classList.toggle('show-mobile');
            // Logika Overlay
            if (sidebar.classList.contains('show-mobile')) {
                sidebarOverlay.style.display = 'block';
                setTimeout(() => sidebarOverlay.style.opacity = '1', 10);
            } else {
                sidebarOverlay.style.opacity = '0';
                setTimeout(() => sidebarOverlay.style.display = 'none', 300);
            }
        };

        // 1. Tombol Burger
        toggleButton.addEventListener('click', toggleSidebar);

        // 2. Klik Overlay
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // 3. Klik Link Menu
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 767.98) {
                    setTimeout(toggleSidebar, 150); 
                }
            });
        });
    } else {
        console.error("DEBUG FATAL: Elemen Sidebar atau Toggle Button tidak ditemukan.");
    }
}
</script>

<script>if (typeof window.jQuery === 'undefined') { document.write('<script src="<?= ASSETS_URL ?>vendor/js/jquery-3.6.0.min.js"><\/script>'); }</script>
<script>if (typeof window.Swal === 'undefined') { document.write('<script src="<?= ASSETS_URL ?>vendor/js/sweetalert2.all.min.js"><\/script>'); }</script>
<script src="<?= ASSETS_URL ?>vendor/js/bootstrap.bundle.min.js" 
        onload="initSidebarToggle();"></script>

<!-- AOS Animation JS -->
<script src="<?= ASSETS_URL ?>vendor/js/aos.js"></script>
<!-- Custom Animations JS -->
<script src="<?= ASSETS_URL ?>js/animations.js"></script>

</body>
</html>