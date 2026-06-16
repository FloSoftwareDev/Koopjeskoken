// ══════════════════════════════════════════════════════
// AUTH & THEME
// ══════════════════════════════════════════════════════

let isLogin = true;
let isLoggedIn = false;
let currentUsername = null;
let authOverlay;
let authMessage;

const apiRoot = (() => {
    if (window.location.host.includes('127.0.0.1:5500') || window.location.host.includes('localhost:5500')) {
        return 'http://127.0.0.1/Koopjeskoken/api';
    }
    const p = window.location.pathname.toLowerCase();
    if (p.includes('/koopjeskoken')) {
        return window.location.origin + '/Koopjeskoken/api';
    }
    return window.location.origin + '/api';
})();

function setAuthMessage(text, type) {
    if (!authMessage) return;
    authMessage.textContent = text;
    authMessage.className = type ? type : '';
}

function toggleAuth() {
    isLogin = !isLogin;

    const emailContainer = document.getElementById('emailContainer');
    if (emailContainer) emailContainer.classList.toggle('visible', !isLogin);

    const passwordHint = document.getElementById('passwordHint');
    if (passwordHint) passwordHint.classList.toggle('visible', !isLogin);

    document.getElementById('authTitle').innerText = isLogin ? 'Login' : 'Register';
    document.getElementById('authSubmitButton').innerText = isLogin ? 'Login' : 'Register';

    document.getElementById('toggleText').innerHTML = isLogin
        ? 'Geen account? <span onclick="toggleAuth()">Registreren</span>'
        : 'Heb je al een account? <span onclick="toggleAuth()">Login</span>';

    setAuthMessage('', '');
}

function showAuth() {
    if (!authOverlay) return;
    authOverlay.style.display = 'flex';

    if (!isLogin) {
        isLogin = true;
        document.getElementById('authTitle').innerText = 'Login';
        document.getElementById('authSubmitButton').innerText = 'Login';
        document.getElementById('toggleText').innerHTML = 'Geen account? <span onclick="toggleAuth()">Registreren</span>';
        const emailContainer = document.getElementById('emailContainer');
        if (emailContainer) emailContainer.classList.remove('visible');
    }
}

async function logout() {
    try {
        await fetch(`${apiRoot}/logout.php`, { method: 'POST' });
    } catch (error) {
        console.error(error);
    } finally {
        const loginUrl = apiRoot.replace(/\/api\/?$/, '') + '/login.php';
        window.location.href = loginUrl;
    }
}

function updateAuthUI() {
    const authTrigger = document.getElementById('authTrigger');
    const logoutBtn = document.getElementById('logoutBtn');

    if (isLoggedIn) {
        if (authTrigger) authTrigger.style.display = 'none';
        if (logoutBtn) logoutBtn.style.display = 'inline-flex';
        setAuthMessage(`Ingelogd als ${currentUsername || 'gebruiker'}.`, 'success');
    } else {
        if (authTrigger) authTrigger.style.display = 'inline-flex';
        if (logoutBtn) logoutBtn.style.display = 'none';
        setAuthMessage('', '');
    }
}

function toggleMobileNav() {
  const toggle   = document.getElementById('navToggle');
  const links    = document.querySelector('.nav-links');
  const right    = document.querySelector('.nav-right');
  const isOpen   = toggle.classList.toggle('open');
  toggle.setAttribute('aria-expanded', isOpen);
  links.classList.toggle('open', isOpen);
  right.classList.toggle('open', isOpen);
}

function closeMobileNav() {
  const toggle = document.getElementById('navToggle');
  if (!toggle) return;
  toggle.classList.remove('open');
  toggle.setAttribute('aria-expanded', 'false');
  document.querySelector('.nav-links')?.classList.remove('open');
  document.querySelector('.nav-right')?.classList.remove('open');
}

function toggleTheme() {
    const isDark = document.body.classList.toggle('dark');
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.textContent = isDark ? 'Licht' : 'Donker';
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    }
}

function initTheme() {
    const saved = localStorage.getItem('theme');
    let isDark = saved ? saved === 'dark' : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.body.classList.toggle('dark', isDark);

    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) themeToggle.textContent = isDark ? 'Licht' : 'Donker';
}

function updatePasswordVisibility() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('showPasswordLabel');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.textContent = 'Verberg';
    } else {
        pwd.type = 'password';
        icon.textContent = 'Toon';
    }
}

