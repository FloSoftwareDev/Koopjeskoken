/**
 * KoopjesKoken — Alle supermarkt scrapers
 * AH via mobile API, overige via Puppeteer
 */

const axios = require('axios');
let puppeteer;
try { puppeteer = require('puppeteer'); } catch(e) { puppeteer = null; }

// ═══════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════

function parseNLPrice(text) {
  if (!text && text !== 0) return 0;
  const clean = String(text).replace(/[€\s\n\r]/g, '').replace(',', '.');
  const num = parseFloat(clean);
  return isNaN(num) ? 0 : Math.round(num * 100) / 100;
}

function normalizeProduct(raw, supermarkt) {
  const prijsNu  = parseNLPrice(raw.prijsNu);
  const prijsWas = parseNLPrice(raw.prijsWas);

  let kortingPct = 0;
  if (prijsWas > 0 && prijsNu > 0 && prijsNu < prijsWas) {
    kortingPct = Math.round((1 - prijsNu / prijsWas) * 100);
  } else if (raw.kortingPct && isFinite(raw.kortingPct)) {
    kortingPct = parseInt(raw.kortingPct);
  }
  if (!isFinite(kortingPct) || kortingPct < 0 || kortingPct > 99) kortingPct = 0;

  const naam = (raw.naam || '').trim();

  return {
    id:           `${supermarkt}-${raw.id || naam.toLowerCase().replace(/[^a-z0-9]/g,'-').slice(0,30)}-${Date.now()%10000}`,
    supermarkt,
    naam,
    beschrijving: (raw.beschrijving || '').trim(),
    categorie:    (raw.categorie || 'Aanbieding').trim(),
    prijsNu,
    prijsWas,
    kortingPct,
    korting:      (raw.korting || '').trim(),
    afbeelding:   raw.afbeelding || '',
    geldigTot:    normalizeDate(raw.geldigTot),
    geldigVanaf:  normalizeDate(raw.geldigVanaf),
    merk:         (raw.merk || '').trim(),
    trefwoorden:  extractTrefwoorden(naam, raw.beschrijving || '', raw.categorie || ''),
    opgehaald:    new Date().toISOString(),
  };
}

function normalizeDate(d) {
  if (!d) return '';
  try { return new Date(d).toISOString().split('T')[0]; } catch { return ''; }
}

function extractTrefwoorden(naam, beschrijving, categorie) {
  const tekst = `${naam} ${beschrijving} ${categorie}`.toLowerCase();
  const trefwoorden = new Set();
  const mapping = [
    { r: /runder(gehakt|steak)|half.om.half/i, t: 'gehakt' },
    { r: /varkens(gehakt|vlees|haas)|spek|bacon/i, t: 'spek' },
    { r: /kip(filet|dij|borst|stukje|schnitzel)?/i, t: 'kip' },
    { r: /worst|rookworst|braadworst/i, t: 'worst' },
    { r: /zalm/i, t: 'zalm' },
    { r: /garnaal|scampi/i, t: 'garnalen' },
    { r: /kabeljauw|tilapia|pangasius|vis\b/i, t: 'vis' },
    { r: /ei(eren)?/i, t: 'ei' },
    { r: /kaas|gouda|mozzarella|brie/i, t: 'kaas' },
    { r: /geraspte?\s*kaas/i, t: 'geraspte kaas' },
    { r: /melk/i, t: 'melk' },
    { r: /boter/i, t: 'boter' },
    { r: /room|kookroom/i, t: 'room' },
    { r: /yoghurt|kwark/i, t: 'yoghurt' },
    { r: /ui(en)?|sjalot/i, t: 'ui' },
    { r: /knoflook/i, t: 'knoflook' },
    { r: /tomaat|tomatensaus/i, t: 'tomaat' },
    { r: /paprika/i, t: 'paprika' },
    { r: /aardappel|krieltje/i, t: 'aardappel' },
    { r: /spinazie/i, t: 'spinazie' },
    { r: /broccoli/i, t: 'broccoli' },
    { r: /wortel/i, t: 'wortel' },
    { r: /prei/i, t: 'prei' },
    { r: /champignon/i, t: 'champignon' },
    { r: /pasta|spaghetti|penne|fusilli/i, t: 'pasta' },
    { r: /rijst|basmati/i, t: 'rijst' },
    { r: /brood|baguette|tortilla/i, t: 'brood' },
    { r: /kidney.?bonen|bruine.?bonen|kikker.?erwten/i, t: 'bonen' },
    { r: /split.?erwten/i, t: 'erwten' },
    { r: /mais/i, t: 'mais' },
    { r: /tomatensaus|pastasaus/i, t: 'tomatensaus' },
    { r: /ketjap|sojasaus/i, t: 'ketjap' },
    { r: /bouillon/i, t: 'bouillon' },
  ];
  for (const { r, t } of mapping) {
    if (r.test(tekst)) trefwoorden.add(t);
  }
  return Array.from(trefwoorden);
}

