<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';

requireAdmin();

$csrfToken = generateCsrfToken();

$apiBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/api';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'unsafe-inline'; connect-src 'self'; font-src 'self'");
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard – Koopjeskoken</title>
<style>
  :root { --orange:#E8500A; --ink:#1a1410; --border:#e0e0e0; --bg:#faf7f2; --muted:#777; --danger:#c0392b; }
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,sans-serif; max-width:1100px; margin:1rem auto; padding:0 1rem; background:var(--bg); color:var(--ink); }
  h1 { margin:0 0 .25rem; }
  header { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:1rem; }
  header a { color:var(--orange); text-decoration:none; font-size:.9rem; }
  fieldset { border:1px solid var(--border); border-radius:8px; padding:1rem; margin-bottom:1rem; background:white; }
  legend { font-weight:600; padding:0 .5rem; }
  label { display:block; font-size:.85rem; font-weight:600; margin:.5rem 0 .2rem; }
  label.inline { display:inline-flex; align-items:center; gap:.3rem; font-weight:normal; margin:.2rem .8rem .2rem 0; }
  input[type="text"], input[type="number"], textarea, select {
    width:100%; padding:.5rem; border:1px solid var(--border); border-radius:6px; font:inherit; background:white;
  }
  textarea { resize:vertical; min-height:60px; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
  .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; }
  .grid-4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:1rem; }
  .grid-5 { display:grid; grid-template-columns:repeat(5,1fr); gap:.5rem; }
  .chips { display:flex; flex-wrap:wrap; gap:.4rem; }
  .chip { padding:.3rem .7rem; border:1px solid var(--border); border-radius:100px; cursor:pointer; font-size:.85rem; background:white; user-select:none; }
  .chip.on { background:var(--orange); color:white; border-color:var(--orange); }
  .ingredient-row { display:grid; grid-template-columns:1.5fr .8fr .7fr 1fr .7fr .7fr .5fr .5fr .7fr auto; gap:.4rem; align-items:center; margin-bottom:.4rem; }
  .ingredient-row input, .ingredient-row select { padding:.35rem; font-size:.85rem; }
  .step-row { display:grid; grid-template-columns:auto 1fr auto; gap:.5rem; align-items:start; margin-bottom:.4rem; }
  .step-row .num { font-weight:700; color:var(--muted); padding-top:.4rem; }
  button { padding:.5rem 1rem; border:none; border-radius:6px; cursor:pointer; font:inherit; font-weight:600; }
  .btn-primary { background:var(--orange); color:white; }
  .btn-secondary { background:#eee; color:var(--ink); }
  .btn-danger { background:var(--danger); color:white; }
  .btn-small { padding:.3rem .6rem; font-size:.8rem; }
  .actions { margin-top:1rem; display:flex; gap:.5rem; }
  #message { margin-top:.5rem; font-style:italic; min-height:1.2em; }
  #message.error { color:var(--danger); font-style:normal; font-weight:600; }
  #message.success { color:#2a7a3b; font-style:normal; font-weight:600; }
  .recipe-list { background:white; border:1px solid var(--border); border-radius:8px; padding:.5rem; max-height:600px; overflow-y:auto; }
  .recipe-row { display:flex; justify-content:space-between; align-items:center; padding:.5rem; border-bottom:1px solid var(--border); }
  .recipe-row:last-child { border-bottom:none; }
  .recipe-row .meta { font-size:.8rem; color:var(--muted); }
  .filter-bar { display:flex; gap:.5rem; margin-bottom:.5rem; }
  .filter-bar input { flex:1; }
</style>
</head>
<body>

<header>
  <div>
    <h1>Admin Dashboard</h1>
    <p>Ingelogd als <strong><?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?></strong></p>
  </div>
  <div>
    <a href="<?= htmlspecialchars(dirname(dirname($_SERVER['SCRIPT_NAME']))) ?>/index.php">← Terug naar site</a>
    &nbsp;|&nbsp;
    <a href="#" id="logout-link">Uitloggen</a>
  </div>
</header>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem; align-items:start;">

  <div>
    <fieldset>
      <legend id="form-heading">Recept toevoegen</legend>

      <div class="grid-2">
        <div><label>Slug (URL-ID, optioneel)</label><input type="text" id="slug" placeholder="bijv. 201"></div>
        <div><label>Naam</label><input type="text" id="name" required></div>
      </div>

      <label>Omschrijving</label>
      <textarea id="desc" rows="2"></textarea>

      <label>Gradient (CSS)</label>
      <input type="text" id="gradient" placeholder="linear-gradient(135deg,#F4A261,#E76F51)">

      <div class="grid-3">
        <div><label>Tijd (min)</label><input type="number" id="time" min="1" max="600" required></div>
        <div><label>Personen</label><input type="number" id="persons" min="1" max="20" required></div>
        <div>
          <label>Moeilijkheid</label>
          <select id="difficulty">
            <option>Makkelijk</option><option>Gemiddeld</option><option>Moeilijk</option>
          </select>
        </div>
      </div>

      <label>Prijzen</label>
      <div class="grid-5">
        <div><label>priceNow</label><input type="number" step="0.01" id="priceNow" required></div>
        <div><label>priceWas</label><input type="number" step="0.01" id="priceWas" required></div>
        <div><label>totalNow</label><input type="number" step="0.01" id="totalNow" required></div>
        <div><label>totalWas</label><input type="number" step="0.01" id="totalWas" required></div>
        <div><label>saving</label><input type="number" step="0.01" id="saving" required></div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Tags / diëten / supermarkten</legend>
      <label>Tags</label>
      <div class="chips" id="tagsChips" data-options="aanbieding,snel,budget,student,vegetarisch,vegan"></div>
      <label>Diëten</label>
      <div class="chips" id="dietsChips" data-options="vegetarisch,vegan"></div>
      <label>Supermarkten</label>
      <div class="chips" id="smChips" data-options="AH,Jumbo,Lidl,Aldi,Plus,Dirk"></div>
    </fieldset>

    <fieldset>
      <legend>Allergenen (bevat)</legend>
      <div id="allergenChecks" data-options="gluten,lactose,noten,pinda,ei,soja,vis,schaaldieren"></div>
    </fieldset>

    <fieldset>
      <legend>Voedingswaarden p.p.</legend>
      <div class="grid-4">
        <div><label>Calorieën</label><input type="number" id="nut_kcal" min="0" required></div>
        <div><label>Eiwit (g)</label><input type="number" id="nut_eiwit" min="0" required></div>
        <div><label>Koolh. (g)</label><input type="number" id="nut_koolh" min="0" required></div>
        <div><label>Vet (g)</label><input type="number" id="nut_vet" min="0" required></div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Ingrediënten</legend>
      <div class="ingredient-row" style="font-size:.75rem; color:var(--muted); font-weight:600;">
        <div>Naam</div><div>perPersoon</div><div>unit</div><div>inkoop</div><div>inkoopQty</div><div>prijsPerPak</div><div>pakken</div><div>deal</div><div>sm</div><div></div>
      </div>
      <div id="ingredients"></div>
      <button type="button" class="btn-secondary btn-small" id="add-ingredient">+ Ingrediënt</button>
    </fieldset>

    <fieldset>
      <legend>Bereiding (stappen)</legend>
      <div id="steps"></div>
      <button type="button" class="btn-secondary btn-small" id="add-step">+ Stap</button>
    </fieldset>

    <div class="actions">
      <button class="btn-primary" id="save-btn">Recept opslaan</button>
      <button class="btn-secondary" id="cancel-btn" style="display:none">Annuleren</button>
      <div id="message"></div>
    </div>
  </div>

  <div>
    <h2 style="margin-top:0">Bestaande recepten</h2>
    <div class="filter-bar">
      <input type="text" id="search" placeholder="Zoek op naam…">
    </div>
    <div class="recipe-list" id="recipes-list">Laden…</div>
  </div>

</div>

<script nonce="<?= htmlspecialchars($nonce) ?>">
const CSRF     = <?= json_encode($csrfToken) ?>;
const API_BASE = <?= json_encode($apiBase) ?>;

const UNITS = ['g','ml','stuks','el','tl','teentjes','blik','zakje'];
const SMS   = ['AH','Jumbo','Lidl','Aldi','Plus','Dirk'];

let allRecipes = [];
let editingId  = null;

async function api(method, path, body) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        credentials: 'include',
    };
    if (body !== undefined) opts.body = JSON.stringify(body);
    const res = await fetch(API_BASE + path, opts);
    if (res.status === 401 || res.status === 403) {
        location.href = '../login.php';
        return null;
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
}