async function submitAuth(event) {
    if (event) event.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const email = document.getElementById('email') ? document.getElementById('email').value.trim() : '';
    const submitBtn = document.getElementById('authSubmitButton');

    if (!username || !password) {
        setAuthMessage('Vul gebruikersnaam en wachtwoord in.', 'error');
        return;
    }

    if (!isLogin) {
        if (!email) {
            setAuthMessage('Vul een geldig e-mailadres in.', 'error');
            return;
        }
        if (password.length < 8) {
            setAuthMessage('Wachtwoord moet minimaal 8 tekens bevatten.', 'error');
            return;
        }
    }

    submitBtn.disabled = true;
    submitBtn.textContent = isLogin ? 'Even geduld...' : 'Registreren...';
    setAuthMessage('', '');

    try {
        const url = isLogin ? `${apiRoot}/login.php` : `${apiRoot}/register.php`;
        const rememberMe = document.getElementById('rememberMe')?.checked || false;
        const payload = isLogin ? { username, password, remember_me: rememberMe } : { username, email, password };
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        if (!res.ok) {
            const text = await res.text();
            let parsed;
            try { parsed = JSON.parse(text); } catch (e) { parsed = null; }
            const apiError = parsed?.error || parsed?.message || text || `HTTP ${res.status}`;
            setAuthMessage(apiError, 'error');
            return;
        }

        const data = await res.json();

        if (data.success) {
            isLoggedIn = true;
            currentUsername = username;
            updateAuthUI();

            setAuthMessage(isLogin ? 'Inloggen geslaagd! Je wordt doorgestuurd...' : 'Registratie geslaagd! Je kunt nu inloggen.', 'success');

            setTimeout(() => {
                if (authOverlay) authOverlay.style.display = 'none';
                if (data.role === 'admin') window.location.href = '/Koopjeskoken/admin/dashboard.php';
            }, 600);
        } else {
            setAuthMessage(data.error || 'Er is iets misgegaan, probeer opnieuw.', 'error');
        }
    } catch (error) {
        setAuthMessage('Kan geen verbinding maken met de server. Controleer XAMPP/PHP en URL.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = isLogin ? 'Login' : 'Register';
    }
}

window.addEventListener('load', async () => {
    authOverlay = document.getElementById('authOverlay');
    authMessage = document.getElementById('authMessage');

    try {
        const res = await fetch(`${apiRoot}/check_session.php`);
        const session = await res.json();

        if (session.logged_in) {
            isLoggedIn = true;
            currentUsername = session.username || null;
            if (authOverlay) authOverlay.style.display = 'none';
        }
    } catch (e) {
        console.error('Session check failed:', e);
    }

    updateAuthUI();
    initTheme();

    const passwordHint = document.getElementById('passwordHint');
    if (passwordHint) passwordHint.classList.remove('visible');

    const authForm = document.getElementById('authForm');
    if (authForm) authForm.addEventListener('submit', submitAuth);

    const showPasswordCheckbox = document.getElementById('showPasswordCheckbox');
    if (showPasswordCheckbox) showPasswordCheckbox.addEventListener('change', updatePasswordVisibility);

    // Set initial history entry so the first back press stays on home
    const validPages = ['home', 'recepten', 'aanbiedingen', 'planner', 'supermarkten'];
    const startPage  = validPages.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'home';
    history.replaceState({ page: startPage }, '', '#' + startPage);
    if (startPage !== 'home') showPage(startPage, false);

    showRecipesLoading();
    await loadRecipes();
    renderRecipes(recipes);
    renderHomePreview();
    if (startPage === 'recepten') filterRecipes();
});

function showRecipesLoading() {
    const placeholder = '<p style="color:var(--ink-soft);padding:2rem 0;grid-column:1/-1">Recepten laden…</p>';
    const grids = ['recipeGrid', 'homePreviewGrid', 'plannerRecipeGrid'];
    for (const id of grids) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = placeholder;
    }
}

window.addEventListener('popstate', e => {
  const page = e.state?.page || 'home';
  showPage(page, false);
});

// ══════════════════════════════════════════════════════
// DATA
// ══════════════════════════════════════════════════════

let recipes = [];
let recipesLoaded = false;
let recipesLoadingPromise = null;