// ═══════════════════════════════════════════════
// ALBERT HEIJN — mobile API (werkt al)
// ═══════════════════════════════════════════════

const AH_AUTH_URL   = 'https://api.ah.nl/mobile-auth/v1/auth/token/anonymous';
const AH_SEARCH_URL = 'https://api.ah.nl/mobile-services/product/search/v2';
const AH_HEADERS    = {
  'User-Agent':      'Appie/8.22.3 (nl.ahold.albert.heijn; build:2977; iOS 16.0)',
  'x-application':   'AHWEBSHOP',
  'Accept':          'application/json',
  'Accept-Language': 'nl-NL',
};

let ahToken = null;
let ahTokenExpiry = 0;

async function getAHToken() {
  if (ahToken && Date.now() < ahTokenExpiry) return ahToken;
  const res = await axios.post(
    AH_AUTH_URL,
    { clientId: 'appie' },
    { headers: { 'Content-Type': 'application/json', ...AH_HEADERS }, timeout: 10000 }
  );
  ahToken = res.data.access_token;
  ahTokenExpiry = Date.now() + (res.data.expires_in - 300) * 1000;
  return ahToken;
}

async function fetchAH() {
  console.log('🛒 Albert Heijn: aanbiedingen ophalen...');
  const deals = [];
  const gezien = new Set();

  try {
    const token = await getAHToken();
    const headers = { ...AH_HEADERS, Authorization: `Bearer ${token}` };

    const zoektermen = [
      'vlees', 'kip', 'vis', 'kaas', 'melk', 'pasta',
      'rijst', 'groente', 'fruit', 'brood', 'snack', 'drank', 'zuivel'
    ];

    for (const term of zoektermen) {
      try {
        const res = await axios.get(AH_SEARCH_URL, {
          headers,
          params: { query: term, sortOn: 'RELEVANCE', size: 30, page: 0 },
          timeout: 10000,
        });

        const producten = res.data?.products || res.data?.cards || [];

        for (const item of producten) {
          const p = item.product || item;
          if (!p?.title || gezien.has(p.webshopId)) continue;

          const heeftBonus = p.discount || p.bonusPrice || p.isBonus
            || p.isBonusPeriod || (p.priceLabel?.was && p.priceLabel?.now);
          if (!heeftBonus) continue;

          gezien.add(p.webshopId);

          // priceLabel.now = normale prijs, priceLabel.was = bonusprijs
          const prijsWas = parseFloat(p.priceLabel?.now?.amount || p.currentPrice || 0);
          let   prijsNu  = parseFloat(p.priceLabel?.was?.amount || p.bonusPrice?.amount || 0);

          const kortingTekst = (p.discountDescription || p.shield?.text || '').toUpperCase();

          if (prijsNu === 0 && prijsWas > 0) {
            const pctMatch = kortingTekst.match(/(\d+)%/);
            if (pctMatch) {
              const pct = parseInt(pctMatch[1]);
              if (pct > 0 && pct < 100) prijsNu = Math.round((prijsWas * (1 - pct / 100)) * 100) / 100;
            } else if (/HALVE PRIJS|1\+1/.test(kortingTekst)) {
              prijsNu = Math.round(prijsWas * 0.75 * 100) / 100;
            } else if (/2\+1|3 VOOR 2/.test(kortingTekst)) {
              prijsNu = Math.round(prijsWas * 0.67 * 100) / 100;
            } else {
              prijsNu = prijsWas;
            }
          }

          if (prijsNu <= 0) continue;

          deals.push(normalizeProduct({
            id:           p.webshopId || p.id,
            naam:         p.title,
            beschrijving: p.salesUnitSize || p.unitSize || '',
            categorie:    p.mainCategory || p.category || 'AH Bonus',
            prijsNu, prijsWas,
            korting:      kortingTekst,
            afbeelding:   (p.images || []).sort((a,b)=>(b.width||0)-(a.width||0)).find(i=>i.width>=200)?.url || '',
            geldigTot:    p.bonusEndDate || '',
            merk:         p.brand?.description || '',
          }, 'ah'));
        }

        await new Promise(r => setTimeout(r, 200));
      } catch (e) {
        if (e.response?.status === 401) ahToken = null;
      }
    }

    console.log(`  ✅ AH: ${deals.length} aanbiedingen`);
    return deals;

  } catch (err) {
    console.error(`  ❌ AH mislukt: ${err.message}`);
    return [];
  }
}