function showMessage(text, type) {
    const m = document.getElementById('message');
    m.textContent = text;
    m.className = type || '';
    if (type === 'success') setTimeout(() => { if (m.textContent === text) { m.textContent=''; m.className=''; } }, 3000);
}

// ── Chips ───────────────────────────────────────────────────────────────────
function buildChips(container, options) {
    container.innerHTML = '';
    for (const opt of options) {
        const c = document.createElement('button');
        c.type = 'button';
        c.className = 'chip';
        c.dataset.value = opt;
        c.textContent = opt;
        c.addEventListener('click', () => c.classList.toggle('on'));
        container.appendChild(c);
    }
}
function readChips(container) {
    return [...container.querySelectorAll('.chip.on')].map(c => c.dataset.value);
}
function setChips(container, values) {
    const set = new Set(values || []);
    for (const c of container.querySelectorAll('.chip')) c.classList.toggle('on', set.has(c.dataset.value));
}

// ── Allergens (checkboxes) ──────────────────────────────────────────────────
function buildAllergens(container, options) {
    container.innerHTML = '';
    for (const opt of options) {
        const lab = document.createElement('label');
        lab.className = 'inline';
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.dataset.value = opt;
        lab.appendChild(cb);
        lab.appendChild(document.createTextNode(opt));
        container.appendChild(lab);
    }
}
function readAllergens(container) {
    const out = {};
    for (const cb of container.querySelectorAll('input[type=checkbox]')) out[cb.dataset.value] = cb.checked;
    return out;
}
function setAllergens(container, values) {
    for (const cb of container.querySelectorAll('input[type=checkbox]')) cb.checked = !!(values && values[cb.dataset.value]);
}

