# Fúzna aréna — nasadenie na web

`public/index.html` je hotová hra (jeden samostatný súbor, žiadny build).
`deploy.sh` ju nahrá na FTP, pričom heslo číta z macOS Keychainu.

## Prvé spustenie

```bash
chmod +x deploy.sh

# 1. Ulož heslo do Keychainu (stačí raz)
FTP_USER=tvoje_ftp_meno ./deploy.sh save-password

# 2. Pozri sa, ako vyzerá koreň FTP — kde je vlastne web
FTP_USER=tvoje_ftp_meno ./deploy.sh ls /

# 3. Nahraj
FTP_USER=tvoje_ftp_meno ./deploy.sh deploy
```

Ak už heslo v Keychaine máš (napr. od Transmitu alebo Cyberducku), krok 1 preskoč —
skript ho nájde sám. Hľadá položku s názvom `ftp.kseftar.sk` a tvojím používateľským
menom, najprv ako *internet password*, potom ako *generic password*.

Aby si nemusel `FTP_USER` písať zakaždým, zapíš si ho priamo do hlavičky `deploy.sh`.

## Kam sa to nahrá

Predvolene do podadresára `/fuznaarena`, takže sa **neprepíše existujúca titulná
stránka**. Výsledná adresa býva `https://kseftar.sk/fuznaarena/`.

Koreň FTP sa ale nemusí rovnať koreňu webu — na mnohých hostingoch je web až
v `www/`, `public_html/` alebo `httpdocs/`. Preto ten krok `ls /`. Podľa toho, čo
uvidíš, uprav cestu:

```bash
REMOTE_DIR=/www/fuznaarena FTP_USER=meno ./deploy.sh deploy
```

Skript nikdy nič nemaže — súbory s rovnakým názvom prepíše, ostatné nechá na pokoji.
Pred nahrávaním vypíše, čo presne pošle, a počká na potvrdenie (`-y` to preskočí).

## Ak nejde pripojenie

Predvolene sa vyžaduje šifrované FTPS. Ak ho hosting nepodporuje, curl skončí
chybou na TLS — vtedy skús bez šifrovania:

```bash
USE_FTPS=0 FTP_USER=meno ./deploy.sh ls /
```

Ber ale na vedomie, že obyčajné FTP posiela heslo v čitateľnej podobe. Ak to hosting
vie, lepšie je prejsť na SFTP.

## Jedna vec v hre, ktorá na bežnom hostingu nefunguje

Hra si ukladá históriu zostáv a vlastné obrázky hrdinov cez `window.storage`
(riadky `loadHist`, `loadCustom`, `saveCustom`, `saveHist`). To nie je štandardné
webové API — na obyčajnom hostingu neexistuje, volanie spadne do `catch` a záložka
**História** ostane navždy prázdna. Hra inak funguje úplne normálne.

Ak chceš, aby si ukladanie fungovalo, stačí pred `render();loadHist();loadCustom();`
na konci súboru doplniť náhradu cez `localStorage`:

```js
window.storage = window.storage || {
  get: async k => { const v = localStorage.getItem(k); return v ? {value:v} : null; },
  set: async (k,v) => localStorage.setItem(k,v)
};
```

Zámerne som to do `public/index.html` nedoplnil — je to zmena v tvojom kóde, nie
súčasť nahrávania.
