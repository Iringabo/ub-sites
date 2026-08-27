let formIsSubmitting = false;

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.getAttribute('data-confirm') || 'Confirmer cette action ?';

        if (!window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });
});

document.querySelectorAll('form').forEach((form) => {
    let changed = false;

    form.addEventListener('input', () => {
        changed = true;
    });

    form.addEventListener('change', () => {
        changed = true;
    });

    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !form.checkValidity()) {
            return;
        }

        formIsSubmitting = true;
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Enregistrement...';
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (!changed || formIsSubmitting) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
});

document.querySelectorAll('[data-character-counter]').forEach((field) => {
    const counter = document.getElementById(field.getAttribute('data-character-counter'));
    const max = Number.parseInt(field.getAttribute('maxlength') || field.dataset.maxlength || '0', 10);

    if (!counter || !max) {
        return;
    }

    const update = () => {
        counter.textContent = `${field.value.length}/${max}`;
    };

    field.addEventListener('input', update);
    update();
});

document.querySelectorAll('[data-range-output]').forEach((field) => {
    const output = document.getElementById(field.getAttribute('data-range-output'));

    if (!output) {
        return;
    }

    const update = () => {
        output.textContent = `${Math.round(Number.parseFloat(field.value) * 100)} %`;
    };

    field.addEventListener('input', update);
    update();
});
