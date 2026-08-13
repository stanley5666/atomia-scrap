# Fúzna aréna

Zložíš 7 hrdinov, tí sa zlúčia do jednej fúzie — a hra vyhodnotí, **ktorá
z dvoch fúzií je silnejšia a prečo**. Žiadny ťahový súboj sa nehrá.

Hrať sa dá dvoma spôsobmi:

- **na jednom mobile** — dvaja sa striedajú pri jednom zariadení,
- **na dvoch mobiloch** — jeden založí draft, druhý sa pripojí kódom
  a každý si ťahá na svojom telefóne.

## Súbory

| súbor | načo |
|---|---|
| `public/index.html` | celá hra, jeden samostatný súbor |
| `public/relay.php`  | prenos ťahov medzi dvoma mobilmi |
| `deploy.sh`         | nahranie na FTP, heslo z macOS Keychainu |

`rooms/` si `relay.php` vytvorí sám pri prvom drafte, netreba ho zakladať.

## Nahranie

```bash
chmod +x deploy.sh
FTP_USER=tvoje_ftp_meno ./deploy.sh save-password   # stačí raz
FTP_USER=tvoje_ftp_meno ./deploy.sh
```

Cieľ je `/www_root_tobiaskarafa_sk`, teda **koreň webu** — `index.html`
prepíše doterajšiu titulnú stránku `tobiaskarafa.sk`. Skript na to pred
nahrávaním upozorní a počká na potvrdenie. Nič nemaže, len prepisuje súbory
rovnakého mena.

Ak si chceš najprv pozrieť, čo tam je:

```bash
FTP_USER=meno ./deploy.sh ls /www_root_tobiaskarafa_sk
```

## Ako funguje draft na dvoch mobiloch

1. Prvý ťukne **Draft na dvoch** — dostane štvorznakový kód.
2. **Zdieľať** pošle odkaz s kódom (`…/#AB12`); kto ho otvorí, pripojí sa sám.
   Druhý môže kód aj len prepísať do políčka.
3. Ťaháte striedavo. Kým vyberá súper, mriežka je zamknutá a hore vidíš, kto
   je na rade — ten pruh drží na vrchu obrazovky aj pri rolovaní.
4. Po štrnástom ťahu obom mobilom naskočí **rovnaký výsledok**.

Rovnaký doslova: vyhodnotenie nebeží na `Math.random`, ale na generátore
zasiatom zo zloženia oboch tímov. Tie isté tímy dajú ten istý verdikt na
akomkoľvek zariadení aj po opätovnom otvorení.

Server v tom hrá malú rolu — pravidlá hry nepozná. Drží len poradie ťahov
a stráži, že ťahá ten, kto je na rade, a že hrdinu neberú dvaja naraz.
Draft sa sám zmaže po šiestich hodinách.

### Keby dvojica nefungovala

Otvor si `https://tobiaskarafa.sk/relay.php?a=ping`. Má prísť
`{"ok":true,"php":"…","writable":true}`.

- Vypíše sa zdrojový kód PHP → hosting PHP nespúšťa.
- `"writable":false` → adresár nemá práva na zápis, treba `chmod 775`.
- Chyba 404 → `relay.php` neleží vedľa `index.html`.

Hra bez `relay.php` funguje ďalej, len bez hrania na dvoch mobiloch.

## Čo na bežnom hostingu nefunguje

História zostáv a vlastné obrázky hrdinov sa ukladajú cez `window.storage`,
čo nie je webové API — na obyčajnom hostingu neexistuje. Volanie spadne do
`catch`, takže sa nič nerozbije, ale záložka **História** ostane po zatvorení
prehliadača prázdna.

Ak to má prežiť, stačí pred posledný riadok `render();loadHist();loadCustom();`
doplniť:

```js
window.storage = window.storage || {
  get: async k => { const v = localStorage.getItem(k); return v ? {value:v} : null; },
  set: async (k,v) => localStorage.setItem(k,v)
};
```

Zámerne to tam nie je — je to zmena v hre, nie súčasť nahrávania.