// ── Ingredient rows ─────────────────────────────────────────────────────────
function addIngredientRow(ing) {
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.innerHTML = `
      <input type="text" data-k="name" placeholder="naam" value="${esc(ing?.name)}">
      <input type="number" step="0.01" data-k="perPersoon" placeholder="100" value="${ing?.perPersoon ?? ''}">
      <select data-k="unit">${UNITS.map(u => `<option ${ing?.unit===u?'selected':''}>${u}</option>`).join('')}</select>
      <input type="text" data-k="inkoop" placeholder="500g pak" value="${esc(ing?.inkoop)}">
      <input type="number" step="0.01" data-k="inkoopQty" placeholder="500" value="${ing?.inkoopQty ?? ''}">
      <input type="number" step="0.01" data-k="prijsPerPak" placeholder="0.59" value="${ing?.prijsPerPak ?? ''}">
      <input type="number" data-k="pakkenBase" placeholder="1" value="${ing?.pakkenBase ?? 1}">
      <input type="checkbox" data-k="deal" ${ing?.deal?'checked':''}>
      <select data-k="sm">${SMS.map(s => `<option ${ing?.sm===s?'selected':''}>${s}</option>`).join('')}</select>
      <button type="button" class="btn-danger btn-small" data-act="del">×</button>
    `;
    row.querySelector('[data-act=del]').addEventListener('click', () => row.remove());
    document.getElementById('ingredients').appendChild(row);
}
function readIngredients() {
    const rows = document.querySelectorAll('#ingredients .ingredient-row');
    const out = [];
    for (const row of rows) {
        const get = k => row.querySelector(`[data-k="${k}"]`);
        const name = get('name').value.trim();
        if (!name) continue;
        out.push({
            name,
            perPersoon: parseFloat(get('perPersoon').value) || 0,
            unit:       get('unit').value,
            inkoop:     get('inkoop').value.trim(),
            inkoopQty:  parseFloat(get('inkoopQty').value) || 0,
            prijsPerPak:parseFloat(get('prijsPerPak').value) || 0,
            pakkenBase: parseInt(get('pakkenBase').value, 10) || 1,
            deal:       get('deal').checked,
            sm:         get('sm').value,
        });
    }
    return out;
}

