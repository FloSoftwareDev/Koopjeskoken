/**
 * Debug script — voer uit met: node src/debug.js
 * Laat zien welke HTML elementen Puppeteer ziet op de aanbiedingspagina
 */

const puppeteer = require('puppeteer');

async function debug(url, naam) {
  console.log(`\n══════════════════════════════`);
  console.log(`🔍 ${naam}: ${url}`);
  console.log(`══════════════════════════════`);

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });

  const pagina = await browser.newPage();
  await pagina.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
  await pagina.setViewport({ width: 1280, height: 800 });

  try {
    await pagina.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 3000));

    const info = await pagina.evaluate(() => {
      // Verzamel alle unieke klassen die op de pagina voorkomen
      const klassen = new Set();
      document.querySelectorAll('[class]').forEach(el => {
        const cn = typeof el.className === 'string' ? el.className : (el.className.baseVal || '');
        cn.split(/\s+/).forEach(c => {
          if (c.length > 3 && c.length < 50) klassen.add(c);
        });
      });

      // Zoek elementen die op product cards lijken
      const mogelijkeCards = [
        'article', 'li[class]', '[data-testid]',
        '[class*="product"]', '[class*="Product"]',
        '[class*="card"]', '[class*="Card"]',
        '[class*="offer"]', '[class*="Offer"]',
        '[class*="tile"]', '[class*="Tile"]',
        '[class*="item"]', '[class*="Item"]',
      ];

      const gevonden = {};
      mogelijkeCards.forEach(sel => {
        const els = document.querySelectorAll(sel);
        if (els.length > 0) {
          gevonden[sel] = {
            aantal: els.length,
            eersteKlasse: els[0]?.className?.slice(0, 80) || '',
            eersteText: els[0]?.textContent?.trim().slice(0, 60) || '',
          };
        }
      });

      // Zoek prijselementen
      const prijsEls = document.querySelectorAll('[class*="price"], [class*="Price"], [class*="prijs"]');
      const prijsSample = Array.from(prijsEls).slice(0, 5).map(el => ({
        klasse: el.className.slice(0, 60),
        tekst: el.textContent.trim().slice(0, 30),
      }));

      return {
        titel: document.title,
        url: window.location.href,
        aantalElementen: document.querySelectorAll('*').length,
        gevondenCards: gevonden,
        prijsSamples: prijsSample,
        klassenSample: Array.from(klassen).filter(k =>
          /product|card|offer|tile|price|prijs|item|deal/i.test(k)
        ).slice(0, 30),
      };
    });

    console.log(`Titel:     ${info.titel}`);
    console.log(`URL:       ${info.url}`);
    console.log(`Elementen: ${info.aantalElementen}`);
    console.log(`\nRelevante klassen:`);
    info.klassenSample.forEach(k => console.log(`  - ${k}`));
    console.log(`\nGevonden card-selectors:`);
    Object.entries(info.gevondenCards).forEach(([sel, data]) => {
      console.log(`  ${sel}: ${data.aantal}x`);
      console.log(`    Klasse: ${data.eersteKlasse}`);
      console.log(`    Tekst:  ${data.eersteText}`);
    });
    console.log(`\nPrijs elementen:`);
    info.prijsSamples.forEach(p => {
      console.log(`  Klasse: ${p.klasse}`);
      console.log(`  Tekst:  ${p.tekst}`);
    });

  } catch (err) {
    console.error(`❌ Fout: ${err.message}`);
  } finally {
    await pagina.close();
    await browser.close();
  }
}

async function main() {
  // Test één supermarkt tegelijk — uncomment wat je wil testen
  await debug('https://www.jumbo.com/aanbiedingen/', 'Jumbo');
  // await debug('https://www.lidl.nl/c/aanbiedingen/s10006388', 'Lidl');
  // await debug('https://www.aldi.nl/aanbiedingen.html', 'Aldi');
  // await debug('https://www.plus.nl/aanbiedingen', 'Plus');
  // await debug('https://www.dirk.nl/aanbiedingen', 'Dirk');
}

main().catch(console.error);