// ═══════════════════════════════════════════════
// PUPPETEER HELPER — gedeelde browser instantie
// ═══════════════════════════════════════════════

let browserInstantie = null;

async function getBrowser() {
  if (!puppeteer) throw new Error('Puppeteer niet geïnstalleerd. Voer uit: npm install puppeteer');
  if (!browserInstantie || !browserInstantie.connected) {
    browserInstantie = await puppeteer.launch({
      headless: 'new',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--window-size=1280,800',
      ],
    });
  }
  return browserInstantie;
}

async function sluitBrowser() {
  if (browserInstantie) {
    await browserInstantie.close();
    browserInstantie = null;
  }
}

// Open een pagina, wacht tot producten geladen zijn, geef de HTML terug
async function haalPaginaOp(url, wachtSelector, timeout = 20000) {
  const browser = await getBrowser();
  const pagina  = await browser.newPage();

  await pagina.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
  await pagina.setViewport({ width: 1280, height: 800 });

  // Blokkeer afbeeldingen/fonts om sneller te laden
  await pagina.setRequestInterception(true);
  pagina.on('request', req => {
    const type = req.resourceType();
    if (['image', 'font', 'media'].includes(type)) req.abort();
    else req.continue();
  });

  try {
    await pagina.goto(url, { waitUntil: 'networkidle2', timeout });
    if (wachtSelector) {
      await pagina.waitForSelector(wachtSelector, { timeout: 15000 }).catch(() => {});
    }
    // Extra wacht voor lazy-load
    await new Promise(r => setTimeout(r, 2000));
    const html = await pagina.content();
    return html;
  } finally {
    await pagina.close();
  }
}

// ═══════════════════════════════════════════════
// JUMBO — Puppeteer
// ═══════════════════════════════════════════════