// ── Step rows ───────────────────────────────────────────────────────────────
function addStepRow(text) {
    const wrap = document.getElementById('steps');
    const row = document.createElement('div');
    row.className = 'step-row';
    const num = wrap.children.length + 1;
    row.innerHTML = `
      <span class="num">${num}.</span>
      <textarea rows="2" placeholder="Beschrijving">${esc(text || '')}</textarea>
      <button type="button" class="btn-danger btn-small" data-act="del">×</button>
    `;
    row.querySelector('[data-act=del]').addEventListener('click', () => { row.remove(); renumberSteps(); });
    wrap.appendChild(row);
}
function renumberSteps() {
    const rows = document.querySelectorAll('#steps .step-row');
    rows.forEach((r, i) => { r.querySelector('.num').textContent = (i+1) + '.'; });
}
function readSteps() {
    return [...document.querySelectorAll('#steps textarea')]
        .map(t => t.value.trim())
        .filter(Boolean);
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Form state ──────────────────────────────────────────────────────────────
function resetForm() {
    editingId = null;
    document.getElementById('form-heading').textContent = 'Recept toevoegen';
    document.getElementById('save-btn').textContent     = 'Recept opslaan';
    document.getElementById('cancel-btn').style.display = 'none';
    ['slug','name','desc','gradient','time','persons','priceNow','priceWas','totalNow','totalWas','saving','nut_kcal','nut_eiwit','nut_koolh','nut_vet']
        .forEach(id => document.getElementById(id).value = '');
    document.getElementById('difficulty').value = 'Makkelijk';
    setChips(document.getElementById('tagsChips'), []);
    setChips(document.getElementById('dietsChips'), []);
    setChips(document.getElementById('smChips'), []);
    setAllergens(document.getElementById('allergenChecks'), {});
    document.getElementById('ingredients').innerHTML = '';
    document.getElementById('steps').innerHTML = '';
    addIngredientRow();
    addStepRow();
    showMessage('', '');
}

function loadIntoForm(r) {
    editingId = r._dbId;
    document.getElementById('form-heading').textContent = `Recept bewerken — ${r.name}`;
    document.getElementById('save-btn').textContent     = 'Wijzigingen opslaan';
    document.getElementById('cancel-btn').style.display = 'inline-flex';

    document.getElementById('slug').value       = r.id;
    document.getElementById('name').value       = r.name;
    document.getElementById('desc').value       = r.desc || '';
    document.getElementById('gradient').value   = r.gradient || '';
    document.getElementById('time').value       = r.time;
    document.getElementById('persons').value    = r.persons;
    document.getElementById('difficulty').value = r.difficulty;
    document.getElementById('priceNow').value   = r.priceNow;
    document.getElementById('priceWas').value   = r.priceWas;
    document.getElementById('totalNow').value   = r.totalNow;
    document.getElementById('totalWas').value   = r.totalWas;
    document.getElementById('saving').value     = r.saving;
    document.getElementById('nut_kcal').value   = r.nutrition?.kcal ?? '';
    document.getElementById('nut_eiwit').value  = r.nutrition?.eiwit ?? '';
    document.getElementById('nut_koolh').value  = r.nutrition?.koolh ?? '';
    document.getElementById('nut_vet').value    = r.nutrition?.vet ?? '';

    setChips(document.getElementById('tagsChips'), r.tags);
    setChips(document.getElementById('dietsChips'), r.diets);
    setChips(document.getElementById('smChips'), r.supermarkts);
    setAllergens(document.getElementById('allergenChecks'), r.allergens);

    document.getElementById('ingredients').innerHTML = '';
    (r.ingredients || []).forEach(addIngredientRow);
    if (!r.ingredients?.length) addIngredientRow();

    document.getElementById('steps').innerHTML = '';
    (r.steps || []).forEach(addStepRow);
    if (!r.steps?.length) addStepRow();

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function saveRecipe() {
    const payload = {
        id:          document.getElementById('slug').value.trim() || undefined,
        name:        document.getElementById('name').value.trim(),
        desc:        document.getElementById('desc').value.trim(),
        gradient:    document.getElementById('gradient').value.trim(),
        time:        parseInt(document.getElementById('time').value, 10),
        persons:     parseInt(document.getElementById('persons').value, 10),
        difficulty:  document.getElementById('difficulty').value,
        priceNow:    parseFloat(document.getElementById('priceNow').value) || 0,
        priceWas:    parseFloat(document.getElementById('priceWas').value) || 0,
        totalNow:    parseFloat(document.getElementById('totalNow').value) || 0,
        totalWas:    parseFloat(document.getElementById('totalWas').value) || 0,
        saving:      parseFloat(document.getElementById('saving').value) || 0,
        tags:        readChips(document.getElementById('tagsChips')),
        diets:       readChips(document.getElementById('dietsChips')),
        supermarkts: readChips(document.getElementById('smChips')),
        allergens:   readAllergens(document.getElementById('allergenChecks')),
        nutrition: {
            kcal:  parseInt(document.getElementById('nut_kcal').value, 10) || 0,
            eiwit: parseInt(document.getElementById('nut_eiwit').value, 10) || 0,
            koolh: parseInt(document.getElementById('nut_koolh').value, 10) || 0,
            vet:   parseInt(document.getElementById('nut_vet').value, 10) || 0,
        },
        ingredients: readIngredients(),
        steps:       readSteps(),
    };
    if (!payload.name) { showMessage('Naam is verplicht', 'error'); return; }
    if (!payload.time || !payload.persons) { showMessage('Tijd en personen zijn verplicht', 'error'); return; }

    try {
        if (editingId) {
            await api('PUT', `/recipes.php?id=${editingId}`, payload);
            showMessage('Recept bijgewerkt', 'success');
        } else {
            await api('POST', '/recipes.php', payload);
            showMessage('Recept toegevoegd', 'success');
        }
        resetForm();
        await refreshRecipeList();
    } catch (e) {
        showMessage(e.message, 'error');
    }
}

async function deleteRecipe(dbId, name) {
    if (!confirm(`"${name}" definitief verwijderen?`)) return;
    try {
        await api('DELETE', `/recipes.php?id=${dbId}`);
        showMessage('Verwijderd', 'success');
        if (editingId === dbId) resetForm();
        await refreshRecipeList();
    } catch (e) {
        showMessage(e.message, 'error');
    }
}

async function refreshRecipeList() {
    const list = document.getElementById('recipes-list');
    list.textContent = 'Laden…';
    try {
        const recipes = await api('GET', '/recipes.php');
        // Need the DB ids too — fetch one by slug to get those? Or include in GET response.
        // For now: GET only returns shape. We track ids via separate map.
        // Workaround: keep allRecipes with the slug, and add a hidden field _dbId by querying admin meta.
        // Simpler: a tiny extra endpoint isn't worth it; do a GET ?id=dbId on edit. But list needs ids for delete.
        // Refactor: extend GET to include _dbId.
        allRecipes = recipes;
        renderList();
    } catch (e) {
        list.innerHTML = '<p style="color:var(--danger)">' + e.message + '</p>';
    }
}

function renderList() {
    const q = document.getElementById('search').value.trim().toLowerCase();
    const list = document.getElementById('recipes-list');
    const filtered = q ? allRecipes.filter(r => r.name.toLowerCase().includes(q)) : allRecipes;
    if (!filtered.length) { list.innerHTML = '<p style="color:var(--muted)">Geen recepten.</p>'; return; }
    list.innerHTML = filtered.map(r => `
      <div class="recipe-row">
        <div>
          <strong>${esc(r.name)}</strong>
          <div class="meta">slug ${esc(r.id)} · ${r.time}min · ${r.persons}p · €${r.priceNow.toFixed(2)} p.p.</div>
        </div>
        <div>
          <button type="button" class="btn-secondary btn-small" data-act="edit" data-slug="${esc(r.id)}">Bewerk</button>
          <button type="button" class="btn-danger btn-small" data-act="del" data-slug="${esc(r.id)}">×</button>
        </div>
      </div>
    `).join('');
    list.querySelectorAll('[data-act=edit]').forEach(b => b.addEventListener('click', () => onEditClick(b.dataset.slug)));
    list.querySelectorAll('[data-act=del]').forEach(b => b.addEventListener('click', () => onDeleteClick(b.dataset.slug)));
}

async function onEditClick(slug) {
    try {
        const r = await api('GET', `/recipes.php?slug=${encodeURIComponent(slug)}`);
        if (r.error) throw new Error(r.error);
        loadIntoForm(r);
    } catch (e) {
        showMessage(e.message, 'error');
    }
}

async function onDeleteClick(slug) {
    const r = allRecipes.find(x => x.id === slug);
    if (!r) return;
    await deleteRecipe(r._dbId, r.name);
}

async function logout() {
    try { await fetch(API_BASE + '/logout.php', { method: 'POST', credentials:'include' }); } catch {}
    location.href = '../login.php';
}

// ── Init ────────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    buildChips(document.getElementById('tagsChips'),  document.getElementById('tagsChips').dataset.options.split(','));
    buildChips(document.getElementById('dietsChips'), document.getElementById('dietsChips').dataset.options.split(','));
    buildChips(document.getElementById('smChips'),    document.getElementById('smChips').dataset.options.split(','));
    buildAllergens(document.getElementById('allergenChecks'), document.getElementById('allergenChecks').dataset.options.split(','));
    addIngredientRow();
    addStepRow();
    document.getElementById('add-ingredient').addEventListener('click', () => addIngredientRow());
    document.getElementById('add-step').addEventListener('click', () => addStepRow());
    document.getElementById('save-btn').addEventListener('click', saveRecipe);
    document.getElementById('cancel-btn').addEventListener('click', resetForm);
    document.getElementById('search').addEventListener('input', renderList);
    document.getElementById('logout-link').addEventListener('click', e => { e.preventDefault(); logout(); });
    refreshRecipeList();
});
</script>
</body>
</html>
