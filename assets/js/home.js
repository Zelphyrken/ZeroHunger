(() => {
    'use strict';

    const toggle = document.querySelector('.home-menu-toggle');
    const navigation = document.querySelector('#home-navigation');
    const mobile = window.matchMedia('(max-width: 800px)');

    if (toggle && navigation) {
        const setExpanded = (expanded) => {
            toggle.setAttribute('aria-expanded', String(expanded));
            navigation.hidden = mobile.matches && !expanded;
        };

        const syncNavigation = () => {
            // Moving to mobile must not leave focus inside a collapsed menu.
            if (mobile.matches && navigation.contains(document.activeElement)) {
                toggle.hidden = false;
                toggle.focus();
            }
            toggle.hidden = !mobile.matches;
            setExpanded(false);
        };

        toggle.addEventListener('click', () => {
            setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
        });
        navigation.addEventListener('click', (event) => {
            if (mobile.matches && event.target.closest('a')) {
                setExpanded(false);
                toggle.focus();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && mobile.matches && toggle.getAttribute('aria-expanded') === 'true') {
                setExpanded(false);
                toggle.focus();
            }
        });
        mobile.addEventListener('change', syncNavigation);
        syncNavigation();
    }

    // Preserve the neutral, labelled placeholder when an uploaded file is gone.
    document.querySelectorAll('[data-home-image]').forEach((img) => {
        const hideBrokenImage = () => { img.hidden = true; };
        img.addEventListener('error', hideBrokenImage);
        if (img.complete && img.naturalWidth === 0) hideBrokenImage();
    });
})();
