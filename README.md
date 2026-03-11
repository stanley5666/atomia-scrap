# Atomia scraper (PHP)

PHP aplikácia, ktorá vytiahne ponuku nehnuteľností makléra z Atomia a uloží detailné dáta do JSON.
Výstup je pripravený na použitie na osobnom webe makléra.

## Požiadavky

- PHP 8.1+

## Použitie

```bash
php bin/scrape.php "https://www.atomia.sk/makler/12-ing-michaela-karafa#properties" --pretty
```

Výstup sa uloží do `output/properties.json`.

Pri spustení skript vypíše, čo načítal:
- počet nájdených inzerátov
- priebežné načítanie detailov
- finálny prehľad každej nehnuteľnosti (názov, URL, lokalita, cena, počet obrázkov)

### Prepínače

- `--output=...` vlastný JSON súbor (napr. `--output=data/nehnutelnosti.json`)
- `--limit=...` obmedzenie počtu inzerátov na test
- `--pretty` formátovaný JSON
- `--help` pomoc
- `--quiet` vypne priebežný výpis a prehľad

## Čo sa exportuje

- URL detailu
- titulok
- ID (ak sa dá odvodiť)
- typ a stav
- lokalita
- cena
- výmera
- popis
- obrázky
- tabuľkové/parametrické atribúty
- JSON-LD dáta z detailu


## HTML test (v prehliadači)

Ak chceš scraper testovať cez web rozhranie, použi súbor `web/test.php`.

Spustenie lokálne:

```bash
php -S 127.0.0.1:8080 -t .
```

Potom otvor:

- `http://127.0.0.1:8080/web/test.php`

V stránke zadáš URL makléra, limit a po spustení uvidíš:
- log načítania (profil + jednotlivé detaily)
- výsledný JSON priamo na stránke

## Test

```bash
php tests/test_scraper.php
```

## Integrácia na osobný web

Odporúčanie:

1. Spúšťať skript cez cron (napr. každé 3 hodiny).
2. Uložiť nový `properties.json` do repozitára alebo objektového storage.
3. Web načíta aktuálne dáta pri builde alebo runtime.

## Poznámka

Ak je scraping blokovaný podľa IP/proxy, spúšťaj skript na serveri/VPS s povoleným prístupom.
