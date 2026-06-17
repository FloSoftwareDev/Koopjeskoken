/**
 * KoopjesKoken Scraper — index.js
 * Gebruik:
 *   node src/index.js           → alle supermarkten
 *   node src/index.js --test    → alle supermarkten + eerste 3 tonen
 *   node src/index.js --sm ah   → alleen AH
 *   node src/index.js --watch   → elke dag om 07:00 en 13:00
 */

const fs   = require('fs');
const path = require('path');
const cron = require('node-cron');

const {
  fetchAH, fetchJumbo, fetchLidl,
  fetchAldi, fetchPlus, fetchDirk, cleanup,
} = require('./scrapers');

// Two levels up from src/ lands at the web root, so the web server can serve
// deals-frontend.json directly at ./data/deals-frontend.json
const DATA_DIR = path.join(__dirname, '..', '..', 'data');
if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });

// ── Non-food filter ─────────────────────────────────────────────────────────
// Sluit verzorging, schoonmaak, hygiëne, huishouddier en huishouddiverse uit.
// Werkt op productnaam — toegevoegde regex'en mogen geen voedingsproducten
// raken (test naar false-positives bij elke uitbreiding).

const NON_FOOD_PATTERNS = [
  // Persoonlijke verzorging
  /\b(deodorant|deospray|antitranspirant)\b/i,
  /\b(tandpasta|tandenborstel|mondwater|flossdraad|gebitsreiniger)\b/i,
  /\b(shampoo|conditioner|haarlak|haargel|haarmasker|haarverf|haaract|haarsp)\b/i,
  /\b(douchegel|douchescha?u?im|douchecre|bodylotion|body\s*wash|body\s*lotion|handzeep|badschuim|badzout)\b/i,
  /\b(scheermes|scheergel|scheerschuim|scheercre|scheerkop|aftershave)\b/i,
  /\b(parfum|eau\s*de\s*(toilette|parfum|cologne)|geurspr)\b/i,
  /\b(mascara|lipstick|lippenstift|foundation|nagellak|oogschaduw|make[-\s]?up|wimper|kohl|concealer|primer)\b/i,
  /\b(zonnebrand|zonneolie|after[-\s]?sun)\b/i,
  /\b(handcre|gezichtscre|dagcre|nachtcre|oogcre|voetencre)\b/i,

  // Schoonmaak
  /\b(wasmiddel|wasverzachter|wasparfum|wasstrips|wastablett|wascaps|wasgel|wasvloeibaar|wastrommel)\b/i,
  /\b(afwasmiddel|vaatwas(tablett|caps|middel|gel|tabs)?|glansspoel|machinereiniger)\b/i,
  /\b(allesreiniger|schoonmaak|vlekverwijder|ontkalk|bleek|chloor|ammoniak|kalk\s*remover)\b/i,
  /\b(toiletreiniger|badreiniger|keukenreiniger|wc[-\s]?reiniger|glasreiniger|vloerreiniger|tegelreiniger)\b/i,
  /\b(luchtverfris|wcblok|geurkaars)\b/i,
  /\b(vuilniszak|afvalzak|vriezerzak|diepvrieszak)\b/i,
  /\b(microvezeldoek|sponsj|schuursponsj|spons|werkdoek)\b/i,

  // Hygiëne / medisch (niet-eet)
  /\b(maandverband|tampon|inlegkruis|incontinent|panty\s*liner)\b/i,
  /\b(condoom|condooms|glijmiddel)\b/i,
  /\b(pleister|verband|gaasje|wattenstaaf|wattenschijfje|kompres)\b/i,

  // Baby (non-food)
  /\b(luier|luiers|billendoekje|babydoekje|babylotion|babyolie|babyzeep|kinderzeep)\b/i,

  // Dier
  /\b(katten?(voer|brok|bakvulling|grit|snack)|honden?(voer|brok|snack|sticks|kluif)|dieren\s*voer|kibble|kattenmelk)\b/i,
  /\b(vogelvoer|vissenvoer|aquariumvoer|knaagdier(voer|en))\b/i,

  // Papier huishouden
  /\b(toiletpapier|wc[-\s]?papier|keukenpapier|keukenrol|zakdoekjes|tissues|servet)\b/i,

  // Overig huishouden
  /\b(batterij(en)?|gloeilamp|ledlamp|halogeenlamp|wegwerp\s*(maagzuur)?)\b/i,
  /\b(tape|plakband|lijm(kit|stift)?|nieten|nietmachine|paperclip)\b/i,
];

const NON_FOOD_BRANDS = [
  // Schoonmaak
  'robijn','persil','ariel','dreft','vanish','calgon','glorix','wc eend','wc-eend',
  'cif','ajax','mr proper','mr. proper','glassex','andy','lenor','quickwash',
  // Verzorging
  'odorex','rexona','sanex','axe','old spice','gillette','wilkinson sword','veet',
  'andrelon','elvive','schwarzkopf','pantene','garnier','head & shoulders',
  // Hygiëne / baby
  'always','tena','carefree','pampers','libero','huggies',
  // Dier
  'felix','whiskas','sheba','pedigree','bonzo','frolic','pro plan','royal canin',
  'hill\'s','eukanuba','iams','dreamies','kitekat','cesar',
];

