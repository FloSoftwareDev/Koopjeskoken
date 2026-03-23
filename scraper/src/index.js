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

const DATA_DIR = path.join(__dirname, '..', 'data');
if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });

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
  console.log(`🍽️  KoopjesKoken Scraper — ${new Date().toLocaleString('nl-NL')}`);
  console.log('══════════════════════════════════════════════\n');

  const actief = smFilter
    ? scrapers.filter(s => s.id === smFilter)
    : scrapers;

  if (actief.length === 0) {
    console.error(`❌ Onbekende supermarkt: "${smFilter}"`);
    process.exit(1);
  }

  const alleDeals = [];
  const stats     = {};
  const fouten    = [];

  // AH eerst (API), daarna Puppeteer scrapers één voor één
  for (const scraper of actief) {
    try {
      const deals = await scraper.fn();
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
    d.naam && d.naam.length > 2 && d.prijsNu > 0 && d.prijsNu < 500
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
  console.log('📊 Resultaten:');
  for (const [sm, count] of Object.entries(stats)) {
    console.log(`   ${count > 0 ? '✅' : '⚠️ '} ${sm.padEnd(8)}: ${count} aanbiedingen`);
  }
  if (fouten.length > 0) {
    console.log('\n⚠️  Fouten:');
    fouten.forEach(f => console.log(`   ❌ ${f.supermarkt}: ${f.fout}`));
  }
  console.log(`\n🎯 Totaal: ${geldig.length} deals opgeslagen`);
  console.log(`💾 ${DATA_DIR}`);
  console.log('══════════════════════════════════════════════\n');

  if (testMode) {
    console.log('🧪 Eerste 3 deals:');
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
  console.log(`   💾 Opgeslagen: ${bestand} (${kb} KB)`);
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