async function fetchJumbo() {
  console.log('🟡 Jumbo: aanbiedingen ophalen via Puppeteer...');
  const deals = [];

  try {
    const browser = await getBrowser();
    const pagina  = await browser.newPage();

    await pagina.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    await pagina.setViewport({ width: 1280, height: 800 });
    await pagina.setRequestInterception(true);
    pagina.on('request', req => {
      if (['image','font','media'].includes(req.resourceType())) req.abort();
      else req.continue();
    });

    await pagina.goto('https://www.jumbo.com/aanbiedingen/', { waitUntil: 'networkidle2', timeout: 30000 });
    await pagina.waitForSelector('[class*="ProductCard"], [data-testid*="product"]', { timeout: 15000 }).catch(() => {});
    await new Promise(r => setTimeout(r, 2000));

    // Scrape producten direct via browser context
    const producten = await pagina.evaluate(() => {
      const cards = document.querySelectorAll('[class*="ProductCard"], [data-testid*="product-card"], article[class*="product"]');
      return Array.from(cards).map(card => {
        const naam    = card.querySelector('[class*="title"], [class*="name"], h2, h3')?.textContent?.trim() || '';
        const prijsEl = card.querySelector('[class*="current-price"], [class*="sale"], [class*="promo"], [data-testid*="price"]');
        const wasEl   = card.querySelector('[class*="was-price"], [class*="strike"], [class*="original"], del');
        const img     = card.querySelector('img')?.src || '';
        const korting = card.querySelector('[class*="badge"], [class*="sticker"], [class*="label"]')?.textContent?.trim() || '';

        return { naam, prijs: prijsEl?.textContent?.trim() || '', was: wasEl?.textContent?.trim() || '', img, korting };
      }).filter(p => p.naam && p.prijs);
    });

    await pagina.close();

    for (const p of producten) {
      const prijsNu  = parseNLPrice(p.prijs);
      const prijsWas = parseNLPrice(p.was);
      if (!p.naam || prijsNu <= 0) continue;
      deals.push(normalizeProduct({
        naam: p.naam, prijsNu, prijsWas,
        korting: p.korting, afbeelding: p.img,
        categorie: 'Jumbo Aanbieding',
      }, 'jumbo'));
    }

    console.log(`  ✅ Jumbo: ${deals.length} aanbiedingen`);
    return deals;

  } catch (err) {
    console.error(`  ❌ Jumbo mislukt: ${err.message}`);
    return [];
  }
}

// ═══════════════════════════════════════════════
// LIDL — Puppeteer
// ═══════════════════════════════════════════════

async function fetchLidl() {
  console.log('🔵 Lidl: aanbiedingen ophalen via Puppeteer...');
  const deals = [];

  try {
    const browser = await getBrowser();
    const pagina  = await browser.newPage();

    await pagina.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    await pagina.setViewport({ width: 1280, height: 800 });
    await pagina.setRequestInterception(true);
    pagina.on('request', req => {
      if (['image','font','media'].includes(req.resourceType())) req.abort();
      else req.continue();
    });

    await pagina.goto('https://www.lidl.nl/c/aanbiedingen/s10006388', { waitUntil: 'networkidle2', timeout: 30000 });
    await pagina.waitForSelector('[class*="offer"], [class*="product"], [class*="tile"]', { timeout: 15000 }).catch(() => {});
    await new Promise(r => setTimeout(r, 2000));

    const producten = await pagina.evaluate(() => {
      const cards = document.querySelectorAll('[class*="offer-card"], [class*="OfferCard"], [class*="product-tile"], [class*="ProductTile"]');
      return Array.from(cards).map(card => {
        const naam    = card.querySelector('[class*="title"], [class*="headline"], h2, h3')?.textContent?.trim() || '';
        const prijsEl = card.querySelector('[class*="price__main"], [class*="price-main"], [class*="current"]');
        const wasEl   = card.querySelector('[class*="price__struck"], [class*="strike"], [class*="was"], del');
        const korting = card.querySelector('[class*="badge"], [class*="discount"]')?.textContent?.trim() || '';
        const img     = card.querySelector('img')?.src || '';
        return { naam, prijs: prijsEl?.textContent?.trim() || '', was: wasEl?.textContent?.trim() || '', korting, img };
      }).filter(p => p.naam && p.prijs);
    });

    await pagina.close();

    for (const p of producten) {
      const prijsNu  = parseNLPrice(p.prijs);
      const prijsWas = parseNLPrice(p.was);
      if (!p.naam || prijsNu <= 0) continue;
      deals.push(normalizeProduct({
        naam: p.naam, prijsNu, prijsWas,
        korting: p.korting, afbeelding: p.img,
        categorie: 'Lidl Aanbieding',
      }, 'lidl'));
    }

    console.log(`  ✅ Lidl: ${deals.length} aanbiedingen`);
    return deals;

  } catch (err) {
    console.error(`  ❌ Lidl mislukt: ${err.message}`);
    return [];
  }
}

