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

document.querySelectorAll('form[data-unsaved-guard]').forEach((form) => {
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

window.addEventListener('pageshow', () => {
    formIsSubmitting = false;
    document.querySelectorAll('form button[type="submit"]').forEach((button) => {
        button.disabled = false;
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
        }
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

document.querySelectorAll('input[type="color"][id]').forEach((field) => {
    const hex = document.querySelector(`[data-color-hex-for="${field.id}"]`);
    if (!hex) {
        return;
    }

    const update = () => {
        hex.textContent = field.value.toUpperCase();
    };

    field.addEventListener('input', update);
    update();
});

document.querySelectorAll('.admin-preview-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const row = document.getElementById(toggle.getAttribute('data-target'));
        if (!row) {
            return;
        }
        const expanded = row.classList.toggle('d-none') === false;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.innerHTML = expanded
            ? '<i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Masquer'
            : '<i class="bi bi-eye me-1" aria-hidden="true"></i>Aperçu';
    });
});

const bulkToggle = document.getElementById('adminBulkToggle');
const bulkChecks = document.querySelectorAll('.admin-bulk-check');
const bulkBar = document.querySelector('.admin-bulk-bar');
const bulkCount = document.querySelector('.admin-bulk-count');

if (bulkToggle && bulkChecks.length) {
    const updateBulkCount = () => {
        const selected = Array.from(bulkChecks).filter((check) => check.checked).length;
        if (bulkCount) {
            bulkCount.textContent = String(selected);
        }
    };

    bulkToggle.addEventListener('change', () => {
        bulkChecks.forEach((check) => {
            check.checked = bulkToggle.checked;
        });
        updateBulkCount();
    });

    bulkChecks.forEach((check) => {
        check.addEventListener('change', () => {
            const allChecked = Array.from(bulkChecks).every((c) => c.checked);
            bulkToggle.checked = allChecked;
            updateBulkCount();
        });
    });
}

if (bulkBar && bulkChecks.length) {
    document.querySelectorAll('.admin-bulk-bar select').forEach((select) => {
        const apply = select.nextElementSibling;
        if (!apply) {
            return;
        }
        const message = select.options[select.selectedIndex] && select.options[select.selectedIndex].text
            ? `Appliquer « ${select.options[select.selectedIndex].text} » à ${bulkChecks.length ? '' : '0'} contenu(s) sélectionné(s) ?`
            : 'Confirmer cette action groupée ?';
        apply.addEventListener('click', (event) => {
            const selected = Array.from(bulkChecks).filter((check) => check.checked).length;
            if (selected === 0) {
                event.preventDefault();
                window.alert('Sélectionnez au moins un contenu.');
                return;
            }
            const label = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : 'effectuer cette action';
            if (!window.confirm(`Appliquer « ${label} » à ${selected} contenu(s) sélectionné(s) ?`)) {
                event.preventDefault();
            }
        });
    });
}

document.querySelectorAll('tbody[data-sortable], table[data-sortable]').forEach((table) => {
    if (!window.Sortable) {
        return;
    }

    const tbody = table;
    const url = tbody.getAttribute('data-sortable-url');
    const form = document.getElementById('adminReorderForm');
    let saving = false;

    const renumberOrderCells = () => {
        const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
        rows.forEach((row, index) => {
            const cell = row.querySelector('[data-order-cell]');
            if (cell) {
                cell.textContent = String(index + 1);
            }
        });
    };

    new window.Sortable(tbody, {
        handle: '.admin-drag-handle',
        animation: 150,
        ghostClass: 'admin-drag-ghost',
        onEnd: () => {
            if (saving || !form) {
                return;
            }
            saving = true;
            renumberOrderCells();
            const ids = Array.from(tbody.querySelectorAll('tr[data-id]'))
                .map((row) => row.getAttribute('data-id'))
                .join(',');

            const body = new URLSearchParams(new FormData(form));
            body.set('ids', ids);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('network');
                    }
                    return response.json();
                })
                .then(() => {
                    saving = false;
                })
                .catch(() => {
                    saving = false;
                    window.alert("Impossible d'enregistrer le nouvel ordre. Rechargez la page.");
                });
        },
    });
});

