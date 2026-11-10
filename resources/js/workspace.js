const workspace = document.querySelector('[data-workspace]');

if (workspace) {
    const mobile = window.matchMedia('(max-width: 63.99rem)');
    const sidebar = workspace.querySelector('[data-sidebar]');
    const body = workspace.querySelector('[data-workspace-body]');
    const openButton = workspace.querySelector('[data-menu-open]');
    const closeButton = workspace.querySelector('[data-menu-close]');

    function openMenu() {
        workspace.dataset.menuOpen = '';
        openButton.setAttribute('aria-expanded', 'true');
        body.inert = true;
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        closeButton.focus();
    }

    function closeMenu() {
        if (!workspace.hasAttribute('data-menu-open')) {
            return;
        }

        delete workspace.dataset.menuOpen;
        body.inert = false;
        openButton.setAttribute('aria-expanded', 'false');
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        const focusTarget = mobile.matches ? openButton : sidebar.querySelector('a');
        focusTarget.focus();
    }

    openButton.addEventListener('click', openMenu);
    closeButton.addEventListener('click', closeMenu);
    workspace.querySelector('[data-menu-overlay]').addEventListener('click', closeMenu);
    mobile.addEventListener('change', closeMenu);
    sidebar.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }

        if (event.key === 'Tab' && workspace.hasAttribute('data-menu-open')) {
            const controls = sidebar.querySelectorAll('a[href], button');
            const first = controls[0];
            const last = controls[controls.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    workspace.dataset.enhanced = '';
}

const confirmation = document.querySelector('[data-confirm-dialog]');

if (typeof confirmation?.showModal === 'function') {
    const message = confirmation.querySelector('[data-confirm-message]');
    const cancelButton = confirmation.querySelector('button[value="cancel"]');
    let pending;
    let confirmedForm;

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.onsubmit = null;
        form.addEventListener('submit', (event) => {
            if (confirmedForm === form) {
                return;
            }

            event.preventDefault();
            pending = { form, submitter: event.submitter };
            message.textContent = form.dataset.confirm;
            confirmation.returnValue = '';
            confirmation.showModal();
            cancelButton.focus();
        });
    });

    confirmation.addEventListener('close', () => {
        if (confirmation.returnValue === 'confirm' && pending) {
            confirmedForm = pending.form;
            pending.form.requestSubmit(pending.submitter ?? undefined);
            confirmedForm = null;
        }
        pending = null;
    });
}

const imagePreviews = document.querySelectorAll('[data-image-preview]');

async function loadImagePreview(preview) {
    try {
        const response = await fetch(preview.dataset.imagePreview, { redirect: 'error' });
        if (response.ok) {
            preview.innerHTML = await response.text();
        }
    } catch {
        preview.textContent = 'Anteprima immagini non disponibile.';
    }
}

if (imagePreviews.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                loadImagePreview(entry.target);
            }
        }
    }, { rootMargin: '150px' });

    imagePreviews.forEach((preview) => observer.observe(preview));
} else {
    imagePreviews.forEach(loadImagePreview);
}

const imagePicker = document.querySelector('[data-image-limit]');

if (imagePicker) {
    const images = Array.from(imagePicker.querySelectorAll('input[name="image_ids[]"]'));
    const limit = Number(imagePicker.dataset.imageLimit);
    const counter = imagePicker.querySelector('[data-image-count]');

    function updateSelection() {
        const selected = images.filter((image) => image.checked).length;

        images.forEach((image) => {
            image.disabled = selected >= limit && !image.checked;
        });
        counter.textContent = `${selected} su ${limit} immagini selezionate`;
    }

    imagePicker.addEventListener('change', updateSelection);
    updateSelection();
}