async function loadRecipes() {
  if (recipesLoaded) return recipes;
  if (recipesLoadingPromise) return recipesLoadingPromise;
  recipesLoadingPromise = (async () => {
    try {
      const res = await fetch(`${apiRoot}/recipes.php`, { credentials: 'include' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      recipes = await res.json();
      recipesLoaded = true;
      return recipes;
    } catch (err) {
      console.error('Kon recepten niet laden:', err);
      recipes = [];
      return recipes;
    } finally {
      recipesLoadingPromise = null;
    }
  })();
  return recipesLoadingPromise;
}


let deals = [
  {id:'d1', naam:'Rundergehakt 500g', sm:'ah',    smLabel:'Albert Heijn', prijsNu:2.99, prijsWas:4.99, kortingPct:40, cat:'Vlees',   recept:'1'},
  {id:'d2', naam:'Kipfilet 400g', sm:'jumbo', smLabel:'Jumbo',        prijsNu:3.49, prijsWas:5.49, kortingPct:36, cat:'Vlees',   recept:'2'},
  {id:'d3', naam:'Pasta 500g', sm:'lidl',  smLabel:'Lidl',         prijsNu:0.59, prijsWas:0.99, kortingPct:40, cat:'Droog',   recept:'1'},
  {id:'d4', naam:'Geraspte kaas 150g', sm:'ah',    smLabel:'Albert Heijn', prijsNu:1.49, prijsWas:2.49, kortingPct:40, cat:'Zuivel',  recept:'1'},
  {id:'d5', naam:'Tomatensaus 400ml', sm:'aldi',  smLabel:'Aldi',         prijsNu:0.79, prijsWas:1.29, kortingPct:39, cat:'Sauzen',  recept:'1'},
  {id:'d6', naam:'Eieren 10 stuks', sm:'plus',  smLabel:'Plus',         prijsNu:1.99, prijsWas:2.89, kortingPct:31, cat:'Zuivel',  recept:'5'},
  {id:'d7', naam:'Uien 1kg', sm:'dirk',  smLabel:'Dirk',         prijsNu:0.69, prijsWas:1.19, kortingPct:42, cat:'Groente', recept:''},
  {id:'d8', naam:'Aardappelen 1kg', sm:'plus',  smLabel:'Plus',         prijsNu:0.89, prijsWas:1.49, kortingPct:40, cat:'Groente', recept:'4'},
  {id:'d9', naam:'Kidneybonen 2-pak', sm:'lidl',  smLabel:'Lidl',         prijsNu:0.99, prijsWas:1.69, kortingPct:41, cat:'Droog',   recept:'3'},
  {id:'d10',naam:'Spliterwten 500g', sm:'jumbo', smLabel:'Jumbo',        prijsNu:0.89, prijsWas:1.49, kortingPct:40, cat:'Droog',   recept:'6'},
  {id:'d11',naam:'Spekblokjes 150g', sm:'ah',    smLabel:'Albert Heijn', prijsNu:1.09, prijsWas:1.89, kortingPct:42, cat:'Vlees',   recept:'4'},
  {id:'d12',naam:'Rookworst', sm:'ah',    smLabel:'Albert Heijn', prijsNu:2.49, prijsWas:3.99, kortingPct:38, cat:'Vlees',   recept:'6'},
];

const supermarkten = [
  {id:'ah',    naam:'Albert Heijn', kleur:'#E8500A', bg:'#FFF0E0', tagline:'Jouw dagelijkse boodschappen', deals:5, recepten:24, avgBesparing:38},
  {id:'jumbo', naam:'Jumbo', kleur:'#C8860A', bg:'#FFF9E0', tagline:'Altijd de laagste prijs',      deals:4, recepten:18, avgBesparing:32},
  {id:'lidl',  naam:'Lidl', kleur:'#0D3FA6', bg:'#E0EAFF', tagline:'Vers & voordelig',              deals:3, recepten:15, avgBesparing:41},
  {id:'aldi',  naam:'Aldi', kleur:'#005CA8', bg:'#E0F0FF', tagline:'Gewoon goede kwaliteit',       deals:3, recepten:12, avgBesparing:35},
  {id:'plus',  naam:'Plus', kleur:'#008000', bg:'#E0F8E0', tagline:'Meer dan verwacht',            deals:3, recepten:10, avgBesparing:29},
  {id:'dirk',  naam:'Dirk', kleur:'#B00000', bg:'#FFE8E8', tagline:'Spotgoedkoop',                 deals:2, recepten:8,  avgBesparing:44},
];

const weekDagen = ['Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag','Zondag'];

// ══════════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════════
let activeFilter   = 'all';
let personsState   = {};
let cartItems      = JSON.parse(localStorage.getItem('kk_cart')    || '[]');
let plannerMeals   = {};
let activeSMFilter = 'all';
let favourites     = new Set(JSON.parse(localStorage.getItem('kk_favs') || '[]'));
window.checkedItems = JSON.parse(localStorage.getItem('kk_checked') || '{}');

function saveCart() {
  localStorage.setItem('kk_cart',    JSON.stringify(cartItems));
  localStorage.setItem('kk_checked', JSON.stringify(window.checkedItems || {}));
}
function saveFavourites() {
  localStorage.setItem('kk_favs', JSON.stringify([...favourites]));
}

function toggleFavourite(id, e) {
  e.stopPropagation();
  if (favourites.has(id)) {
    favourites.delete(id);
    showToast('Verwijderd uit favorieten');
  } else {
    favourites.add(id);
    showToast('Toegevoegd aan favorieten');
  }
  saveFavourites();
  filterRecipes();
  renderHomePreview();
  const btn = document.getElementById('fav-btn-' + id);
  if (btn) {
    btn.textContent = favourites.has(id) ? '●' : '○';
    btn.classList.toggle('is-fav', favourites.has(id));
  }
}

// ══════════════════════════════════════════════════════
// INGREDIENT SCALING
// ══════════════════════════════════════════════════════

function formatQty(val, unit) {
  if (unit === 'stuks' || unit === 'blik' || unit === 'zakje') {
    if (val < 1) {
      const fracs = [[0.25,'¼'],[0.33,'⅓'],[0.5,'½'],[0.67,'⅔'],[0.75,'¾']];
      const f = fracs.find(f => Math.abs(f[0]-val) < 0.05);
      return f ? f[1] : val.toFixed(1);
    }
    return Number.isInteger(val) ? val : val.toFixed(1);
  }
  if (val >= 1000 && (unit === 'g' || unit === 'ml')) {
    return (val/1000).toFixed(val%1000===0?0:1) + (unit==='g'?' kg':' liter');
  }
  return Math.round(val) + ' ' + unit;
}

function calcIngredients(recipe, targetPersons) {
  return recipe.ingredients.map(ing => {
    const useQty = ing.perPersoon * targetPersons;
    const useFormatted = formatQty(useQty, ing.unit);
    const pakkenNodig = Math.max(1, Math.ceil(useQty / ing.inkoopQty));
    const prijs = pakkenNodig * ing.prijsPerPak;
    const inkoopLabel = pakkenNodig === 1 ? ing.inkoop : `${pakkenNodig}× ${ing.inkoop}`;
    return { ...ing, useQty, useFormatted, pakkenNodig, prijs, inkoopLabel };
  });
}

function calcTotals(recipe, targetPersons) {
  const ings = calcIngredients(recipe, targetPersons);
  const totalNow = ings.reduce((s, i) => s + i.prijs, 0);
  const totalWas = recipe.totalWas * (targetPersons / recipe.persons);
  const saving = Math.max(0, totalWas - totalNow);
  const pricePerPerson = totalNow / targetPersons;
  return { ings, totalNow, totalWas, saving, pricePerPerson };
}

// ══════════════════════════════════════════════════════
// NAVIGATION
// ══════════════════════════════════════════════════════
function showPage(page, pushState = true) {
  closeMobileNav();
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
  const navEl = document.getElementById('nav-' + page);
  if (navEl) navEl.classList.add('active');
  window.scrollTo({top:0, behavior:'smooth'});
  if (pushState) history.pushState({ page }, '', '#' + page);

  if (page === 'recepten')     filterRecipes();
  if (page === 'aanbiedingen') renderDealsPage();
  if (page === 'planner')      renderPlanner();
  if (page === 'supermarkten') renderSupermarkten();
}

// ══════════════════════════════════════════════════════
// RECIPE CARDS
// ══════════════════════════════════════════════════════
function recipeCardHtml(r) {
  return `
    <div class="recipe-card" onclick="openModal('${r.id}')">
      <div class="card-img" style="background:${r.gradient}">
        ${r.saving > 0 ? `<div class="card-deal-badge">Bespaar €${r.saving.toFixed(2)}</div>` : ''}
        <div class="card-time-badge">${r.time} min</div>
        <button class="fav-btn ${favourites.has(r.id) ? 'is-fav' : ''}" id="fav-btn-${r.id}" onclick="toggleFavourite('${r.id}',event)" title="${favourites.has(r.id) ? 'Verwijder uit favorieten' : 'Voeg toe aan favorieten'}">${favourites.has(r.id) ? '●' : '○'}</button>
      </div>
      <div class="card-body">
        <div class="card-tags">
          ${r.tags.includes('aanbieding') ? '<span class="card-tag">Aanbieding</span>' : ''}
          ${r.diets.includes('vegan') ? '<span class="card-tag green">Vegan</span>' : ''}
          ${r.diets.includes('vegetarisch') ? '<span class="card-tag green">Vegetarisch</span>' : ''}
          <span class="card-tag gold">${r.difficulty}</span>
        </div>
        <div class="card-title">${r.name}</div>
        <div class="card-desc">${r.desc}</div>
        <div class="card-footer">
          <div class="card-price">
            <div class="price-person">€${r.priceNow.toFixed(2)}</div>
            <div class="price-label">per persoon</div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.4rem">
            <div class="card-persons">${r.persons} p. · ${r.ingredients.length} ing.</div>
            <div class="card-sm-logos">
              ${r.supermarkts.map(s=>`<div class="sm-dot sm-${s.toLowerCase()}">${s}</div>`).join('')}
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
}

function renderRecipes(list) {
  const grid = document.getElementById('recipeGrid');
  if (!list.length) {
    grid.innerHTML = `<p style="color:var(--ink-soft);padding:2rem 0;grid-column:1/-1">${activeFilter === 'favorieten' ? 'Nog geen favorieten — klik op een recept om het te bewaren.' : 'Geen recepten gevonden.'}</p>`;
    return;
  }
  grid.innerHTML = list.map(recipeCardHtml).join('');
}

function renderHomePreview() {
  const grid = document.getElementById('homePreviewGrid');
  if (!grid) return;
  const top = [...recipes].sort((a, b) => b.saving - a.saving).slice(0, 4);
  grid.innerHTML = top.map(recipeCardHtml).join('');
}

function filterRecipes() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  let list = recipes;
  if (activeFilter !== 'all') {
    list = list.filter(r => {
      if (activeFilter === 'favorieten')  return favourites.has(r.id);
      if (activeFilter === 'aanbieding')  return r.tags.includes('aanbieding');
      if (activeFilter === 'snel')        return r.time <= 20;
      if (activeFilter === 'vegetarisch') return r.diets.includes('vegetarisch') || r.diets.includes('vegan');
      if (activeFilter === 'vegan')       return r.diets.includes('vegan');
      if (activeFilter === 'budget')      return r.priceNow < 3;
      if (activeFilter === 'student')     return r.tags.includes('student');
      return true;
    });
  }
  if (q) list = list.filter(r => r.name.toLowerCase().includes(q) || r.desc.toLowerCase().includes(q));
  renderRecipes(list);
}

function setFilter(f, el) {
  activeFilter = f;
  document.querySelectorAll('#page-recepten .chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  filterRecipes();
}

function setFilterCat(f) {
  activeFilter = f;
  const chipLabel = { all:'Alles', aanbieding:'Aanbieding', snel:'Snel', vegetarisch:'Vegetarisch', vegan:'Vegan', budget:'Onder €3', favorieten:'Favorieten' }[f];
  document.querySelectorAll('#page-recepten .chip').forEach(c => {
    c.classList.toggle('active', !!chipLabel && c.textContent.trim() === chipLabel);
  });
  showPage('recepten');
}

// ══════════════════════════════════════════════════════
// DEALS STRIP (homepage)
// ══════════════════════════════════════════════════════
function renderDealsStrip() {
  const container = document.getElementById('dealsScrollHome');
  if (!deals.length) { container.innerHTML = ''; return; }

  const pill = d => `
    <div class="deal-pill" onclick="goToDeal('${d.id}')" aria-hidden="false">
      <div class="deal-pill-info">
        <div class="deal-pill-name">${d.naam}</div>
        <div class="deal-pill-sm">${d.smLabel}</div>
      </div>
      <div class="deal-pill-price">
        <div class="deal-pill-new">€${d.prijsNu.toFixed(2)}</div>
        ${d.prijsWas > 0 ? `<div class="deal-pill-old">€${d.prijsWas.toFixed(2)}</div>` : d.korting ? `<div style="font-size:0.7rem;color:var(--green);font-weight:700">${d.korting}</div>` : ''}
      </div>
    </div>`;

  container.innerHTML = `<div class="deals-track">${deals.map(pill).join('')}</div>`;

  // Defer measurement one frame so the browser has laid out the track
  requestAnimationFrame(() => {
    const track = container.querySelector('.deals-track');
    if (!track) return;
    const containerW = container.offsetWidth;
    const trackW     = track.scrollWidth;
    track.style.setProperty('--marquee-start', containerW + 'px');
    track.style.setProperty('--marquee-end',   '-' + trackW + 'px');
    track.style.animationDuration = Math.round((containerW + trackW) / 120) + 's';
  });
}

// ══════════════════════════════════════════════════════
// MODAL
// ══════════════════════════════════════════════════════
const allergenNames = {gluten:'Gluten',lactose:'Lactose',noten:'Noten',pinda:'Pinda',ei:'Ei',soja:'Soja',vis:'Vis',schaaldieren:'Schaaldieren'};

function openModal(id) {
  const r = recipes.find(x => x.id === id);
  if (!r) return;
  if (!personsState[id]) personsState[id] = r.persons;
  renderModal(r);
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function renderModal(r) {
  const p = personsState[r.id];
  const { ings, totalNow, totalWas, saving, pricePerPerson } = calcTotals(r, p);

  document.getElementById('modalContent').innerHTML = `
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div class="modal-hero" style="background:${r.gradient}">
      <div class="modal-hero-info">
        <div class="modal-badge">${r.time} min</div>
        <div class="modal-badge">${r.difficulty}</div>
        ${r.diets.map(d=>`<div class="modal-badge">${d.charAt(0).toUpperCase()+d.slice(1)}</div>`).join('')}
      </div>
    </div>
    <div class="modal-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:0.5rem">
        <div class="modal-title" style="margin-bottom:0">${r.name}</div>
        <button class="fav-btn fav-btn-modal ${favourites.has(r.id) ? 'is-fav' : ''}" id="fav-btn-${r.id}" onclick="toggleFavourite('${r.id}',event)" title="${favourites.has(r.id) ? 'Verwijder uit favorieten' : 'Voeg toe aan favorieten'}">${favourites.has(r.id) ? '●' : '○'}</button>
      </div>
      <div class="modal-meta">
        <span>${p} personen</span>
        <span>${r.ingredients.length} ingrediënten</span>
        <span>${r.nutrition.kcal} kcal p.p.</span>
        <span>${r.supermarkts.join(', ')}</span>
      </div>

      <div class="persons-adjuster">
        <span class="adj-label">Aantal personen:</span>
        <button class="adj-btn" onclick="adjustPersons('${r.id}',-1)">−</button>
        <span class="adj-count">${p}</span>
        <button class="adj-btn" onclick="adjustPersons('${r.id}',1)">+</button>
        <span style="font-size:0.78rem;color:#888;margin-left:0.25rem">Hoeveelheden en prijzen worden automatisch aangepast</span>
      </div>

      <div class="modal-grid">
        <div>
          <div class="modal-section-title">Ingrediënten</div>
          <ul class="ingredient-list">
            ${ings.map(ing => `
              <li class="ingredient-item">
                <div class="ing-left">
                  <div class="ing-name">${ing.name}</div>
                  <div class="ing-qty-recept">Gebruik: ${ing.useFormatted}</div>
                  <div class="ing-qty-inkoop">Kopen: ${ing.inkoopLabel}</div>
                </div>
                <div class="ing-right">
                  ${ing.deal ? '<span class="ing-deal">Deal</span>' : ''}
                  <span class="ing-price">€${ing.prijs.toFixed(2)}</span>
                  <span class="sm-badge sm-${ing.sm.toLowerCase()}">${ing.sm}</span>
                </div>
              </li>
            `).join('')}
          </ul>
        </div>
        <div>
          <div class="modal-section-title">Bereiding</div>
          <ol class="steps-list">
            ${r.steps.map((s,i) => `
              <li class="step-item">
                <div class="step-num">${i+1}</div>
                <div class="step-text">${s}</div>
              </li>
            `).join('')}
          </ol>
        </div>
        <div>
          <div class="modal-section-title">Allergenen</div>
          <div class="allergen-grid">
            ${Object.entries(r.allergens).map(([k,v]) => {
              const cls = v ? "allergen-yes" : "allergen-no";
              const label = v ? "Bevat" : "Geen";
              return `<div class="allergen-item ${cls}">${label} ${allergenNames[k]}</div>`;
            }).join('')}
          </div>
          <div style="margin-top:1.5rem">
            <div class="modal-section-title">Voedingswaarden p.p.</div>
            <div class="nutrition-grid">
              <div class="nutrition-item"><div class="nut-val">${r.nutrition.kcal}</div><div class="nut-label">Calorieën</div></div>
              <div class="nutrition-item"><div class="nut-val">${r.nutrition.eiwit}g</div><div class="nut-label">Eiwitten</div></div>
              <div class="nutrition-item"><div class="nut-val">${r.nutrition.koolh}g</div><div class="nut-label">Koolhydraten</div></div>
              <div class="nutrition-item"><div class="nut-val">${r.nutrition.vet}g</div><div class="nut-label">Vetten</div></div>
            </div>
          </div>
        </div>
        <div>
          <div class="price-summary">
            <div class="modal-section-title" style="margin-bottom:1rem">Prijsoverzicht</div>
            <div class="price-summary-grid">
              <div><div class="ps-val strike">€${totalWas.toFixed(2)}</div><div class="ps-label">Normale prijs (${p}p)</div></div>
              <div><div class="ps-val orange">€${totalNow.toFixed(2)}</div><div class="ps-label">Met aanbiedingen</div></div>
              <div><div class="ps-val green">€${saving.toFixed(2)}</div><div class="ps-label">Jij bespaart!</div></div>
            </div>
            <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid rgba(232,80,10,0.15);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem">
              <span style="font-size:0.85rem;color:#666">Per persoon: <strong style="color:var(--orange)">€${pricePerPerson.toFixed(2)}</strong></span>
              <button class="btn-primary" style="padding:0.6rem 1.25rem;font-size:0.85rem" onclick="addToCart('${r.id}')">
                Voeg toe aan lijst
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
}

function adjustPersons(id, delta) {
  personsState[id] = Math.max(1, Math.min(12, (personsState[id] || recipes.find(r=>r.id===id).persons) + delta));
  renderModal(recipes.find(x => x.id === id));
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
function closeModalOutside(e) {
  if (e.target === document.getElementById('modalOverlay')) closeModal();
}

// ══════════════════════════════════════════════════════
// BOODSCHAPPENLIJST (CART)
// ══════════════════════════════════════════════════════
function addToCart(recipeId) {
  const r = recipes.find(x => x.id === recipeId);
  const p = personsState[recipeId] || r.persons;
  const { ings, totalNow } = calcTotals(r, p);

  const existing = cartItems.findIndex(ci => ci.recipeId === recipeId);
  if (existing >= 0) {
    cartItems.splice(existing, 1);
    showToast(`"${r.name}" verwijderd uit lijst`);
  } else {
    cartItems.push({ recipeId, recipeName: r.name, persons: p, ingredients: ings, totalNow });
    showToast(`"${r.name}" toegevoegd aan boodschappenlijst`);
  }
  saveCart();
  updateCartCount();
}

function openCart() {
  renderCart();
  document.getElementById('cartPanel').classList.add('open');
  document.getElementById('cartBackdrop').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeCart() {
  document.getElementById('cartPanel').classList.remove('open');
  document.getElementById('cartBackdrop').classList.remove('open');
  document.body.style.overflow = '';
}

function renderCart() {
  const body = document.getElementById('cartBody');
  const footer = document.getElementById('cartFooter');

  if (cartItems.length === 0) {
    body.innerHTML = `<div class="cart-empty"><p style="font-weight:600;margin-bottom:0.5rem">Lijst is leeg</p><p style="font-size:0.85rem">Voeg recepten toe via de receptenpagina.</p></div>`;
    footer.innerHTML = '';
    return;
  }

  let html = '';
  let grandTotal = 0;

  cartItems.forEach(ci => {
    grandTotal += ci.totalNow;
    html += `<div class="cart-group">
      <div class="cart-group-title">${ci.recipeName} <span style="background:var(--orange-pale);color:var(--orange);border-radius:6px;padding:0.1rem 0.4rem;font-weight:600">${ci.persons} p.</span></div>`;
    ci.ingredients.forEach((ing, idx) => {
      const itemId = ci.recipeId + "-" + idx;
      const checked = (window.checkedItems || {})[itemId];
      html += `<div class="cart-item">
        <div class="cart-item-check ${checked ? 'checked' : ''}" onclick="toggleCartItem('${itemId}',this)">${checked ? '✓' : ''}</div>
        <div class="cart-item-info">
          <div class="cart-item-name ${checked ? 'checked-text' : ''}">${ing.name}</div>
          <div class="cart-item-detail">${ing.inkoopLabel} · ${ing.sm}</div>
        </div>
        <span class="cart-item-price">€${ing.prijs.toFixed(2)}</span>
      </div>`;
    });
    html += `<div style="text-align:right;padding:0.4rem 0 0;font-size:0.8rem;color:var(--ink-soft)">Subtotaal: <strong style="color:var(--orange)">€${ci.totalNow.toFixed(2)}</strong>
      <button class="cart-item-remove" onclick="removeFromCart('${ci.recipeId}')" title="Verwijder recept">✕</button>
    </div></div>`;
  });

  body.innerHTML = html;
  footer.innerHTML = `
    <div class="cart-total-row">
      <span class="cart-total-label">Totaal boodschappen</span>
      <span class="cart-total-val">€${grandTotal.toFixed(2)}</span>
    </div>
    <div class="cart-actions">
      <button class="btn-cart-print" onclick="window.print()">Print lijst</button>
      <button class="btn-primary" style="flex:1;justify-content:center;padding:0.75rem" onclick="closeCart()">Klaar</button>
    </div>
    <div style="text-align:center;margin-top:0.5rem">
      <button class="btn-cart-clear" onclick="clearCart()">Lijst leegmaken</button>
    </div>
  `;
}

function toggleCartItem(itemId, el) {
  if (!window.checkedItems) window.checkedItems = {};
  window.checkedItems[itemId] = !window.checkedItems[itemId];
  saveCart();
  renderCart();
}

function removeFromCart(recipeId) {
  cartItems = cartItems.filter(ci => ci.recipeId !== recipeId);
  saveCart();
  updateCartCount();
  renderCart();
}

function clearCart() {
  cartItems = [];
  window.checkedItems = {};
  saveCart();
  updateCartCount();
  renderCart();
}

function updateCartCount() {
  const el = document.getElementById('cartCount');
  if (cartItems.length > 0) {
    el.textContent = cartItems.length;
    el.style.display = 'flex';
  } else {
    el.style.display = 'none';
  }
}

// ══════════════════════════════════════════════════════
// AANBIEDINGEN PAGE
// ══════════════════════════════════════════════════════
function renderDealsPage() {
  renderDealsRecipes();
  const filterRow = document.getElementById('smFilterRow');
  const allActive = activeSMFilter === 'all' ? "active" : "";
  filterRow.innerHTML = `
    <button class="chip ${allActive}" onclick="setSMFilter('all',this)">Alle supermarkten</button>
    ${supermarkten.map(sm => {
      const smActive = activeSMFilter === sm.id ? "active" : "";
      return `<button class="chip ${smActive}" onclick="setSMFilter('${sm.id}',this)">
        <span class="sm-badge sm-${sm.id}" style="margin-right:0.25rem">${sm.naam.substring(0,2).toUpperCase()}</span> ${sm.naam}
      </button>`;
    }).join('')}
  `;

  const filtered = activeSMFilter === 'all' ? deals : deals.filter(d => d.sm === activeSMFilter);
  const grid = document.getElementById('dealsGrid');
  grid.innerHTML = filtered.map(d => {
    const pct = (d.kortingPct && isFinite(d.kortingPct) && d.kortingPct > 0 && d.kortingPct < 100)
      ? d.kortingPct
      : (d.prijsWas > 0 && d.prijsNu > 0 && d.prijsNu < d.prijsWas)
        ? Math.round((1 - d.prijsNu/d.prijsWas) * 100)
        : 0;
    const smInfo = supermarkten.find(s => s.id === d.sm);
    return `
    <div class="deal-card" data-deal-id="${d.id}" onclick="${d.recept ? `openModal('${d.recept}')` : ''}">
      <div class="deal-card-img" style="background:${smInfo?.bg||'#f5f5f5'}">
        ${pct > 0 ? `<div class="deal-pct">-${pct}%</div>` : ''}
      </div>
      <div class="deal-card-body">
        <div class="deal-card-name">${d.naam}</div>
        <div class="deal-card-sm"><span class="sm-badge sm-${d.sm}">${smInfo?.naam||d.sm}</span> · ${d.cat}</div>
        <div class="deal-card-prices">
          <div class="deal-price-now">€${d.prijsNu.toFixed(2)}</div>
          ${d.prijsWas > 0
            ? `<div class="deal-price-was">€${d.prijsWas.toFixed(2)}</div>`
            : d.korting
              ? `<div style="font-size:0.75rem;color:var(--green);font-weight:700">${d.korting}</div>`
              : ''}
        </div>
        ${d.recept ? `<div style="margin-top:0.5rem;font-size:0.72rem;color:var(--orange);font-weight:600">→ Bekijk recept</div>` : ''}
      </div>
    </div>`;
  }).join('');
}

async function generateAiRecipe() {
  const btn = document.querySelector('.ai-generate-btn');
  if (!btn || btn.dataset.loading === '1') return;

  const filtered = activeSMFilter === 'all' ? deals : deals.filter(d => d.sm === activeSMFilter);
  if (!filtered.length) {
    showToast('Geen aanbiedingen om mee te werken');
    return;
  }

  btn.dataset.loading = '1';
  const originalText = btn.textContent;
  btn.textContent = 'Claude denkt na…';
  btn.disabled = true;

  try {
    const payload = {
      deals: filtered.slice(0, 12).map(d => ({
        naam:    d.naam,
        prijsNu: d.prijsNu,
        sm:      d.sm,
        smLabel: d.smLabel,
      })),
    };

    const res = await fetch(`${apiRoot}/ai_recipe.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
      credentials: 'include',
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
      const msg = data.error || `HTTP ${res.status}`;
      showToast('Mislukt: ' + msg);
      return;
    }

    renderAiRecipe(data.recipe);
  } catch (err) {
    showToast('Netwerkfout: ' + err.message);
  } finally {
    btn.dataset.loading = '0';
    btn.textContent = originalText;
    btn.disabled = false;
  }
}

function renderAiRecipe(r) {
  const tip = r.tip && r.tip.trim() ? `<div class="ai-tip"><strong>Tip:</strong> ${escAi(r.tip)}</div>` : '';
  document.getElementById('modalContent').innerHTML = `
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div class="modal-hero" style="background:linear-gradient(135deg,#E8500A,#FF6B2B)">
      <div class="modal-hero-info">
        <div class="modal-badge">AI gegenereerd</div>
        <div class="modal-badge">${r.time} min</div>
        <div class="modal-badge">${escAi(r.difficulty)}</div>
      </div>
    </div>
    <div class="modal-body">
      <div class="modal-title">${escAi(r.name)}</div>
      <div class="modal-meta">
        <span>${r.persons} personen</span>
        <span>~€${Number(r.pricePerPerson).toFixed(2)} p.p.</span>
        <span>${r.ingredients.length} ingrediënten</span>
      </div>
      <p style="color:var(--ink);margin-bottom:1.25rem;line-height:1.55">${escAi(r.description)}</p>
      <div class="modal-grid">
        <div>
          <div class="modal-section-title">Ingrediënten</div>
          <ul class="ingredient-list">
            ${r.ingredients.map(ing => `
              <li class="ingredient-item">
                <div class="ing-left">
                  <div class="ing-name">${escAi(ing.name)} ${ing.fromDeal ? '<span class="ing-deal">aanbieding</span>' : ''}</div>
                  <div class="ing-qty-recept">${escAi(ing.quantity)}${ing.supermarket ? ' · ' + escAi(ing.supermarket) : ''}</div>
                </div>
              </li>
            `).join('')}
          </ul>
          ${tip}
        </div>
        <div>
          <div class="modal-section-title">Bereiding</div>
          <ol class="steps-list">
            ${r.steps.map((s, i) => `
              <li class="step-item">
                <div class="step-num">${i + 1}</div>
                <div class="step-text">${escAi(s)}</div>
              </li>
            `).join('')}
          </ol>
        </div>
      </div>
      <p style="margin-top:1.5rem;font-size:0.78rem;color:var(--ink-soft);text-align:center;font-style:italic">
        Door AI gegenereerd. Controleer altijd allergieën en houdbaarheid.
      </p>
    </div>
  `;
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function escAi(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function goToDeal(id) {
  showPage('aanbiedingen');
  const card = document.querySelector(`[data-deal-id="${id}"]`);
  if (!card) return;
  card.scrollIntoView({ behavior: 'smooth', block: 'center' });
  card.classList.add('deal-highlight');
  setTimeout(() => card.classList.remove('deal-highlight'), 1500);
}

function setSMFilter(id, el) {
  activeSMFilter = id;
  renderDealsPage();
}

function renderDealsRecipes() {
  const container = document.getElementById('dealsRecipes');
  if (!container) return;

  const filtered = activeSMFilter === 'all' ? deals : deals.filter(d => d.sm === activeSMFilter);
  if (!filtered.length) { container.innerHTML = ''; return; }

  // Gather all deal keywords for the visible deals
  const keywords = new Set(filtered.flatMap(d => d.trefwoorden || []));
  if (!keywords.size) { container.innerHTML = ''; return; }

  // Score each recipe by how many ingredients match deal keywords
  const scored = recipes.map(r => {
    let score = 0;
    for (const ing of r.ingredients) {
      const naam = ing.name.toLowerCase();
      for (const kw of keywords) {
        if (naam.includes(kw) || kw.includes(naam.split(' ')[0])) score++;
      }
    }
    return { r, score };
  }).filter(x => x.score > 0).sort((a, b) => b.score - a.score).slice(0, 4);

  if (!scored.length) { container.innerHTML = ''; return; }

  const smNaam = activeSMFilter === 'all'
    ? 'deze aanbiedingen'
    : (supermarkten.find(s => s.id === activeSMFilter)?.naam || 'deze supermarkt');

  container.innerHTML = `
    <div class="deals-recipes-header">
      <span class="modal-section-title">Recepten passend bij ${smNaam}</span>
      <span style="font-size:0.8rem;color:var(--ink-soft)">${scored.length} recept${scored.length > 1 ? 'en' : ''} gevonden op basis van aanbiedingen</span>
    </div>
    <div class="recipe-grid">
      ${scored.map(({ r, score }) => `
        <div class="recipe-card" onclick="openModal('${r.id}')">
          <div class="card-img" style="background:${r.gradient}">
            <div class="card-deal-badge">${score} ingrediënt${score > 1 ? 'en' : ''} in aanbieding</div>
            <div class="card-time-badge">${r.time} min</div>
            <button class="fav-btn ${favourites.has(r.id) ? 'is-fav' : ''}" id="fav-btn-dr-${r.id}" onclick="toggleFavourite('${r.id}',event)" title="Favoriet">${favourites.has(r.id) ? '●' : '○'}</button>
          </div>
          <div class="card-body">
            <div class="card-title">${r.name}</div>
            <div class="card-desc">${r.desc}</div>
            <div class="card-footer">
              <div class="card-price"><div class="price-person">€${r.priceNow.toFixed(2)}</div><div class="price-label">per persoon</div></div>
              <div class="card-persons">${r.persons} p.</div>
            </div>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

// ══════════════════════════════════════════════════════
// WEEKPLANNER PAGE
// ══════════════════════════════════════════════════════
function renderPlanner() {
  const grid = document.getElementById('plannerGrid');
  const today = new Date().getDay();
  const todayIdx = today === 0 ? 6 : today - 1;

  grid.innerHTML = weekDagen.map((dag, i) => {
    const meals = plannerMeals[i] || [];
    const isToday = i === todayIdx;
    return `<div class="planner-day">
      <div class="planner-day-header ${isToday ? 'today' : ''}">
        <span>${dag.substring(0,2)}</span>
        ${isToday ? `<span style="font-size:0.65rem">Vandaag</span>` : ""}
      </div>
      <div class="planner-slot">
        ${meals.map(m => {
          const r = recipes.find(x=>x.id===m.recipeId);
          if (!r) return '';
          const {totalNow} = calcTotals(r, m.persons);
          return `<div class="planner-meal" onclick="openModal('${r.id}')">
            <div>${r.name}</div>
            <div class="planner-meal-price">€${totalNow.toFixed(2)} · ${m.persons}p</div>
          </div>`;
        }).join('')}
        <button class="planner-add-btn" onclick="scrollToPlannerRecipes(${i})">+ Maaltijd toevoegen</button>
      </div>
    </div>`;
  }).join('');

  updatePlannerSummary();

  const pGrid = document.getElementById('plannerRecipeGrid');
  pGrid.innerHTML = recipes.map(r => `
    <div class="recipe-card" onclick="addToPlanner('${r.id}')">
      <div class="card-img" style="background:${r.gradient}">
        <div class="card-time-badge">${r.time} min</div>
      </div>
      <div class="card-body">
        <div class="card-title" style="font-size:0.95rem">${r.name}</div>
        <div class="card-footer" style="padding-top:0.5rem">
          <div class="price-person" style="font-size:1rem">€${r.priceNow.toFixed(2)} p.p.</div>
          <button class="btn-primary" style="padding:0.4rem 0.875rem;font-size:0.78rem" onclick="event.stopPropagation();addToPlanner('${r.id}')">+ Toevoegen</button>
        </div>
      </div>
    </div>
  `).join('');
}

function scrollToPlannerRecipes(dayIdx) {
  window.pendingPlannerDay = dayIdx;
  document.querySelector('#page-planner [style*="margin-top:1.5rem"]').scrollIntoView({behavior:'smooth'});
}

function addToPlanner(recipeId) {
  const day = window.pendingPlannerDay !== undefined ? window.pendingPlannerDay : new Date().getDay() === 0 ? 6 : new Date().getDay() - 1;
  if (!plannerMeals[day]) plannerMeals[day] = [];
  plannerMeals[day].push({ recipeId, persons: 2 });
  window.pendingPlannerDay = undefined;
  const r = recipes.find(x=>x.id===recipeId);
  showToast(`${r.name} toegevoegd aan ${weekDagen[day]}`);
  renderPlanner();
}

function updatePlannerSummary() {
  let total = 0, saved = 0, mealCount = 0;
  for (const day in plannerMeals) {
    for (const m of plannerMeals[day]) {
      const r = recipes.find(x=>x.id===m.recipeId);
      if (!r) continue;
      const {totalNow, saving} = calcTotals(r, m.persons);
      total += totalNow;
      saved += saving;
      mealCount++;
    }
  }
  const days = Object.keys(plannerMeals).filter(d => plannerMeals[d].length > 0).length;
  document.getElementById('planTotal').textContent = '€' + total.toFixed(2);
  document.getElementById('planSaving').textContent = '€' + saved.toFixed(2);
  document.getElementById('planMeals').textContent = mealCount;
  document.getElementById('planPerDay').textContent = days > 0 ? '€' + (total/days).toFixed(2) : '€0,00';
}

// ══════════════════════════════════════════════════════
// SUPERMARKTEN PAGE
// ══════════════════════════════════════════════════════
function renderSupermarkten() {
  const container = document.getElementById('supermarktCards');
  container.innerHTML = supermarkten.map(sm => `
    <div class="supermarkt-card" onclick="setSMFilterAndGo('${sm.id}')">
      <div class="sm-card-header">
        <div class="sm-logo-big" style="background:${sm.bg};color:${sm.kleur}">${sm.id.toUpperCase().slice(0,2)}</div>
        <div>
          <div class="sm-card-name">${sm.naam}</div>
          <div class="sm-card-tagline">${sm.tagline}</div>
        </div>
      </div>
      <div class="sm-stats">
        <div class="sm-stat"><div class="sm-stat-val">${sm.deals}</div><div class="sm-stat-label">Aanbiedingen</div></div>
        <div class="sm-stat"><div class="sm-stat-val">${sm.recepten}</div><div class="sm-stat-label">Recepten</div></div>
        <div class="sm-stat"><div class="sm-stat-val">${sm.avgBesparing}%</div><div class="sm-stat-label">Gem. besparing</div></div>
        <div class="sm-stat"><div class="sm-stat-val" style="font-size:0.8rem">Nu open</div><div class="sm-stat-label">Status</div></div>
      </div>
      <div style="margin-top:1rem">
        <button class="btn-primary" style="width:100%;justify-content:center;font-size:0.875rem" onclick="event.stopPropagation();setSMFilterAndGo('${sm.id}')">
          Bekijk aanbiedingen →
        </button>
      </div>
    </div>
  `).join('');
}

function setSMFilterAndGo(smId) {
  activeSMFilter = smId;
  showPage('aanbiedingen');
}

// ══════════════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════════════
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

// ══════════════════════════════════════════════════════
// LIVE DEALS LADEN
// ══════════════════════════════════════════════════════

const DEALS_URL = './data/deals-frontend.json';

const smNamen = { ah:'Albert Heijn', jumbo:'Jumbo', lidl:'Lidl', aldi:'Aldi', plus:'Plus', dirk:'Dirk' };

function koppelAanRecept(trefwoorden) {
  if (!trefwoorden || trefwoorden.length === 0) return null;
  let beste = null, besteScore = 0;
  for (const r of recipes) {
    let score = 0;
    for (const ing of r.ingredients) {
      const ingNaam = ing.name.toLowerCase();
      for (const tag of trefwoorden) {
        if (ingNaam.includes(tag) || tag.includes(ingNaam.split(' ')[0])) score++;
      }
    }
    if (score > besteScore) { besteScore = score; beste = r.id; }
  }
  return besteScore > 0 ? beste : null;
}

async function loadDeals() {
  const strip = document.getElementById('dealsScrollHome');
  if (strip) strip.innerHTML = '<div style="padding:1rem;color:var(--ink-soft);font-size:0.85rem">Aanbiedingen laden...</div>';

  try {
    const res = await fetch(DEALS_URL + '?t=' + Date.now());
    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const data = await res.json();

    deals = data.map(d => ({
      id:         d.id,
      naam:       d.naam,
      sm:         d.sm,
      smLabel:    smNamen[d.sm] || d.sm,
      prijsNu:    d.nu,
      prijsWas:   d.was,
      kortingPct: (d.pct && isFinite(d.pct) && d.pct > 0 && d.pct < 100) ? d.pct : 0,
      korting:    d.korting || '',
      cat:        d.cat,
      afbeelding: d.img || '',
      geldigTot:  d.tot || '',
      trefwoorden: d.tags || [],
      recept:     koppelAanRecept(d.tags),
    }));

    for (const sm of supermarkten) {
      sm.deals = deals.filter(d => d.sm === sm.id).length;
    }


  } catch (err) {
    console.warn(`Live deals niet beschikbaar (${err.message}) — dummy data actief`);
  }

  renderDealsStrip();
  if (document.getElementById('page-aanbiedingen')?.classList.contains('active')) {
    renderDealsPage();
  }
}

// ══════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════
updateCartCount();
loadDeals();

setInterval(loadDeals, 30 * 60 * 1000);
