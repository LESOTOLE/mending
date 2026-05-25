/**
 * Mending Laundry - Professional SaaS Animations
 * Uses AOS (Animate On Scroll) for scroll animations and Vanilla JS for micro-interactions.
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Add AOS attributes to Cards automatically (if they don't have them)
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        if (!card.hasAttribute('data-aos')) {
            card.setAttribute('data-aos', 'fade-up');
            const delay = (index % 4) * 100; 
            card.setAttribute('data-aos-delay', delay.toString());
        }
    });

    // 2. Add AOS to Tables
    const tables = document.querySelectorAll('.table-responsive');
    tables.forEach((table) => {
        if (!table.hasAttribute('data-aos')) {
            table.setAttribute('data-aos', 'fade-up');
            table.setAttribute('data-aos-duration', '800');
        }
    });

    // 3. Add AOS to Page Headers
    const pageHeaders = document.querySelectorAll('.h3, h3');
    pageHeaders.forEach((header) => {
        if (!header.hasAttribute('data-aos') && header.parentElement.tagName !== 'A') {
            header.setAttribute('data-aos', 'fade-right');
        }
    });

    // 4. Initialize AOS (Animate on Scroll) AFTER setting attributes
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 600,               // Faster animation
            easing: 'ease-out-cubic',    // Better for entering elements
            once: true,                  // Only animate once
            offset: 20                   // Trigger earlier
        });
    }

    // 5. Button Micro-interactions (Ripple Effect)
    const buttons = document.querySelectorAll('.btn-primary, .btn-success, .btn-warning, .btn-danger');
    buttons.forEach(btn => {
        btn.addEventListener('mousedown', function(e) {
            let x = e.clientX - e.target.getBoundingClientRect().left;
            let y = e.clientY - e.target.getBoundingClientRect().top;

            let ripples = document.createElement('span');
            ripples.style.cssText = `
                position: absolute;
                background: rgba(255, 255, 255, 0.4);
                transform: translate(-50%, -50%);
                pointer-events: none;
                border-radius: 50%;
                animation: animateRipple 0.6s linear;
                left: ${x}px;
                top: ${y}px;
                width: 0;
                height: 0;
            `;
            
            // Add keyframes dynamically if not present
            if (!document.getElementById('rippleKeyframes')) {
                const style = document.createElement('style');
                style.id = 'rippleKeyframes';
                style.innerHTML = `
                    @keyframes animateRipple {
                        0% { width: 0px; height: 0px; opacity: 0.5; }
                        100% { width: 400px; height: 400px; opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }

            btn.style.position = 'relative';
            btn.style.overflow = 'hidden';
            this.appendChild(ripples);

            setTimeout(() => {
                ripples.remove();
            }, 600);
        });
    });

    // 6. Sidebar item slide-in on load
    const sidebarItems = document.querySelectorAll('.sidebar .nav-item');
    sidebarItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        item.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        
        setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, 100 + (index * 50));
    });
});
