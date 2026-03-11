# Atomia scraper

Jednoduchá Python aplikácia, ktorá vytiahne ponuku nehnuteľností makléra z Atomia a uloží detailné dáta do JSON. Výsledný súbor vieš následne použiť na osobnom webe (statický web, CMS import, vlastný frontend).

## Inštalácia

```bash
python -m venv .venv
source .venv/bin/activate
pip install -e .
```

## Použitie

```bash
python -m atomia_scraper.cli "https://www.atomia.sk/makler/12-ing-michaela-karafa#properties" --pretty
```

Výstup sa uloží do `output/properties.json`.

### Dôležité prepínače

- `--output`: vlastný názov výstupu (napr. `data/nehnutelnosti.json`)
- `--limit`: obmedzenie počtu inzerátov na testovanie
- `--pretty`: formátovaný JSON na ručnú kontrolu

## Čo sa exportuje

Na každú nehnuteľnosť sa ukladá:

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

## Integrácia na osobný web

Najjednoduchšie je načítať `properties.json` vo vlastnom frontende (napr. Next.js, Nuxt, Astro, Hugo) a vyrenderovať karty nehnuteľností + detail stránky.

Odporúčaný flow:

1. Spúšťať scraper cez cron (napr. každé 3 hodiny).
2. Commitnúť alebo publikovať nový JSON artefakt.
3. Web si pri builde načíta aktuálne dáta.

## Testy

```bash
pip install -e .[dev]
pytest
```

## Poznámka

Niektoré weby môžu blokovať scraping podľa siete/IP. V tom prípade použi vlastný server/VPS, kde je prístup povolený.
