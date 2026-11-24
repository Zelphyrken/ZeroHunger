(() => {
    'use strict';
    document.querySelectorAll('[data-home-image]').forEach((img) => {
        const hide = () => { img.hidden = true; };
        img.addEventListener('error', hide);
        if (img.complete && img.naturalWidth === 0) hide();
    });
})();
