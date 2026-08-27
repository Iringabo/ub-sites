// Reusable counter animation for server-rendered data-count elements.
window.FSEG = window.FSEG || {};

window.FSEG.initCounters = function (root) {
    root = root || document;
    const counters = root.querySelectorAll('[data-count]:not([data-fseg-counted])');
    if (!counters.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(el => {
        el.setAttribute('data-fseg-counted', 'true');
        observer.observe(el);
    });

    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-count'), 10);
        const valueNode = el.querySelector('.stat-value');
        const suffixNode = el.querySelector('.stat-suffix');
        const suffix = suffixNode ? suffixNode.textContent : '';
        const duration = 1800;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                if (valueNode) {
                    valueNode.textContent = target.toLocaleString('fr-FR');
                } else {
                    el.textContent = target.toLocaleString('fr-FR') + suffix;
                }
                clearInterval(timer);
            } else {
                const formatted = Math.floor(current).toLocaleString('fr-FR');
                if (valueNode) {
                    valueNode.textContent = formatted;
                } else {
                    el.textContent = formatted + suffix;
                }
            }
        }, 16);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // 1. Navbar shadow on scroll
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        navbar?.classList.toggle('shadow', window.scrollY > 10);
    });

    // 1b. Hero carousel. Bootstrap handles keyboard and touch interactions;
    // reduced-motion users keep manual controls without automatic cycling.
    const heroCarousel = document.querySelector('[data-site-hero-carousel]');
    if (heroCarousel && window.bootstrap?.Carousel) {
        const carousel = window.bootstrap.Carousel.getOrCreateInstance(heroCarousel, {
            interval: prefersReducedMotion ? false : parseInt(heroCarousel.getAttribute('data-bs-interval') || '7000', 10),
            keyboard: true,
            pause: 'hover',
            ride: prefersReducedMotion ? false : 'carousel',
            touch: true,
        });

        if (prefersReducedMotion) {
            heroCarousel.removeAttribute('data-bs-ride');
            carousel.pause();
        }
    }

    // 2. Counter animation (triggered by IntersectionObserver)
    window.FSEG.initCounters();

    // 3. Filter buttons (news & staff) + optional live search (news page)
    const filterBtns = document.querySelectorAll('[data-filter]');
    const searchInput = document.getElementById('newsSearch');
    const noResults = document.getElementById('newsNoResults');

    const ACCENT_MAP = { a: 'aàâä', e: 'eéèêë', i: 'iîï', o: 'oôö', u: 'uùûü', c: 'cç' };
    const ACCENT_LOOKUP = {};
    Object.keys(ACCENT_MAP).forEach(plain => {
        ACCENT_MAP[plain].split('').forEach(accented => { ACCENT_LOOKUP[accented] = plain; });
    });

    function normalizeText(str) {
        return str.toLowerCase().split('').map(ch => ACCENT_LOOKUP[ch] || ch).join('');
    }

    function applyCardFilters() {
        const activeBtn = document.querySelector('[data-filter].active');
        const filter = activeBtn ? activeBtn.getAttribute('data-filter') : 'tous';
        const searchTerm = searchInput ? normalizeText(searchInput.value.trim()) : '';
        let visibleCount = 0;

        document.querySelectorAll('[data-category]').forEach(card => {
            const matchesCategory = filter === 'tous' || card.getAttribute('data-category') === filter;
            const matchesSearch = !searchTerm || normalizeText(card.textContent).includes(searchTerm);
            const show = matchesCategory && matchesSearch;
            if (show) visibleCount++;
            const col = card.closest('[class*="col-"]');
            if (col) col.style.display = show ? '' : 'none';
        });

        noResults?.classList.toggle('d-none', visibleCount !== 0);
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const parent = btn.closest('div');
            parent.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyCardFilters();
        });
    });

    searchInput?.addEventListener('input', applyCardFilters);

    // 4. Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', e => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            }
        });
    });

    document.querySelectorAll('.navbar .navbar-collapse a, .navbar .navbar-collapse button').forEach((link) => {
        link.addEventListener('click', () => {
            const collapseElement = link.closest('.navbar-collapse');

            if (!collapseElement || window.innerWidth >= 992 || link.classList.contains('dropdown-toggle')) {
                return;
            }

            if (window.bootstrap) {
                window.bootstrap.Collapse.getOrCreateInstance(collapseElement).hide();
            }
        });
    });

    // 5. Contact form enhancement. The server remains the source of validation.
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', (event) => {
            const fields = contactForm.querySelectorAll('[required]');
            let firstInvalid = null;

            fields.forEach(field => {
                field.classList.toggle('is-invalid', !field.value.trim());
                if (!field.value.trim() && firstInvalid === null) {
                    firstInvalid = field;
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                return;
            }

            const submitButton = contactForm.querySelector('[data-contact-submit]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
            }
        });
    }
});
