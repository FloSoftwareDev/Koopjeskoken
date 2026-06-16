<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';

requireAdmin();

$csrfToken = generateCsrfToken();

// Derive the API base from the request so the dashboard works regardless of
// where the project is mounted (localhost/Koopjeskoken vs localhost/app vs ...).
$apiBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/api';

// Per-request nonce for the CSP script-src. Any injected <script> without
// this nonce is blocked by the browser even if escHtml() is somehow bypassed.
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
        body { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        label { display: block; margin-top: 1rem; font-weight: bold; }
        input, textarea { width: 100%; padding: .5rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: .6rem 1.4rem; cursor: pointer; }
        #message { margin-top: 1rem; font-style: italic; }
        #recipes-list { margin-top: 2rem; }
        .recipe-item { border: 1px solid #ccc; padding: 1rem; margin-bottom: 1rem; }
        .edit-btn   { background: #2980b9; color: #fff; border: none; padding: .4rem .8rem; cursor: pointer; margin-right: .4rem; }
        .delete-btn { background: #c0392b; color: #fff; border: none; padding: .4rem .8rem; cursor: pointer; }
        .cancel-btn { margin-top: 1rem; margin-left: .5rem; padding: .6rem 1.4rem; cursor: pointer; }
    </style>
</head>
<body>

<h1>Admin Dashboard</h1>
<p>Ingelogd als <strong><?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?></strong>
   &nbsp;|&nbsp; <a href="#" onclick="logout()">Uitloggen</a></p>

<hr>

<h2 id="form-heading">Recept toevoegen</h2>

<label for="title">Titel</label>
<input id="title" type="text" maxlength="255" placeholder="Naam van het recept">

<label for="description">Omschrijving</label>
<textarea id="description" rows="3" placeholder="Korte omschrijving"></textarea>

<label for="ingredients">Ingrediënten</label>
<textarea id="ingredients" rows="5" placeholder="Eén ingredient per regel"></textarea>

<label for="instructions">Bereidingswijze</label>
<textarea id="instructions" rows="6" placeholder="Stap voor stap uitleg"></textarea>

<button id="save-btn" onclick="saveRecipe()">Recept opslaan</button>
<button id="cancel-btn" class="cancel-btn" onclick="resetForm()" style="display:none">Annuleren</button>
<div id="message"></div>

<h2>Bestaande recepten</h2>
<div id="recipes-list">Laden…</div>

<script nonce="<?= htmlspecialchars($nonce) ?>">
    const CSRF    = <?= json_encode($csrfToken) ?>;
    const API_BASE = <?= json_encode($apiBase) ?>;

    async function apiFetch(url, options = {}) {
        options.headers = Object.assign({
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF
        }, options.headers || {});
        options.credentials = 'include';
        const res = await fetch(url, options);
        if (res.status === 401) { location.href = '/login.html'; return; }
        return res;
    }

    let editingId = null; // null = create mode, number = edit mode

    function setEditMode(recipe) {
        editingId = recipe.id;
        document.getElementById('title').value        = recipe.title;
        document.getElementById('description').value  = recipe.description;
        document.getElementById('ingredients').value  = recipe.ingredients;
        document.getElementById('instructions').value = recipe.instructions;
        document.getElementById('form-heading').textContent = 'Recept bewerken';
        document.getElementById('save-btn').textContent     = 'Wijzigingen opslaan';
        document.getElementById('cancel-btn').style.display = 'inline';
        document.getElementById('title').focus();
    }

    function resetForm() {
        editingId = null;
        ['title','description','ingredients','instructions'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.getElementById('form-heading').textContent = 'Recept toevoegen';
        document.getElementById('save-btn').textContent     = 'Recept opslaan';
        document.getElementById('cancel-btn').style.display = 'none';
        document.getElementById('message').textContent = '';
    }

    async function saveRecipe() {
        const title        = document.getElementById('title').value.trim();
        const description  = document.getElementById('description').value.trim();
        const ingredients  = document.getElementById('ingredients').value.trim();
        const instructions = document.getElementById('instructions').value.trim();
        const msg          = document.getElementById('message');

        if (!title || !description || !ingredients || !instructions) {
            msg.textContent = 'Vul alle velden in.';
            return;
        }

        const isEdit = editingId !== null;
        const url    = isEdit ? `${API_BASE}/recipes.php?id=${editingId}` : `${API_BASE}/recipes.php`;
        const res    = await apiFetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            body: JSON.stringify({ title, description, ingredients, instructions }),
        });

        const data = await res.json();
        if (data.success) {
            msg.textContent = isEdit ? 'Recept bijgewerkt!' : 'Recept opgeslagen!';
            resetForm();
            loadRecipes();
        } else {
            msg.textContent = 'Fout: ' + (data.error || 'Onbekende fout');
        }
    }

    async function loadRecipes() {
        const res  = await fetch(API_BASE + '/recipes.php', { credentials: 'include' });
        const list = await res.json();
        const container = document.getElementById('recipes-list');

        if (!list.length) { container.innerHTML = '<p>Nog geen recepten.</p>'; return; }

        container.innerHTML = list.map(r => `
            <div class="recipe-item">
                <strong>${escHtml(r.title)}</strong>
                <p>${escHtml(r.description)}</p>
                <button class="edit-btn"   onclick="editRecipe(${r.id})">Bewerken</button>
                <button class="delete-btn" onclick="deleteRecipe(${r.id})">Verwijderen</button>
            </div>
        `).join('');
    }

    async function editRecipe(id) {
        const res  = await fetch(API_BASE + '/recipes.php', { credentials: 'include' });
        const list = await res.json();
        const recipe = list.find(r => r.id === id);
        if (recipe) setEditMode(recipe);
    }

    async function deleteRecipe(id) {
        if (!confirm('Weet je zeker dat je dit recept wilt verwijderen?')) return;

        const res  = await apiFetch(`${API_BASE}/recipes.php?id=${id}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.success) {
            if (editingId === id) resetForm();
            loadRecipes();
        } else {
            alert('Verwijderen mislukt: ' + (data.error || 'Onbekende fout'));
        }
    }

    async function logout() {
        await apiFetch(API_BASE + '/logout.php', { method: 'POST' });
        location.href = '/login.html';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    loadRecipes();
</script>

</body>
</html>