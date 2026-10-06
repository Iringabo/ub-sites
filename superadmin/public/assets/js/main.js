// Reusable counter animation for server-rendered data-count elements.
window.FacultySite = window.FacultySite || {};

window.FacultySite.initCounters = function (root) {
    root = root || document;
    const counters = root.querySelectorAll('[data-count]:not([data-counter-done])');
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
        el.setAttribute('data-counter-done', 'true');
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
    window.FacultySite.initCounters();

    // 3. Client filters for in-page chips (staff). News search/chips are server GET.
    const filterBtns = document.querySelectorAll('button[data-filter]');
    const noResults = document.getElementById('newsNoResults');

    function applyCardFilters() {
        const activeBtn = document.querySelector('button[data-filter].active');
        const filter = activeBtn ? activeBtn.getAttribute('data-filter') : 'tous';
        let visibleCount = 0;

        document.querySelectorAll('[data-category]').forEach(card => {
            const matchesCategory = filter === 'tous' || card.getAttribute('data-category') === filter;
            if (matchesCategory) visibleCount++;
            const col = card.closest('[class*="col-"]');
            if (col) col.style.display = matchesCategory ? '' : 'none';
        });

        noResults?.classList.toggle('d-none', visibleCount !== 0);
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const parent = btn.closest('div');
            parent.querySelectorAll('button[data-filter]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyCardFilters();
        });
    });

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

            if (!collapseElement || window.innerWidth >= 1200 || link.classList.contains('dropdown-toggle')) {
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

window.addEventListener('pageshow', () => {
    document.querySelectorAll('#contactForm [data-contact-submit]').forEach((button) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    });
});

(() => {
    const marquees = document.querySelectorAll('[data-marquee]');

    const layout = (marquee) => {
        const track = marquee.querySelector('.partner-marquee-track');
        if (!track) {
            return;
        }

        track.querySelectorAll('[data-marquee-clone]').forEach((clone) => clone.remove());
        marquee.classList.remove('is-scrolling');
        marquee.classList.add('is-measuring');
        const overflows = track.scrollWidth > marquee.clientWidth + 1;
        marquee.classList.remove('is-measuring');

        if (!overflows) {
            return;
        }

        const items = Array.from(track.children);
        items.forEach((item) => {
            const clone = item.cloneNode(true);
            clone.setAttribute('data-marquee-clone', '');
            clone.setAttribute('aria-hidden', 'true');
            clone.querySelectorAll('a').forEach((link) => link.setAttribute('tabindex', '-1'));
            track.appendChild(clone);
        });
        marquee.style.setProperty('--marquee-duration', `${Math.max(20, items.length * 5)}s`);
        marquee.classList.add('is-scrolling');
    };

    let resizeTimer;
    marquees.forEach(layout);
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => marquees.forEach(layout), 200);
    });
})();

document.querySelectorAll('[data-rail]').forEach((rail) => {
    const track = rail.querySelector('[data-rail-track]');
    const previous = rail.querySelector('[data-rail-prev]');
    const next = rail.querySelector('[data-rail-next]');
    if (!track || !previous || !next) {
        return;
    }

    const step = () => {
        const card = track.firstElementChild;
        const gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;

        return card ? card.getBoundingClientRect().width + gap : track.clientWidth * 0.8;
    };

    const update = () => {
        const maxScroll = track.scrollWidth - track.clientWidth;
        const tolerance = 8;
        rail.classList.toggle('is-static', maxScroll <= tolerance);
        previous.disabled = track.scrollLeft <= tolerance;
        next.disabled = track.scrollLeft >= maxScroll - tolerance;
    };

    previous.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
    next.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    window.addEventListener('load', update);
    update();
});

document.querySelectorAll('[data-search]').forEach((form) => {
    const toggle = form.querySelector('[data-search-toggle]');
    const input = form.querySelector('input[type="search"]');
    if (!toggle || !input) {
        return;
    }

    const setOpen = (open) => {
        form.toggleAttribute('data-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            input.focus();
        }
    };

    toggle.addEventListener('click', () => setOpen(!form.hasAttribute('data-open')));
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && form.hasAttribute('data-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (form.hasAttribute('data-open') && !form.contains(event.target)) {
            setOpen(false);
        }
    });
});
