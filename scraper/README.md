# KoopjesKoken Scraper

Haalt actuele supermarkt aanbiedingen op voor de KoopjesKoken website.

## Ondersteunde supermarkten

| Supermarkt    | Methode                          | Auth vereist |
|---------------|----------------------------------|--------------|
| Albert Heijn  | Officiële mobile API (api.ah.nl) | Nee          |
| Jumbo         | Mobile API + HTML fallback       | Nee          |
| Lidl          | Interne JSON API + HTML fallback | Nee          |
| Aldi          | HTML scraping                    | Nee          |
| Plus          | JSON API + HTML fallback         | Nee          |
| Dirk          | Content API + HTML fallback      | Nee          |

## Installatie

```bash
cd koopjeskoken-scraper
npm install
```

## Gebruik

```bash
# Eenmalig alle supermarkten scrapen
node src/index.js

# Alleen Albert Heijn
node src/index.js --sm ah

# Automatisch elke 6 uur bijwerken (cron)
node src/index.js --watch

# Test modus (toont eerste 3 results)
node src/index.js --test
```

## Output bestanden

Na het draaien staan de resultaten in de `data/` map:

```
data/
  deals.json           # Alle deals gecombineerd (met metadata)
  deals-frontend.json  # Compacte versie voor de frontend
  deals-ah.json        # Alleen Albert Heijn
  deals-jumbo.json     # Alleen Jumbo
  deals-lidl.json      # Alleen Lidl
  deals-aldi.json      # Alleen Aldi
  deals-plus.json      # Alleen Plus
  deals-dirk.json      # Alleen Dirk
```

### deals.json structuur

```json
{
  "bijgewerkt": "2025-03-15T08:00:00.000Z",
  "versie": "1.0",
  "totaalDeals": 284,
  "perSupermarkt": {
    "ah": 87,
    "jumbo": 63,
    "lidl": 45,
    "aldi": 38,
    "plus": 31,
    "dirk": 20
  },
  "deals": [
    {
      "id": "ah-wi193679-1234",
      "supermarkt": "ah",
      "naam": "Rundergehakt",
      "beschrijving": "500 gram",
      "categorie": "Vlees & Vis",
      "prijsNu": 2.99,
      "prijsWas": 4.99,
      "kortingPct": 40,
      "korting": "40% korting",
      "afbeelding": "https://static.ah.nl/...",
      "geldigTot": "2025-03-19",
      "geldigVanaf": "2025-03-13",
      "merk": "AH",
      "trefwoorden": ["gehakt", "rund"],
      "opgehaald": "2025-03-15T08:00:00.000Z"
    }
  ]
}
```

## Koppeling met de KoopjesKoken frontend

Zet `deals-frontend.json` op je webserver en laad het in de frontend:

```javascript
// In koopjeskoken.html — vervang dummy deals array met:
async function laadActueleDeals() {
  const res = await fetch('/data/deals-frontend.json');
  const deals = await res.json();
  // deals is nu een array van actuele aanbiedingen
  window.deals = deals;
  renderDealsStrip();
  renderDealsPage();
}
```

## Deployment (productie)

### Optie 1: Cron job op server
```bash
# Voeg toe aan crontab (crontab -e)
0 7,13 * * * cd /var/www/koopjeskoken-scraper && node src/index.js >> logs/scraper.log 2>&1
```

### Optie 2: PM2 met watch mode
```bash
npm install -g pm2
pm2 start src/index.js --name "koopjeskoken-scraper" -- --watch
pm2 save
pm2 startup
```

### Optie 3: Docker
```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY src/ ./src/
RUN mkdir data
CMD ["node", "src/index.js", "--watch"]
```

## Noten over de supermarkt APIs

### Albert Heijn
- Werkt betrouwbaar — AH heeft de API niet afgesloten voor anoniem gebruik
- Token verloopt na 1 uur, maar wordt automatisch vernieuwd
- Bonus-aanbiedingen worden elke maandag bijgewerkt

### Jumbo
- Jumbo heeft Akamai bot-protection op sommige endpoints
- Als de API geblokkeerd wordt, valt de scraper terug op HTML-scraping
- Aanbiedingen worden elke dinsdag bijgewerkt

### Lidl / Aldi
- Gebruiken server-side rendering — HTML scraping is stabiel
- Aanbiedingen gelden meestal van maandag tot zondag

### Plus / Dirk
- Kleine kans dat HTML structuur verandert bij redesign
- Controleer regelmatig of de selectors nog kloppen

## Troubleshooting

**"0 aanbiedingen" voor een supermarkt:**
- Controleer de website handmatig of er aanbiedingen zijn
- Mogelijk is de HTML-structuur gewijzigd — update de selectors in `src/scrapers/overig.js`

**Rate limiting (429 Too Many Requests):**
- De scraper probeert automatisch 3x met wachttijd
- Verlaag de scrape-frequentie als dit aanhoudt

**AH token errors:**
- De cachedToken wordt automatisch gereset
- Als het aanhoudt: verwijder `data/deals-ah.json` en probeer opnieuw
