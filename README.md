# AUTOKASSA – nový web (Autozáložňa Košice)

Moderný, minimalistický a plne responzívny web pre **Auto Kassa s. r. o.** (autozáložňa, Košice).
Postavený ako statické HTML/CSS/JS – bez build kroku, rýchle načítanie, výborné Core Web Vitals.

## 📁 Štruktúra

```
/
├── index.html                     # hlavná stránka (hero, kroky, výhody, cenník, podmienky, kalkulačka+formulár, FAQ, kontakt)
├── autozalozna-presov.html        # lokálna SEO landing page (Prešov / Prešovský kraj)
├── obchodne-podmienky.html        # VOP (vzor – doplniť právnikom)
├── ochrana-osobnych-udajov.html   # GDPR (vzor – doplniť právnikom)
├── 404.html
├── robots.txt
├── sitemap.xml
├── site.webmanifest
└── assets/
    ├── css/styles.css
    ├── js/main.js                 # menu, scroll animácie, kalkulačka, odoslanie formulára
    └── img/                       # favicon.svg, logo.svg, og-image.svg
```

## ⚡ Spustenie lokálne

```bash
python3 -m http.server 8000
# otvor http://localhost:8000
```

## 🚀 Nasadenie
Akýkoľvek statický hosting: **Netlify, Vercel, GitHub Pages, Cloudflare Pages** alebo bežný webhosting (FTP).
Stačí nahrať obsah priečinka. Odporúčame HTTPS a presmerovanie `autokassa.sk → www.autokassa.sk` (alebo naopak – podľa kánonickej domény v `<link rel="canonical">`).

---

## 🔎 SEO – čo je hotové
- Unikátne `<title>` a `meta description` pre každú stránku, `canonical`, `lang="sk"`, `robots`.
- Sémantické HTML5, jeden `<h1>` na stránku, logická hierarchia nadpisov.
- **Štruktúrované dáta (Schema.org / JSON-LD):** `FinancialService`/`AutomotiveBusiness` (NAP, otváracie hodiny, areaServed), `FAQPage`, `BreadcrumbList`, `OfferCatalog`.
- `sitemap.xml` + `robots.txt`.
- Rýchlosť: žiadny framework, odložené načítanie fontu a JS (`defer`), `loading="lazy"` na mape, SVG grafika.
- Prístupnosť: skip-link, `aria` atribúty, focus štýly, kontrast, alt/title.

## 📍 Lokálne SEO (GEO – geografické)
- Konzistentné **NAP** (Názov / Adresa / Telefón) v pätičke, kontaktoch aj v JSON-LD.
- `geo.region`, `geo.placename`, `geo.position`, `ICBM` meta tagy.
- `areaServed`: Košický a Prešovský kraj.
- Samostatná landing page pre **Prešov** (`autozalozna-presov.html`) – vzor pre ďalšie mestá (Michalovce, Spišská Nová Ves, Poprad, Humenné…). Skopírujte a upravte mesto + texty + `geo` meta.
- **Odporúčame:** založiť/aktualizovať **Google Business Profile** (rovnaké NAP), pridať fotky garáže a prevádzky, zbierať recenzie.

## 🤖 GEO (Generative Engine Optimization – AI vyhľadávače)
- Jasné definičné vety („Autozáložňa AUTOKASSA vám poskytne peniaze pod zástavu vozidla…") – AI ich ľahko cituje.
- Rozsiahla **FAQ sekcia** + `FAQPage` schema = časté zdroje pre AI odpovede.
- Faktická tabuľka sadzieb (% podľa km) – štruktúrovaný, citovateľný obsah.
- `robots.txt` explicitne **povoľuje AI crawlerov** (GPTBot, OAI-SearchBot, PerplexityBot, Google-Extended, Applebot-Extended).

## 📣 Príprava na Facebook a Instagram (hlavné kanály leadov)
- **Open Graph** + **Twitter Card** meta tagy → pekný náhľad pri zdieľaní v FB/IG/Messenger.
- Odkazy na FB a IG v hlavičke kontaktu aj v pätičke (sociálne ikony).
- CTA „Vyžiadať ponuku" a klikateľné telefónne číslo na každej obrazovke (vrátane plávajúceho tlačidla na mobile) – ideálne pre návštevnosť z reklám.
- Pripravené miesta pre **Facebook Pixel** a **GA4** (pozri nižšie) na meranie konverzií z kampaní.

---

## ✅ ČO DOPLNIŤ pred spustením

1. **OG obrázok (dôležité pre FB/IG):** vyexportovať `assets/img/og-image.svg` do **PNG 1200×630** ako `assets/img/og-image.png`.
   - online: napr. cloudconvert.com, alebo lokálne:
     ```bash
     rsvg-convert -w 1200 -h 630 assets/img/og-image.svg -o assets/img/og-image.png
     # alebo: inkscape assets/img/og-image.svg -w 1200 -h 630 -o assets/img/og-image.png
     ```
   (FB/IG/Twitter ignorujú SVG, preto je potrebný raster.)

2. **Formulár – odosielanie:** v `index.html` má `<form id="quoteForm">` atribút `action="https://formspree.io/f/your-id"`.
   - nahraďte reálnym endpointom (Formspree, Web3Forms, Netlify Forms, alebo vlastný backend);
   - kým nie je nastavený, formulár zobrazí len potvrdenie (lead sa neodošle).
   - Pre **Netlify Forms** pridajte na `<form>`: `netlify` a `name="ponuka"`.

3. **Facebook Pixel / Google Analytics 4:** odkomentovať a doplniť ID v `<head>` `index.html`
   (GA4 blok) a aktivovať konverziu v `assets/js/main.js` vo funkcii `showSuccess()`
   (`fbq('track','Lead')`, `gtag('event','generate_lead')`).

4. **Sociálne siete:** v kóde sú placeholdery
   `https://www.facebook.com/autokassa` a `https://www.instagram.com/autokassa` – nahraďte reálnymi profilmi.

5. **Otváracie hodiny:** v JSON-LD (`openingHoursSpecification`) je predvolené Po–Pia 9:00–17:00 – upravte podľa reality.

6. **GPS súradnice:** `geo.position` / `GeoCoordinates` sú približné pre Košice – spresnite podľa presnej polohy prevádzky.

7. **Právne stránky:** `obchodne-podmienky.html` a `ochrana-osobnych-udajov.html` sú vzory – nechať skontrolovať právnikom, doplniť poplatky, úroky a lehoty.

8. **Doména:** skontrolovať, či kánonická verzia (`www.` vs bez `www.`) sedí s reálnym nastavením hostingu.
