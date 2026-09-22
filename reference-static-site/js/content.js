async function fetchData(name) {
    const res = await fetch(`data/${name}.json`);
    if (!res.ok) throw new Error(`Impossible de charger data/${name}.json`);
    return res.json();
}

function setLoading(container) {
    if (!container) return;
    container.innerHTML = '<p class="text-center text-muted w-100 py-4"><span class="spinner-border spinner-border-sm text-success me-2" role="status" aria-hidden="true"></span>Chargement du contenu...</p>';
}

function setError(container, label) {
    if (!container) return;
    container.innerHTML = `<p class="text-center text-muted w-100 py-4"><i class="bi bi-exclamation-circle me-1"></i>Impossible de charger ${label} pour le moment. Veuillez réessayer plus tard.</p>`;
}

function newsCoverCardHTML(item) {
    const badgeClass = item.category === 'evenement' ? 'badge-evenement' : 'badge-actualite';
    return `
        <article class="news-card h-100">
          <img src="${item.image}?w=480&h=200&fit=crop" class="news-card-cover" alt="${item.title}">
          <div class="news-card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge-news ${badgeClass}">${item.categoryLabel}</span>
              <span class="small text-muted">${item.date}</span>
            </div>
            <h5 class="h6 fw-bold">${item.title}</h5>
            <p class="small text-muted">${item.excerpt}</p>
            <a href="actualites.html" class="small fw-semibold text-success">Lire la suite →</a>
          </div>
        </article>`;
}

function newsThumbCardHTML(item) {
    const badgeClass = item.category === 'evenement' ? 'badge-evenement' : 'badge-actualite';
    return `
      <div class="col-lg-6">
        <article class="news-card h-100 d-flex flex-column flex-md-row" data-category="${item.category}">
          <img src="${item.image}?w=480&h=280&fit=crop" class="news-card-thumb" alt="${item.title}">
          <div class="news-card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge-news ${badgeClass}">${item.categoryLabel}</span>
              <span class="small text-muted">${item.date}</span>
            </div>
            <h5 class="h6 fw-bold">${item.title}</h5>
            <p class="small text-muted mb-0">${item.excerpt}</p>
          </div>
        </article>
      </div>`;
}

function staffCardHTML(item) {
    return `
      <div class="col-lg-3 col-md-4 col-6">
        <article class="staff-card h-100" data-category="${item.category}">
          <img src="${item.photo}" alt="${item.name}" class="staff-photo">
          <p class="staff-name mb-0">${item.name}</p>
          <p class="staff-grade mb-1">${item.grade}</p>
          <p class="staff-specialty mb-1">${item.specialty}</p>
          <p class="small text-muted mb-2">${item.role}</p>
          <a href="mailto:${item.email}" class="staff-email"><i class="bi bi-envelope me-1"></i>${item.email}</a>
        </article>
      </div>`;
}

function formationCardHTML(item, colClass) {
    const debouches = item.debouches.map(d => `<li>${d}</li>`).join('');
    return `
      <div class="${colClass}">
        <article class="formation-card h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="h6 fw-bold mb-0">${item.title}</h5>
            <span class="badge-news badge-actualite">${item.duration}</span>
          </div>
          <p class="small text-muted">${item.description}</p>
          <p class="small fw-semibold mb-1">Débouchés professionnels</p>
          <ul class="small text-muted ps-3 mb-3">${debouches}</ul>
          <div class="bg-light rounded p-2 small">
            <strong>Conditions d'admission :</strong> ${item.conditions}
          </div>
        </article>
      </div>`;
}

function labMiniCardHTML(item) {
    return `
      <div class="col-md-6">
        <article class="lab-card h-100">
          <i class="bi ${item.icon} text-success fs-4 mb-2 d-block"></i>
          <p class="fw-bold mb-0">${item.abbr}</p>
          <p class="small text-muted mb-0">${item.name}</p>
        </article>
      </div>`;
}