// ═══════════════════════════════════════════════
// ALDI — Puppeteer
// ═══════════════════════════════════════════════

async function fetchAldi() {
  console.log('🔵 Aldi: aanbiedingen ophalen via Puppeteer...');
  const deals = [];

  try {
    const browser = await getBrowser();
    const pagina  = await browser.newPage();

    await pagina.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    await pagina.setViewport({ width: 1280, height: 800 });
    await pagina.setRequestInterception(true);
    pagina.on('request', req => {
      if (['image','font','media'].includes(req.resourceType())) req.abort();
      else req.continue();
    });

    await pagina.goto('https://www.aldi.nl/aanbiedingen.html', { waitUntil: 'networkidle2', timeout: 30000 });
    await pagina.waitForSelector('[class*="tile"], [class*="product"], [class*="offer"]', { timeout: 15000 }).catch(() => {});
    await new Promise(r => setTimeout(r, 2000));

    const producten = await pagina.evaluate(() => {
      const cards = document.querySelectorAll('[class*="mod-article-tile"], [class*="product-tile"], [class*="offer-tile"]');
      return Array.from(cards).map(card => {
        const naam    = card.querySelector('[class*="headline"], [class*="title"], h2, h3')?.textContent?.trim() || '';
        const prijsEl = card.querySelector('[class*="price__main"], [class*="current-price"], [class*="price-main"]');
        const wasEl   = card.querySelector('[class*="price__struck"], [class*="strike"], del');
        const korting = card.querySelector('[class*="badge"], [class*="discount-badge"]')?.textContent?.trim() || '';
        const img     = card.querySelector('img')?.src || '';
        return { naam, prijs: prijsEl?.textContent?.trim() || '', was: wasEl?.textContent?.trim() || '', korting, img };
      }).filter(p => p.naam && p.prijs);
    });

    await pagina.close();

    for (const p of producten) {
      const prijsNu  = parseNLPrice(p.prijs);
      const prijsWas = parseNLPrice(p.was);
      if (!p.naam || prijsNu <= 0) continue;
      deals.push(normalizeProduct({
        naam: p.naam, prijsNu, prijsWas,
        korting: p.korting, afbeelding: p.img,
        categorie: 'Aldi Aanbieding',
      }, 'aldi'));
    }

    console.log(`  ✅ Aldi: ${deals.length} aanbiedingen`);
    return deals;

  } catch (err) {
    console.error(`  ❌ Aldi mislukt: ${err.message}`);
    return [];
  }
}

// ═══════════════════════════════════════════════
// PLUS — Puppeteer
// ═══════════════════════════════════════════════