(() => {
    const overlay = document.getElementById('adminSiteLoading');
    const switcher = document.querySelector('[data-admin-site-switcher]');

    if (!overlay || !switcher) {
        return;
    }

    const select = switcher.querySelector('select[name="site_id"]');

    const showLoading = () => {
        overlay.hidden = false;
        overlay.setAttribute('aria-busy', 'true');
        document.body.classList.add('admin-site-loading-active');
        if (select) {
            select.disabled = true;
        }
    };

    const hideLoading = () => {
        overlay.hidden = true;
        overlay.setAttribute('aria-busy', 'false');
        document.body.classList.remove('admin-site-loading-active');
        if (select) {
            select.disabled = false;
        }
    };

    // CSP script-src-attr is 'none': never use inline onchange= handlers.
    if (select) {
        select.addEventListener('change', () => {
            if (typeof switcher.requestSubmit === 'function') {
                switcher.requestSubmit();
            } else {
                switcher.submit();
            }
        });
    }

    switcher.addEventListener('htmx:beforeRequest', showLoading);
    switcher.addEventListener('htmx:responseError', hideLoading);
    switcher.addEventListener('htmx:sendError', hideLoading);
    switcher.addEventListener('htmx:timeout', hideLoading);

    // Full-page HX-Redirect keeps the overlay visible until navigation completes.
    switcher.addEventListener('submit', () => {
        if (!window.htmx) {
            showLoading();
        }
    });
})();

(() => {
    const openPalette = () => {
        const input = document.getElementById('adminPaletteInput');
        if (input) {
            input.value = '';
            const results = document.getElementById('adminPaletteResults');
            if (results) {
                results.replaceChildren();
            }
            input.focus();
        }
    };

    const items = [];

    const collect = () => {
        items.length = 0;
        document.querySelectorAll('.admin-nav-section').forEach((section) => {
            const toggle = section.querySelector('.admin-nav-group-toggle');
            const groupTitle = toggle ? toggle.textContent.trim() : 'Navigation';
            section.querySelectorAll('a.admin-nav-link[href]').forEach((link) => {
                items.push({
                    title: link.textContent.trim(),
                    group: groupTitle,
                    href: link.getAttribute('href'),
                });
            });
        });
    };

    const renderResults = (query) => {
        const list = document.getElementById('adminPaletteResults');
        if (!list) {
            return;
        }
        const q = query.trim().toLowerCase();
        const matches = q ? items.filter((item) => (item.title + ' ' + item.group).toLowerCase().includes(q)) : items;

        list.replaceChildren();
        matches.slice(0, 12).forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'd-flex w-100 align-items-center justify-content-between gap-2 px-3 py-2 text-start';

            const title = document.createElement('span');
            title.className = 'text-truncate';
            title.textContent = item.title;

            const group = document.createElement('span');
            group.className = 'small text-muted text-nowrap ms-auto';
            group.textContent = item.group;

            button.append(title, group);
            button.addEventListener('click', () => {
                window.location.href = item.href;
            });
            button.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    window.location.href = item.href;
                }
            });
            list.appendChild(button);
        });

        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-2 text-muted small';
            empty.textContent = 'Aucun résultat.';
            list.appendChild(empty);
        }
    };

    const ensureDom = () => {
        if (document.getElementById('adminPalette')) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.id = 'adminPalette';
        overlay.className = 'admin-palette';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Palette de commandes');
        overlay.style.display = 'none';
        overlay.innerHTML = `
            <div class="admin-palette-inner" role="search">
                <div class="d-flex align-items-center gap-2 border-bottom px-3 py-2">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="adminPaletteInput" type="text" class="form-control border-0 shadow-none" placeholder="Rechercher un module… (Ctrl+K)" aria-label="Recherche">
                </div>
                <div id="adminPaletteResults" class="admin-palette-results"></div>
            </div>`;

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                closePalette();
            }
        });

        document.body.appendChild(overlay);

        const input = document.getElementById('adminPaletteInput');
        input.addEventListener('input', () => renderResults(input.value));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                const buttons = Array.from(document.querySelectorAll('#adminPaletteResults button'));
                if (!buttons.length) {
                    return;
                }
                event.preventDefault();
                const current = document.activeElement;
                const idx = buttons.indexOf(current);
                const next = buttons[(idx + 1) % buttons.length];
                next.focus();
            }
            if (event.key === 'ArrowUp') {
                const buttons = Array.from(document.querySelectorAll('#adminPaletteResults button'));
                if (!buttons.length) {
                    return;
                }
                event.preventDefault();
                const current = document.activeElement;
                const idx = buttons.indexOf(current);
                const prev = buttons[(idx === -1 ? 0 : idx) - 1 + buttons.length];
                prev.focus();
            }
        });
    };

    const openPaletteFull = () => {
        ensureDom();
        const overlay = document.getElementById('adminPalette');
        collect();
        overlay.style.display = 'flex';
        openPalette();
    };

    const closePalette = () => {
        const overlay = document.getElementById('adminPalette');
        if (overlay) {
            overlay.style.display = 'none';
        }
    };

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            const overlay = document.getElementById('adminPalette');
            if (overlay && overlay.style.display !== 'none') {
                closePalette();
            } else {
                openPaletteFull();
            }
        }
        if (event.key === 'Escape') {
            closePalette();
        }
    });

    document.querySelectorAll('[data-admin-palette-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openPaletteFull();
        });
    });
})();