function labDetailedCardHTML(item) {
    const themes = item.themes.map(t => `<span class="badge-news badge-actualite">${t}</span>`).join('');
    return `
      <div class="col-lg-6">
        <article class="card-fseg h-100">
          <div class="d-flex align-items-start gap-3 mb-2">
            <div class="card-icon mb-0"><i class="bi ${item.icon}"></i></div>
            <div>
              <p class="fw-bold text-success mb-0">${item.abbr}</p>
              <p class="fw-semibold mb-0">${item.name}</p>
            </div>
          </div>
          <p class="small text-muted">${item.description}</p>
          <div class="d-flex flex-wrap gap-2 mb-3">${themes}</div>
          <p class="small text-muted mb-0"><i class="bi bi-people me-1"></i>${item.researchers} chercheurs</p>
        </article>
      </div>`;
}

function publicationItemHTML(item) {
    return `
        <article class="publication-item">
          <span class="pub-year mb-2">${item.year}</span>
          <h6 class="fw-bold mt-2 mb-1">${item.title}</h6>
          <p class="small text-muted mb-1">${item.authors}</p>
          <p class="small text-success mb-0"><i class="bi bi-journal me-1"></i>${item.journal}</p>
        </article>`;
}

function projectCardHTML(item) {
    return `
      <div class="col-lg-4 col-md-6">
        <article class="card-fseg h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="card-icon mb-0"><i class="bi ${item.icon}"></i></div>
            <span class="badge-news badge-actualite">${item.period}</span>
          </div>
          <h5 class="h6 fw-bold text-success">${item.code}</h5>
          <p class="small text-muted">${item.description}</p>
          <p class="small text-muted mb-0"><i class="bi bi-cash me-1"></i>${item.funder}</p>
        </article>
      </div>`;
}

function timelineItemHTML(item) {
    return `
          <article class="timeline-item">
            <div class="timeline-year">${item.year}</div>
            <h5 class="h6 fw-bold mb-1">${item.title}</h5>
            <p class="small text-muted mb-0">${item.description}</p>
          </article>`;
}

function alumniStatHTML(stat) {
    return `
          <div class="col-4">
            <article class="card-fseg h-100 p-3">
              <div class="stat-number text-success" style="font-size:1.9rem;" data-count="${stat.count}">0</div>
              <p class="small text-muted mb-0">${stat.label}</p>
            </article>
          </div>`;
}

function alumniNotableHTML(item) {
    return `
      <div class="col-lg-3 col-md-6">
        <article class="staff-card h-100">
          <img src="${item.photo}" alt="${item.name}" class="staff-photo">
          <p class="staff-name mb-0">${item.name}</p>
          <p class="staff-grade mb-1">${item.promotion}</p>
          <p class="staff-specialty mb-0">${item.role}</p>
          <p class="small text-muted mb-0">${item.organization}</p>
        </article>
      </div>`;
}

function alumniTestimonialHTML(item) {
    return `
      <div class="col-lg-4">
        <article class="card-fseg h-100">
          <i class="bi bi-quote text-success fs-3 d-block mb-2"></i>
          <p class="small text-muted fst-italic">« ${item.quote} »</p>
          <div class="d-flex align-items-center gap-2 mt-3">
            <img src="${item.photo}" alt="${item.name}" class="rounded-circle" width="42" height="42">
            <div>
              <p class="fw-bold small mb-0">${item.name}</p>
              <p class="text-muted small mb-0">${item.promotion}</p>
            </div>
          </div>
        </article>
      </div>`;
}

async function renderHomeNews() {
    const container = document.getElementById('homeNewsGrid');
    if (!container) return;
    setLoading(container);
    try {
        const news = await fetchData('news');
        const featured = news
            .filter(item => item.featured)
            .sort((a, b) => a.homeOrder - b.homeOrder);
        container.innerHTML = featured
            .map(item => `<div class="col-lg-4">${newsCoverCardHTML(item)}</div>`)
            .join('');
    } catch (err) {
        console.error(err);
        setError(container, 'les actualités');
    }
}

async function renderHomeLabs() {
    const container = document.getElementById('homeLabsGrid');
    if (!container) return;
    setLoading(container);
    try {
        const labs = await fetchData('labs');
        container.innerHTML = labs.map(labMiniCardHTML).join('');
    } catch (err) {
        console.error(err);
        setError(container, 'les laboratoires');
    }
}

