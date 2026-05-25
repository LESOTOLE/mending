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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
        onload="initSidebarToggle();"></script>

<!-- AOS Animation JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<!-- Custom Animations JS -->
<script src="/mending/assets/js/animations.js"></script>

</body>
</html>