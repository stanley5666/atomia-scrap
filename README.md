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

### Prepínače

- `--output=...` vlastný JSON súbor (napr. `--output=data/nehnutelnosti.json`)
- `--limit=...` obmedzenie počtu inzerátov na test
- `--pretty` formátovaný JSON
- `--help` pomoc

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