async function fetchPlus() {
  console.log('🟢 Plus: aanbiedingen ophalen via Puppeteer...');
  const deals = [];

  try {
    const browser = await getBrowser();
    const pagina  = await browser.newPage();

    await pagina.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    await pagina.setViewport({ width: 1280, height: 800 });
    await pagina.setRequestInterception(true);
    pagina.on('request', req => {
      if (['image','font','media'].includes(req.resourceType())) req.abort();
      else req.continue();
    });

    await pagina.goto('https://www.plus.nl/aanbiedingen', { waitUntil: 'networkidle2', timeout: 30000 });
    await pagina.waitForSelector('[class*="product"], [class*="offer"], [class*="card"]', { timeout: 15000 }).catch(() => {});
    await new Promise(r => setTimeout(r, 2000));

    const producten = await pagina.evaluate(() => {
      const cards = document.querySelectorAll('[class*="product-card"], [class*="offer-card"], [class*="ProductCard"]');
      return Array.from(cards).map(card => {
        const naam    = card.querySelector('[class*="title"], [class*="name"], h2, h3')?.textContent?.trim() || '';
        const prijsEl = card.querySelector('[class*="sale-price"], [class*="promo"], [class*="current"]');
        const wasEl   = card.querySelector('[class*="was"], [class*="strike"], [class*="original"], del');
        const korting = card.querySelector('[class*="badge"], [class*="label"]')?.textContent?.trim() || '';
        const img     = card.querySelector('img')?.src || '';
        return { naam, prijs: prijsEl?.textContent?.trim() || '', was: wasEl?.textContent?.trim() || '', korting, img };
      }).filter(p => p.naam && p.prijs);
    });

    await pagina.close();

    for (const p of producten) {
      const prijsNu  = parseNLPrice(p.prijs);
      const prijsWas = parseNLPrice(p.was);
      if (!p.naam || prijsNu <= 0) continue;
      deals.push(normalizeProduct({
        naam: p.naam, prijsNu, prijsWas,
        korting: p.korting, afbeelding: p.img,
        categorie: 'Plus Aanbieding',
      }, 'plus'));
    }

    console.log(`  ✅ Plus: ${deals.length} aanbiedingen`);
    return deals;

  } catch (err) {
    console.error(`  ❌ Plus mislukt: ${err.message}`);
    return [];
  }
}

// ═══════════════════════════════════════════════
// DIRK — Puppeteer
// ═══════════════════════════════════════════════

async function fetchDirk() {
  console.log('🔴 Dirk: aanbiedingen ophalen via Puppeteer...');
  const deals = [];

  try {
    const browser = await getBrowser();
    const pagina  = await browser.newPage();

    await pagina.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    await pagina.setViewport({ width: 1280, height: 800 });
    await pagina.setRequestInterception(true);
    pagina.on('request', req => {
      if (['image','font','media'].includes(req.resourceType())) req.abort();
      else req.continue();
    });

    await pagina.goto('https://www.dirk.nl/aanbiedingen', { waitUntil: 'networkidle2', timeout: 30000 });
    await pagina.waitForSelector('[class*="product"], [class*="folder"], [class*="offer"]', { timeout: 15000 }).catch(() => {});
    await new Promise(r => setTimeout(r, 2000));

    const producten = await pagina.evaluate(() => {
      const cards = document.querySelectorAll('[class*="product-card"], [class*="folder-product"], [class*="ProductCard"]');
      return Array.from(cards).map(card => {
        const naam    = card.querySelector('[class*="name"], [class*="title"], h2, h3')?.textContent?.trim() || '';
        const prijsEl = card.querySelector('[class*="current"], [class*="sale"], [class*="promo"]');
        const wasEl   = card.querySelector('[class*="was"], [class*="old"], [class*="strike"], del');
        const korting = card.querySelector('[class*="badge"], [class*="discount"]')?.textContent?.trim() || '';
        const img     = card.querySelector('img')?.src || '';
        return { naam, prijs: prijsEl?.textContent?.trim() || '', was: wasEl?.textContent?.trim() || '', korting, img };
      }).filter(p => p.naam && p.prijs);
    });

    await pagina.close();

    for (const p of producten) {
      const prijsNu  = parseNLPrice(p.prijs);
      const prijsWas = parseNLPrice(p.was);
      if (!p.naam || prijsNu <= 0) continue;
      deals.push(normalizeProduct({
        naam: p.naam, prijsNu, prijsWas,
        korting: p.korting, afbeelding: p.img,
        categorie: 'Dirk Aanbieding',
      }, 'dirk'));
    }

    console.log(`  ✅ Dirk: ${deals.length} aanbiedingen`);
    return deals;

  } catch (err) {
    console.error(`  ❌ Dirk mislukt: ${err.message}`);
    return [];
  }
}

// Browser afsluiten na alle scrapers
async function cleanup() {
  await sluitBrowser();
}

module.exports = { fetchAH, fetchJumbo, fetchLidl, fetchAldi, fetchPlus, fetchDirk, cleanup };