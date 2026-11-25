(() => {
    'use strict';
    const toggle = document.querySelector('.site-menu-toggle');
    const menu = document.querySelector('.site-nav-links');
    const mobile = matchMedia('(max-width: 800px)');
    if (toggle && menu) {
        const expand = (open) => {
            toggle.setAttribute('aria-expanded', String(open));
            menu.hidden = mobile.matches && !open;
        };
        const sync = () => {
            const wasToggle = document.activeElement === toggle;
            if (mobile.matches && menu.contains(document.activeElement)) {
                toggle.hidden = false;
                toggle.focus();
            }
            toggle.hidden = !mobile.matches;
            expand(false);
            if (!mobile.matches && wasToggle) menu.querySelector('a')?.focus();
        };
        toggle.addEventListener('click', () => expand(toggle.getAttribute('aria-expanded') !== 'true'));
        menu.addEventListener('click', (event) => {
            if (mobile.matches && event.target.closest('a')) { expand(false); toggle.focus(); }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && mobile.matches && toggle.getAttribute('aria-expanded') === 'true') {
                expand(false); toggle.focus();
            }
        });
        mobile.addEventListener('change', sync);
        sync();
    }
    document.querySelectorAll('img[data-site-image]').forEach((img) => {
        const fallback = () => {
            if (img.hidden) return;
            const label = document.createElement('span');
            label.className = 'image-fallback';
            label.textContent = 'Foto belum tersedia';
            img.hidden = true;
            img.after(label);
        };
        img.addEventListener('error', fallback);
        if (img.complete && !img.naturalWidth) fallback();
    });
    document.querySelectorAll('input[type="file"]').forEach((input) => {
        let objectUrl;
        input.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            input.parentElement.querySelector('.upload-preview')?.remove();
            const file = input.files[0];
            if (!file || !['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) return;
            objectUrl = URL.createObjectURL(file);
            const image = document.createElement('img');
            image.className = 'media-preview upload-preview';
            image.alt = 'Pratinjau foto yang dipilih';
            image.src = objectUrl;
            input.after(image);
        });
    });
    // Show feedback after native validation and any existing confirmation succeeds.
    document.querySelectorAll('form[method="POST"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;
            const button = event.submitter;
            if (button) {
                button.dataset.submitLabel = button.textContent;
                button.setAttribute('aria-busy', 'true');
                button.textContent = 'Memproses…';
            }
        });
    });
    // Restored pages must not keep the visual submitting state from bfcache.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-submit-label]').forEach((button) => {
            button.textContent = button.dataset.submitLabel;
            button.removeAttribute('aria-busy');
        });
    });
})();
