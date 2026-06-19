<?php
/**
 * AUTOKASSA – online ocenenie vozidla
 * ----------------------------------------------------------------------------
 * Na pozadí porovná podobné autá na autobazar.eu a vráti priemernú cenu.
 * Frontend (assets/js/main.js) z nej vypočíta možnú pôžičku podľa km sadzieb.
 *
 * Volanie:  /api/ocenenie.php?znacka=Skoda&model=Octavia&rok=2017&km=120000
 * Odpoveď:  { "ok": true, "count": 23, "avg": 9450, "currency": "EUR",
 *             "source": "autobazar.eu", "sample": "https://..." }
 *
 * ⚠️ DÔLEŽITÉ – TREBA OVERIŤ NA SERVERI:
 *   Štruktúru autobazar.eu (URL vyhľadávania a formát ceny v HTML) som nevedel
 *   overiť (portál blokuje automatické čítanie). Nižšie sú preto rozumné, ale
 *   ODHADNUTÉ pravidlá. Po nahratí na hosting otestujte a v prípade potreby
 *   upravte funkciu build_search_url() a regulárne výrazy v extract_prices().
 *   Portál môže mať aj ochranu proti botom (Cloudflare) – ak vráti prázdno,
 *   bude treba realistickejšie hlavičky, proxy alebo oficiálnu dohodu o prístupe.
 *   Pozn.: rešpektujte podmienky používania autobazar.eu a primeranú frekvenciu.
 * ----------------------------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');         // aby web (aj náhľad) mohol volať API
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=3600');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* ---------- nastavenia ---------- */
const CACHE_TTL   = 43200;     // 12 h – nech nezaťažujeme portál opakovane
const PRICE_MIN   = 300;       // ignoruj nereálne nízke/vysoké hodnoty
const PRICE_MAX   = 500000;
const MAX_SAMPLES = 60;        // koľko cien max. spriemerovať
const TRIM_RATIO  = 0.10;      // orež 10 % najnižších a najvyšších (odstránenie odľahlých)

/* ---------- vstup ---------- */
$znacka = trim((string)($_GET['znacka'] ?? ''));
$model  = trim((string)($_GET['model']  ?? ''));
$rok    = (int)($_GET['rok'] ?? 0);
$km     = (int)($_GET['km']  ?? 0);

if ($znacka === '' || $model === '') {
    echo json_encode(['ok' => false, 'message' => 'Zadajte značku aj model.']);
    exit;
}

/* ---------- cache ---------- */
$cacheKey  = strtolower(preg_replace('/[^a-z0-9]+/i', '_', "$znacka-$model-$rok"));
$cacheFile = sys_get_temp_dir() . '/autokassa_ocenenie_' . $cacheKey . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < CACHE_TTL)) {
    echo file_get_contents($cacheFile);
    exit;
}

/* ---------- scrape ---------- */
$searchUrl = build_search_url($znacka, $model, $rok);
$html = http_get($searchUrl);

if ($html === null) {
    out(['ok' => false, 'message' => 'Zdroj cien je momentálne nedostupný. Nechajte nám kontakt a oceníme auto ručne.'], $cacheFile, false);
}

$prices = extract_prices($html);
$prices = array_values(array_filter($prices, fn($p) => $p >= PRICE_MIN && $p <= PRICE_MAX));

if (count($prices) < 3) {
    out(['ok' => false, 'count' => count($prices),
         'message' => 'Nenašli sme dosť podobných áut. Nechajte nám kontakt a oceníme auto ručne.'], $cacheFile, false);
}

$avg = trimmed_mean($prices, TRIM_RATIO);

out([
    'ok'       => true,
    'count'    => count($prices),
    'avg'      => (int)round($avg),
    'currency' => 'EUR',
    'source'   => 'autobazar.eu',
    'sample'   => $searchUrl,
    'query'    => ['znacka' => $znacka, 'model' => $model, 'rok' => $rok, 'km' => $km],
], $cacheFile, true);


/* ============================ pomocné funkcie ============================ */

/**
 * Zostaví URL vyhľadávania na autobazar.eu.
 * TODO: overiť presný formát na serveri. Ideálne použiť filtre značka/model/rok
 * (presnejšie ako fulltext). Tu je fulltextová verzia ako bezpečný štart.
 */
function build_search_url(string $znacka, string $model, int $rok): string {
    $q = trim("$znacka $model");
    return 'https://www.autobazar.eu/vyhladavanie/?q=' . rawurlencode($q);
}

/** Stiahne HTML s realistickými hlavičkami prehliadača. */
function http_get(string $url): ?string {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_ENCODING       => '',           // povolí gzip
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: sk-SK,sk;q=0.9,cs;q=0.8,en;q=0.5',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400 || $body === '') return null;
    return $body;
}

/**
 * Vytiahne ceny z HTML. Skúša viac stratégií:
 *   1) JSON-LD / dátové polia "price"
 *   2) textové sumy v eurách: "12 900 €", "12.900 €", "12900 €", "€ 12 900"
 * TODO: po overení markupu autobazar.eu sa dá zúžiť na konkrétny selektor/atribút.
 */
function extract_prices(string $html): array {
    $prices = [];

    // 1) "price": 12900  /  "price":"12 900"
    if (preg_match_all('/"price"\s*:\s*"?([\d\s.,]{3,})"?/i', $html, $m)) {
        foreach ($m[1] as $raw) { $p = normalize_price($raw); if ($p) $prices[] = $p; }
    }

    // 2) sumy v eurách v texte
    if (preg_match_all('/(?:€\s*)?(\d{1,3}(?:[\s.\x{00A0}]\d{3})+|\d{4,6})\s*€/u', $html, $m)) {
        foreach ($m[1] as $raw) { $p = normalize_price($raw); if ($p) $prices[] = $p; }
    }

    // obmedz počet vzoriek
    if (count($prices) > MAX_SAMPLES) $prices = array_slice($prices, 0, MAX_SAMPLES);
    return $prices;
}

/** "12 900" / "12.900" / "12,900" / "12900" -> 12900 (int) */
function normalize_price(string $raw): ?int {
    $s = preg_replace('/[^\d]/u', '', $raw);   // necháme len číslice (oddeľovače tisícov preč)
    if ($s === '') return null;
    $n = (int)$s;
    return $n > 0 ? $n : null;
}

/** Priemer po orezaní odľahlých hodnôt (trimmed mean). */
function trimmed_mean(array $vals, float $ratio): float {
    sort($vals);
    $n = count($vals);
    $cut = (int)floor($n * $ratio);
    if ($n - 2 * $cut >= 1) $vals = array_slice($vals, $cut, $n - 2 * $cut);
    return array_sum($vals) / count($vals);
}

/** Pošle JSON, voliteľne uloží do cache, ukončí beh. */
function out(array $data, string $cacheFile, bool $cacheIt): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($cacheIt) @file_put_contents($cacheFile, $json);
    echo $json;
    exit;
}
