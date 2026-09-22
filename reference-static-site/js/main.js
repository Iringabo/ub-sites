// Reusable counter animation, exposed on window.FSEG so content.js can
// re-run it after injecting data-count elements fetched from JSON.
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
        const duration = 1800;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                el.textContent = target.toLocaleString('fr-FR');
                clearInterval(timer);
            } else {
                el.textContent = Math.floor(current).toLocaleString('fr-FR');
            }
        }, 16);
    }
};

document.addEventListener('DOMContentLoaded', () => {

    // 1. Navbar shadow on scroll
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        navbar?.classList.toggle('shadow', window.scrollY > 10);
    });

    // 1b. Hero video: force-restart on end as a fallback in case the browser
    // doesn't reliably honor the loop attribute on a streamed source.
    // Also hide it on error so the section's own gradient background shows
    // through cleanly instead of a broken/black video frame.
    const heroVideo = document.querySelector('.hero-video');
    if (heroVideo) {
        heroVideo.addEventListener('ended', () => {
            heroVideo.currentTime = 0;
            heroVideo.play();
        });
        heroVideo.addEventListener('error', () => {
            heroVideo.style.display = 'none';
        });
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
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // 5. Contact form validation (contact.html)
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', e => {
            e.preventDefault();
            const alertBox = document.getElementById('formAlert');
            const fields = contactForm.querySelectorAll('[required]');
            let allFilled = true;
            fields.forEach(field => {
                if (!field.value.trim()) allFilled = false;
            });

            if (!allFilled) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Veuillez remplir tous les champs obligatoires.';
                alertBox.classList.remove('d-none');
            } else {
                alertBox.className = 'alert alert-success';
                alertBox.textContent = 'Message envoyé avec succès. Nous vous répondrons dans les meilleurs délais.';
                alertBox.classList.remove('d-none');
                contactForm.reset();
            }
        });
    }
});
