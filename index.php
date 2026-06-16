<?php require_once __DIR__ . '/auth_guard.php'; ?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KoopjesKoken — Lekker goedkoop koken</title>
<meta name="description" content="Goedkope recepten gebaseerd op supermarkt aanbiedingen. Kook heerlijk voor minder dan €5 per persoon.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__.'/assets/app.css') ?>">
</head>
<body>
  <!-- LOGIN BOX -->
<div id="authOverlay">
    <div class="auth-box" role="dialog" aria-modal="true" aria-labelledby="authTitle">
        <h2 id="authTitle">Login</h2>
        <form id="authForm" novalidate>
            <label for="username">Gebruikersnaam</label>
            <input type="text" id="username" name="username" placeholder="Gebruikersnaam" autocomplete="username" required>
            <div class="auth-email" id="emailContainer">
                <label for="email">E-mailadres</label>
                <input type="email" id="email" name="email" placeholder="E-mailadres" autocomplete="email">
            </div>
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" placeholder="Wachtwoord" autocomplete="current-password" required>
            <div class="auth-controls">
                <label for="rememberMe">Onthoud mij</label>
                <input type="checkbox" id="rememberMe" name="rememberMe" />
                <label id="showPasswordLabel" for="showPasswordCheckbox">Toon</label>
                <input type="checkbox" id="showPasswordCheckbox" aria-label="Wachtwoord tonen" />
                <small id="passwordHint" class="register-only">Minimaal 8 tekens</small>
            </div>

            <button type="submit" id="authSubmitButton">Login</button>
            <div id="authMessage" aria-live="polite"></div>
        </form>
        <p id="toggleText">Geen account? <span onclick="toggleAuth()">Registreren</span></p>
    </div>
</div>

<!-- NAV -->
<nav>
  <div class="nav-inner">
    <a class="logo" onclick="showPage('home')">Koopjes<span>Koken</span></a>
    <button class="nav-toggle" id="navToggle" onclick="toggleMobileNav()" aria-label="Menu openen" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links">
      <li><a onclick="showPage('home')" id="nav-home" class="active">Home</a></li>
      <li><a onclick="showPage('recepten')" id="nav-recepten">Recepten</a></li>
      <li><a onclick="showPage('aanbiedingen')" id="nav-aanbiedingen">Aanbiedingen</a></li>
      <li><a onclick="showPage('planner')" id="nav-planner">Weekplanner</a></li>
      <li><a onclick="showPage('supermarkten')" id="nav-supermarkten">Supermarkten</a></li>
    </ul>
    <div class="nav-right">
      <button class="btn-nav" onclick="openCart()">Boodschappenlijst<span class="cart-count" id="cartCount" style="display:none">0</span></button>
      <button class="btn-nav" id="authTrigger" onclick="showAuth()">Login</button>
      <button class="btn-nav" id="logoutBtn" onclick="logout()" style="display:none">Logout</button>
      <button class="btn-nav" id="themeToggle" onclick="toggleTheme()">Donker</button>
    </div>
  </div>
</nav>

