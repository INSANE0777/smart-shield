import './bootstrap';

// Mobile menu toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.getElementById('hamburger-icon');
    const closeIcon = document.getElementById('close-icon');

    if (mobileMenuButton && mobileMenu && hamburgerIcon && closeIcon) {
        mobileMenuButton.addEventListener('click', function() {
            const isMenuOpen = mobileMenu.classList.contains('open');
            
            if (isMenuOpen) {
                // Close menu
                mobileMenu.classList.remove('open');
                hamburgerIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            } else {
                // Open menu
                mobileMenu.classList.add('open');
                hamburgerIcon.classList.add('hidden');
                closeIcon.classList.remove('hidden');
            }
        });

        // Close menu when clicking on a link (for better UX)
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.remove('open');
                hamburgerIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            });
        });
    }
});