async function renderNewsGrid() {
    const container = document.getElementById('newsGrid');
    if (!container) return;
    setLoading(container);
    try {
        const news = await fetchData('news');
        container.innerHTML = news.map(newsThumbCardHTML).join('');
    } catch (err) {
        console.error(err);
        setError(container, 'les actualités');
    }
}

async function renderStaffGrid() {
    const container = document.getElementById('staffGrid');
    if (!container) return;
    setLoading(container);
    try {
        const staff = await fetchData('staff');
        container.innerHTML = staff.map(staffCardHTML).join('');
    } catch (err) {
        console.error(err);
        setError(container, 'le corps enseignant');
    }
}

async function renderFormations() {
    const licenceGrid = document.getElementById('licenceGrid');
    const masterGrid = document.getElementById('masterGrid');
    const doctoratGrid = document.getElementById('doctoratGrid');
    if (!licenceGrid && !masterGrid && !doctoratGrid) return;

    [licenceGrid, masterGrid, doctoratGrid].forEach(setLoading);
    try {
        const formations = await fetchData('formations');
        if (licenceGrid) {
            licenceGrid.innerHTML = formations.licence.map(item => formationCardHTML(item, 'col-lg-4')).join('');
        }
        if (masterGrid) {
            masterGrid.innerHTML = formations.master.map(item => formationCardHTML(item, 'col-lg-4')).join('');
        }
        if (doctoratGrid) {
            doctoratGrid.innerHTML = formations.doctorat.map(item => formationCardHTML(item, 'col-lg-5')).join('');
        }
    } catch (err) {
        console.error(err);
        [licenceGrid, masterGrid, doctoratGrid].forEach(el => setError(el, 'les formations'));
    }
}

async function renderLabsDetailed() {
    const container = document.getElementById('labsGrid');
    if (!container) return;
    setLoading(container);
    try {
        const labs = await fetchData('labs');
        container.innerHTML = labs.map(labDetailedCardHTML).join('');
    } catch (err) {
        console.error(err);
        setError(container, 'les laboratoires');
    }
}

async function renderPublications() {
    const container = document.getElementById('publicationsList');
    if (!container) return;
    setLoading(container);
    try {
        const publications = await fetchData('publications');
        container.innerHTML = publications.map(publicationItemHTML).join('');
    } catch (err) {
        console.error(err);
        setError(container, 'les publications');
    }
}

async function renderProjects() {
    const container = document.getElementById('projectsGrid');
    if (!container) return;
    setLoading(container);
    try {
        const projects = await fetchData('projects');
        container.innerHTML = projects.map(projectCardHTML).join('');
    } catch (err) {
        console.error(err);
        setError(container, 'les projets');
    }
}

async function renderTimeline() {
    const container = document.getElementById('timelineContainer');
    if (!container) return;
    setLoading(container);
    try {
        const timeline = await fetchData('timeline');
        container.innerHTML = timeline.map(timelineItemHTML).join('');
    } catch (err) {
        console.error(err);
        setError(container, "l'historique");
    }
}

async function renderAlumni() {
    const statsContainer = document.getElementById('alumniStats');
    const notableContainer = document.getElementById('alumniNotableGrid');
    const testimonialsContainer = document.getElementById('alumniTestimonialsGrid');
    if (!statsContainer && !notableContainer && !testimonialsContainer) return;

    [statsContainer, notableContainer, testimonialsContainer].forEach(setLoading);
    try {
        const alumni = await fetchData('alumni');
        if (statsContainer) {
            statsContainer.innerHTML = alumni.stats.map(alumniStatHTML).join('');
            window.FSEG.initCounters(statsContainer);
        }
        if (notableContainer) {
            notableContainer.innerHTML = alumni.notable.map(alumniNotableHTML).join('');
        }
        if (testimonialsContainer) {
            testimonialsContainer.innerHTML = alumni.testimonials.map(alumniTestimonialHTML).join('');
        }
    } catch (err) {
        console.error(err);
        [statsContainer, notableContainer, testimonialsContainer].forEach(el => setError(el, 'les informations alumni'));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    renderHomeNews();
    renderHomeLabs();
    renderNewsGrid();
    renderStaffGrid();
    renderFormations();
    renderLabsDetailed();
    renderPublications();
    renderProjects();
    renderTimeline();
    renderAlumni();
});