<!-- ══════════════════════════════════════════ HOME PAGE ══ -->
<div id="page-home" class="page active">

  <div class="hero">
    <div class="hero-content">
      <div class="hero-label">Elke dag nieuwe aanbiedingen</div>
      <h1>Lekker koken <em>voor minder geld</em></h1>
      <p class="hero-desc">Recepten gebaseerd op supermarkt aanbiedingen bij Albert Heijn, Jumbo, Lidl en meer. Elke dag de goedkoopste maaltijden voor jou samengesteld.</p>
      <div class="hero-actions">
        <button class="btn-primary" onclick="showPage('recepten')">Bekijk recepten</button>
        <button class="btn-secondary" onclick="openModal('1')">Voorbeeld recept</button>
      </div>
      <div class="hero-stats">
        <div class="stat"><div class="stat-num">€0,95</div><div class="stat-label">Goedkoopste recept<br>van vandaag</div></div>
        <div class="stat"><div class="stat-num">240+</div><div class="stat-label">Budget recepten</div></div>
        <div class="stat"><div class="stat-num">7</div><div class="stat-label">Supermarkten<br>vergeleken</div></div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-float hero-float-1">
        <div class="float-label">Aanbieding AH</div>
        <div class="float-value">Gehakt 40% korting!</div>
      </div>
      <div class="hero-card-main">
        <div class="hero-card-img"><div class="deal-badge">Bespaar €2,60</div></div>
        <div class="hero-card-body">
          <h3>Spaghetti Bolognese</h3>
          <div class="hero-card-meta"><span>25 min</span><span>4 personen</span><span>Makkelijk</span></div>
          <div class="price-row">
            <div><div class="price-now">€1,85 <small style="font-size:0.9rem;color:#888">p.p.</small></div><div class="price-was">Was €3,25 p.p.</div></div>
            <div class="supermarkt-badges"><span class="sm-badge sm-ah">AH</span><span class="sm-badge sm-jumbo">Jumbo</span></div>
          </div>
        </div>
      </div>
      <div class="hero-float hero-float-2">
        <div class="float-label">Prijs per persoon</div>
        <div class="float-value">€1,85 — Bespaar 43%</div>
      </div>
    </div>
  </div>

  <div class="deals-strip">
    <div class="deals-strip-inner">
      <h3>Huidige aanbiedingen — koppel aan een recept</h3>
      <div class="deals-scroll" id="dealsScrollHome"></div>
    </div>
  </div>

  <section class="content-section">
    <div class="section-header"><h2>Kies jouw <span>categorie</span></h2></div>
    <div class="categories">
      <div class="category-card cat-1" onclick="setFilterCat('aanbieding')"><div class="cat-title">Aanbieding-recepten</div><div class="cat-count">48 recepten beschikbaar</div></div>
      <div class="category-card cat-2" onclick="setFilterCat('student')"><div class="cat-title">Studenten keuken</div><div class="cat-count">62 recepten onder €3</div></div>
      <div class="category-card cat-3" onclick="setFilterCat('snel')"><div class="cat-title">Klaar in 20 minuten</div><div class="cat-count">55 snelle maaltijden</div></div>
      <div class="category-card cat-4" onclick="setFilterCat('vegetarisch')"><div class="cat-title">Vegetarisch &amp; Vegan</div><div class="cat-count">73 plantaardige opties</div></div>
    </div>
  </section>

  <section class="content-section">
    <div class="deal-banner">
      <div>
        <div class="deal-banner-label">Deze week populair</div>
        <h2>Kook 5 maaltijden voor<br>minder dan €25</h2>
        <p class="deal-banner-desc">Onze weekplanner selecteert automatisch de goedkoopste recepten op basis van aanbiedingen bij jouw supermarkt.</p>
      </div>
      <button class="deal-banner-cta" onclick="showPage('planner')">Start weekplanner →</button>
    </div>
  </section>

  <section class="content-section">
    <div class="section-header">
      <h2>Populair <span>vandaag</span></h2>
      <span class="section-link" onclick="showPage('recepten')">Alle recepten →</span>
    </div>
    <div class="recipe-grid" id="homePreviewGrid"></div>
  </section>

</div><!-- /home page -->

<!-- ══════════════════════════════════════════ RECEPTEN PAGE ══ -->
<div id="page-recepten" class="page">
  <div class="page-header">
    <h1>Alle <span style="color:var(--orange)">recepten</span></h1>
    <p>Doorzoek het volledige overzicht en filter op aanbiedingen, dieet, kooktijd of budget.</p>
  </div>

  <section class="content-section" style="padding-top:0">
    <div class="search-section" style="padding-top:0">
      <div class="search-box">
        <div class="search-input-wrap">
          <input type="text" placeholder="Zoek op recept, ingrediënt of supermarkt..." id="searchInput" oninput="filterRecipes()">
        </div>
        <div class="filter-chips">
          <button class="chip active" onclick="setFilter('all',this)">Alles</button>
          <button class="chip" onclick="setFilter('aanbieding',this)">Aanbieding</button>
          <button class="chip" onclick="setFilter('snel',this)">Snel</button>
          <button class="chip" onclick="setFilter('vegetarisch',this)">Vegetarisch</button>
          <button class="chip" onclick="setFilter('vegan',this)">Vegan</button>
          <button class="chip" onclick="setFilter('budget',this)">Onder €3</button>
          <button class="chip" onclick="setFilter('favorieten',this)">Favorieten</button>
        </div>
      </div>
    </div>
    <div class="recipe-grid" id="recipeGrid"></div>
  </section>
</div>

<!-- ══════════════════════════════════════════ AANBIEDINGEN PAGE ══ -->
<div id="page-aanbiedingen" class="page">
  <div class="page-header">
    <h1>Aanbiedingen <span style="color:var(--orange)">deze week</span></h1>
    <p>Actuele kortingen bij alle grote Nederlandse supermarkten, gekoppeld aan recepten.</p>
  </div>
  <section class="content-section" style="padding-top:0">
    <div class="ai-generator-row">
      <button class="ai-generate-btn" onclick="generateAiRecipe()">Verzin een recept van deze aanbiedingen</button>
      <span class="ai-generate-hint">Claude verzint een recept op maat. Cached per dealset, ~5 sec.</span>
    </div>
    <div id="dealsRecipes" class="deals-recipes"></div>
    <div class="sm-filter-row" id="smFilterRow"></div>
    <div class="deals-grid" id="dealsGrid"></div>
  </section>
