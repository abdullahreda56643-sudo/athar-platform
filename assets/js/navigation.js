const menuButton = document.querySelector('.menu-toggle');
const primaryNavigation = document.querySelector('#primary-navigation');
const siteNavigation = document.querySelector('.site-nav');

if (menuButton instanceof HTMLButtonElement && primaryNavigation instanceof HTMLElement && siteNavigation instanceof HTMLElement) {
    const mobileNavigation = window.matchMedia('(max-width: 800px)');

    const closeMenu = (restoreFocus = false) => {
        primaryNavigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'فتح قائمة التنقل');

        if (restoreFocus) {
            menuButton.focus();
        }
    };

    menuButton.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isOpen));
        menuButton.setAttribute('aria-label', isOpen ? 'فتح قائمة التنقل' : 'إغلاق قائمة التنقل');
        primaryNavigation.classList.toggle('is-open', !isOpen);
    });

    primaryNavigation.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('a') && mobileNavigation.matches) {
            closeMenu();
        }
    });

    document.addEventListener('click', (event) => {
        if (mobileNavigation.matches && !siteNavigation.contains(event.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
            closeMenu(true);
        }
    });

    mobileNavigation.addEventListener('change', () => closeMenu());
}
