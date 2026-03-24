<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';

startSecureSession();
requireAdmin();

$csrfToken = generateCsrfToken();
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
        .delete-btn { background: #c0392b; color: #fff; border: none; padding: .4rem .8rem; cursor: pointer; }
    </style>
</head>
<body>

<h1>Admin Dashboard</h1>
<p>Ingelogd als <strong><?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?></strong>
   &nbsp;|&nbsp; <a href="#" onclick="logout()">Uitloggen</a></p>

<hr>

<h2>Recept toevoegen</h2>

<label for="title">Titel</label>
<input id="title" type="text" maxlength="255" placeholder="Naam van het recept">

<label for="description">Omschrijving</label>
<textarea id="description" rows="3" placeholder="Korte omschrijving"></textarea>

<label for="ingredients">Ingrediënten</label>
<textarea id="ingredients" rows="5" placeholder="Eén ingredient per regel"></textarea>

<label for="instructions">Bereidingswijze</label>
<textarea id="instructions" rows="6" placeholder="Stap voor stap uitleg"></textarea>

<button onclick="addRecipe()">Recept opslaan</button>
<div id="message"></div>

<h2>Bestaande recepten</h2>
<div id="recipes-list">Laden…</div>

<script>
    const CSRF = <?= json_encode($csrfToken) ?>;

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

    async function addRecipe() {
        const title        = document.getElementById('title').value.trim();
        const description  = document.getElementById('description').value.trim();
        const ingredients  = document.getElementById('ingredients').value.trim();
        const instructions = document.getElementById('instructions').value.trim();
        const msg          = document.getElementById('message');

        if (!title || !description || !ingredients || !instructions) {
            msg.textContent = 'Vul alle velden in.';
            return;
        }

        const res = await apiFetch('/Koopjeskoken/api/recipes.php', {
            method: 'POST',
            body: JSON.stringify({ title, description, ingredients, instructions })
        });

        const data = await res.json();
        if (data.success) {
            msg.textContent = 'Recept opgeslagen!';
            document.getElementById('title').value        = '';
            document.getElementById('description').value  = '';
            document.getElementById('ingredients').value  = '';
            document.getElementById('instructions').value = '';
            loadRecipes();
        } else {
            msg.textContent = 'Fout: ' + (data.error || 'Onbekende fout');
        }
    }

    async function loadRecipes() {
        const res  = await fetch('/Koopjeskoken/api/recipes.php', { credentials: 'include' });
        const list = await res.json();
        const container = document.getElementById('recipes-list');

        if (!list.length) { container.innerHTML = '<p>Nog geen recepten.</p>'; return; }

        container.innerHTML = list.map(r => `
            <div class="recipe-item">
                <strong>${escHtml(r.title)}</strong>
                <p>${escHtml(r.description)}</p>
                <button class="delete-btn" onclick="deleteRecipe(${r.id})">Verwijderen</button>
            </div>
        `).join('');
    }

    async function deleteRecipe(id) {
        if (!confirm('Weet je zeker dat je dit recept wilt verwijderen?')) return;

        const res  = await apiFetch(`/Koopjeskoken/api/recipes.php?id=${id}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.success) {
            loadRecipes();
        } else {
            alert('Verwijderen mislukt: ' + (data.error || 'Onbekende fout'));
        }
    }

    async function logout() {
        await apiFetch('/Koopjeskoken/api/logout.php', { method: 'POST' });
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