function isFood(naam) {
  if (NON_FOOD_PATTERNS.some(r => r.test(naam))) return false;
  const lower = naam.toLowerCase();
  if (NON_FOOD_BRANDS.some(b => lower.includes(b))) return false;
  return true;
}

async function withRetry(fn, attempts = 3, delayMs = 5000) {
  for (let i = 0; i < attempts; i++) {
    try {
      return await fn();
    } catch (err) {
      if (i === attempts - 1) throw err;
      console.warn(`  Poging ${i + 1} mislukt, opnieuw over ${delayMs / 1000}s: ${err.message}`);
      await new Promise(r => setTimeout(r, delayMs));
    }
  }
}

const args      = process.argv.slice(2);
const watchMode = args.includes('--watch');
const testMode  = args.includes('--test');
const smFilter  = args.includes('--sm') ? args[args.indexOf('--sm') + 1] : null;

const scrapers = [
  { id: 'ah',    naam: 'Albert Heijn', fn: fetchAH    },
  { id: 'jumbo', naam: 'Jumbo',        fn: fetchJumbo },
  { id: 'lidl',  naam: 'Lidl',         fn: fetchLidl  },
  { id: 'aldi',  naam: 'Aldi',         fn: fetchAldi  },
  { id: 'plus',  naam: 'Plus',         fn: fetchPlus  },
  { id: 'dirk',  naam: 'Dirk',         fn: fetchDirk  },
];

async function scrapeAll() {
  console.log('\n══════════════════════════════════════════════');
  console.log(`KoopjesKoken Scraper — ${new Date().toLocaleString('nl-NL')}`);
  console.log('══════════════════════════════════════════════\n');

  const actief = smFilter
    ? scrapers.filter(s => s.id === smFilter)
    : scrapers;

  if (actief.length === 0) {
    console.error(`Onbekende supermarkt: "${smFilter}"`);
    process.exit(1);
  }

  const alleDeals = [];
  const stats     = {};
  const fouten    = [];

  // AH eerst (API), daarna Puppeteer scrapers één voor één
  for (const scraper of actief) {
    try {
      const deals = await withRetry(() => scraper.fn());
      stats[scraper.id] = deals.length;
      alleDeals.push(...deals);
      slaOp(`deals-${scraper.id}.json`, deals);
    } catch (err) {
      fouten.push({ supermarkt: scraper.id, fout: err.message });
      stats[scraper.id] = 0;
    }
  }

  // Puppeteer browser afsluiten
  await cleanup();

  // Filter en sorteer
  const geldig = alleDeals.filter(d =>
    d.naam && d.naam.length > 2 && d.prijsNu > 0 && d.prijsNu < 500 && isFood(d.naam)
  );
  geldig.sort((a, b) => b.kortingPct - a.kortingPct);

  // Sla op
  slaOp('deals.json', {
    bijgewerkt: new Date().toISOString(),
    totaal: geldig.length,
    perSupermarkt: stats,
    deals: geldig,
  });

  const frontend = geldig.map(d => ({
    id: d.id, sm: d.supermarkt, naam: d.naam,
    beschr: d.beschrijving, cat: d.categorie,
    nu: d.prijsNu, was: d.prijsWas, pct: d.kortingPct,
    korting: d.korting, img: d.afbeelding,
    tot: d.geldigTot, tags: d.trefwoorden,
  }));
  slaOp('deals-frontend.json', frontend);

  // Samenvatting
  console.log('\n══════════════════════════════════════════════');
  console.log('Resultaten:');
  for (const [sm, count] of Object.entries(stats)) {
    console.log(`   ${count > 0 ? 'OK ' : '-- '} ${sm.padEnd(8)}: ${count} aanbiedingen`);
  }
  if (fouten.length > 0) {
    console.log('\nFouten:');
    fouten.forEach(f => console.log(`   ${f.supermarkt}: ${f.fout}`));
  }
  console.log(`\nTotaal: ${geldig.length} deals opgeslagen`);
  console.log(`${DATA_DIR}`);
  console.log('══════════════════════════════════════════════\n');

  if (testMode) {
    console.log('Eerste 3 deals:');
    geldig.slice(0, 3).forEach((d, i) => {
      console.log(`   ${i+1}. [${d.supermarkt}] ${d.naam} — €${d.prijsNu} (was €${d.prijsWas})`);
    });
  }

  return geldig;
}

function slaOp(bestand, data) {
  const p = path.join(DATA_DIR, bestand);
  fs.writeFileSync(p, JSON.stringify(data, null, 2), 'utf8');
  const kb = (fs.statSync(p).size / 1024).toFixed(1);
  console.log(`   Opgeslagen: ${bestand} (${kb} KB)`);
}

if (watchMode) {
  console.log('⏰ Watch mode — draait elke dag om 07:00 en 13:00\n');
  scrapeAll();
  cron.schedule('0 7,13 * * *', scrapeAll, { timezone: 'Europe/Amsterdam' });
} else {
  scrapeAll().catch(async err => {
    await cleanup();
    console.error('Fatale fout:', err);
    process.exit(1);
  });
}