</div>

<!-- ══════════════════════════════════════════ WEEKPLANNER PAGE ══ -->
<div id="page-planner" class="page">
  <div class="page-header">
    <h1>Weekplanner <span style="color:var(--orange)">2025</span></h1>
    <p>Plan je maaltijden voor de week en zie direct hoeveel je uitgeeft en bespaart.</p>
  </div>
  <section class="content-section" style="padding-top:0">
    <div class="planner-grid" id="plannerGrid"></div>
    <div class="planner-summary" id="plannerSummary">
      <div><div class="plan-sum-val" id="planTotal">€0,00</div><div class="plan-sum-label">Totaal deze week</div></div>
      <div><div class="plan-sum-val" id="planSaving" style="color:var(--green)">€0,00</div><div class="plan-sum-label">Bespaard</div></div>
      <div><div class="plan-sum-val" id="planMeals">0</div><div class="plan-sum-label">Maaltijden gepland</div></div>
      <div><div class="plan-sum-val" id="planPerDay">€0,00</div><div class="plan-sum-label">Gemiddeld per dag</div></div>
    </div>
    <div style="margin-top:1.5rem;background:white;border-radius:var(--radius);border:1px solid var(--border);padding:1.5rem">
      <div class="modal-section-title">Voeg recept toe aan planner</div>
      <div class="recipe-grid" id="plannerRecipeGrid"></div>
    </div>
  </section>
</div>

<!-- ══════════════════════════════════════════ SUPERMARKTEN PAGE ══ -->
<div id="page-supermarkten" class="page">
  <div class="page-header">
    <h1>Supermarkten <span style="color:var(--orange)">vergelijken</span></h1>
    <p>Bekijk welke supermarkt het voordeligst is voor jouw boodschappen.</p>
  </div>
  <section class="content-section" style="padding-top:0">
    <div class="supermarkt-cards" id="supermarktCards"></div>
  </section>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand">
        <a class="logo" onclick="showPage('home')" style="color:white">Koopjes<span>Koken</span></a>
        <p>Dagelijks bijgewerkte recepten gebaseerd op supermarkt aanbiedingen in Nederland. Lekker koken hoeft niet duur te zijn.</p>
      </div>
      <div class="footer-col"><h4>Recepten</h4><ul><li><a href="#">Budget maaltijden</a></li><li><a href="#">Studenten keuken</a></li><li><a href="#">Vegetarisch</a></li><li><a href="#">Snelle maaltijden</a></li><li><a href="#">Gezinsmaaltijden</a></li></ul></div>
      <div class="footer-col"><h4>Supermarkten</h4><ul><li><a href="#">Albert Heijn</a></li><li><a href="#">Jumbo</a></li><li><a href="#">Lidl</a></li><li><a href="#">Aldi</a></li><li><a href="#">Dirk &amp; Plus</a></li></ul></div>
      <div class="footer-col"><h4>Over KoopjesKoken</h4><ul><li><a href="#">Over ons</a></li><li><a href="#">Recept indienen</a></li><li><a href="#">Cookiebeleid</a></li><li><a href="#">Privacy</a></li><li><a href="mailto:info@koopjeskoken.nl">info@koopjeskoken.nl</a></li></ul></div>
    </div>
    <div class="footer-bottom"><span>© 2025 KoopjesKoken — Alle aanbiedingen worden dagelijks bijgewerkt</span><span>Gemaakt voor Nederland</span></div>
  </div>
</footer>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModalOutside(event)">
  <div class="modal" id="modalContent"></div>
</div>

<!-- BOODSCHAPPENLIJST PANEL -->
<div class="cart-backdrop" id="cartBackdrop" onclick="closeCart()"></div>
<div class="cart-panel" id="cartPanel">
  <div class="cart-header">
    <h3>Boodschappenlijst</h3>
    <button class="cart-close" onclick="closeCart()">✕</button>
  </div>
  <div class="cart-body" id="cartBody"></div>
  <div class="cart-footer" id="cartFooter"></div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script src="assets/app.js?v=<?= filemtime(__DIR__.'/assets/app.js') ?>"></script>
</body>
</html>
