<?php
/**
 * relay.php — prenos pre spoločný draft dvoch hráčov na dvoch zariadeniach.
 *
 * Server nepozná pravidlá hry. Drží len usporiadaný zoznam ťahov a stráži,
 * že ťahá ten, kto je na rade, a že hrdinu neberú dvaja naraz. Zvyšok si
 * z toho zoznamu odvodia obaja prehliadače rovnako.
 *
 * Žiadna databáza — jeden JSON súbor na draft v adresári rooms/.
 * Písané na PHP 7.0+.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

define('ROOMDIR',   __DIR__ . '/rooms');
define('CODE_ABC',  'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'); // bez znakov, čo sa mýlia
define('CODE_LEN',  4);
define('PER_SEAT',  7);
define('MAX_AGE',   21600);  // draft žije 6 hodín
define('MAX_ROOMS', 400);

function out($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail($msg, $status = 400) { out(array('error' => $msg), $status); }

function room_path($code) { return ROOMDIR . '/' . $code . '.json'; }

function ensure_dir() {
    if (!is_dir(ROOMDIR) && !@mkdir(ROOMDIR, 0775, true) && !is_dir(ROOMDIR)) {
        fail('Na serveri sa nedá vytvoriť adresár rooms/ — skontroluj práva na zápis.', 500);
    }
    $ht = ROOMDIR . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}

/** Zmaže staré drafty. Beží len pri zakladaní nového, nie pri každom dopyte. */
function sweep() {
    $files = glob(ROOMDIR . '/*.json');
    if (!$files) return;
    $now = time();
    foreach ($files as $f) {
        if ($now - (int)@filemtime($f) > MAX_AGE) @unlink($f);
    }
    if (count($files) > MAX_ROOMS) fail('Na serveri je priveľa otvorených draftov, skús o chvíľu.', 503);
}

function rand_token() {
    if (function_exists('random_bytes'))          return bin2hex(random_bytes(12));
    if (function_exists('openssl_random_pseudo_bytes')) return bin2hex(openssl_random_pseudo_bytes(12));
    return sha1(uniqid((string)mt_rand(), true));
}

function rand_code() {
    $abc = CODE_ABC; $n = strlen($abc); $c = '';
    for ($i = 0; $i < CODE_LEN; $i++) {
        $c .= $abc[function_exists('random_int') ? random_int(0, $n - 1) : mt_rand(0, $n - 1)];
    }
    return $c;
}

function valid_code($c) { return is_string($c) && preg_match('/^[A-Z0-9]{' . CODE_LEN . '}$/', $c) === 1; }

/** Kto je na ťahu. Presne tá istá logika ako v prehliadači. */
function turn_of($picks) {
    $count = array(0, 0); $turn = 0;
    foreach ($picks as $p) {
        $count[(int)$p['seat']]++;
        if ($count[1 - $turn] < PER_SEAT) $turn = 1 - $turn;
    }
    return $turn;
}

/** Verejný pohľad na draft — tokeny hráčov sa von neposielajú. */
function public_room($r) {
    return array(
        'code'   => $r['code'],
        'ver'    => (int)$r['ver'],
        'picks'  => array_values($r['picks']),
        'turn'   => turn_of($r['picks']),
        'joined' => (empty($r['seats'][0]) ? 0 : 1) + (empty($r['seats'][1]) ? 0 : 1),
    );
}

function read_room($code) {
    $p = room_path($code);
    if (!is_file($p)) fail('Draft ' . $code . ' neexistuje alebo mu vypršal čas.', 404);
    $r = json_decode((string)file_get_contents($p), true);
    if (!is_array($r)) fail('Draft je poškodený.', 500);
    return $r;
}

/**
 * Načíta, dá upraviť a uloží draft pod exkluzívnym zámkom, aby dvaja hráči
 * naraz neprepísali jeden druhého.
 */
function mutate($code, $fn) {
    $p = room_path($code);
    $fh = @fopen($p, 'r+');
    if (!$fh) fail('Draft ' . $code . ' neexistuje alebo mu vypršal čas.', 404);
    if (!flock($fh, LOCK_EX)) { fclose($fh); fail('Draft je práve zamknutý, skús znova.', 503); }

    $r = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($r)) { flock($fh, LOCK_UN); fclose($fh); fail('Draft je poškodený.', 500); }

    $r = $fn($r);            // pri chybe zavolá fail() a skript skončí (zámok uvoľní PHP)
    $r['ver'] = (int)$r['ver'] + 1;

    $enc = json_encode($r, JSON_UNESCAPED_UNICODE);
    ftruncate($fh, 0); rewind($fh); fwrite($fh, $enc); fflush($fh);
    flock($fh, LOCK_UN); fclose($fh);
    return $r;
}

function body_json() {
    $raw = file_get_contents('php://input');
    $j = json_decode((string)$raw, true);
    return is_array($j) ? $j : array();
}

// ---------------------------------------------------------------- akcie

ensure_dir();
$action = isset($_GET['a']) ? $_GET['a'] : '';
$code   = isset($_GET['r']) ? strtoupper((string)$_GET['r']) : '';

if ($action === 'ping') {
    out(array('ok' => true, 'php' => PHP_VERSION, 'writable' => is_writable(ROOMDIR)));
}

if ($action === 'create') {
    sweep();
    for ($try = 0; $try < 30; $try++) {
        $c = rand_code();
        $fh = @fopen(room_path($c), 'x');    // 'x' zlyhá, ak kód už existuje
        if (!$fh) continue;
        $token = rand_token();
        $room = array('code' => $c, 'ver' => 1, 'picks' => array(),
                      'seats' => array($token, null), 'created' => time());
        fwrite($fh, json_encode($room, JSON_UNESCAPED_UNICODE));
        fclose($fh);
        out(array('code' => $c, 'seat' => 0, 'token' => $token, 'room' => public_room($room)));
    }
    fail('Nepodarilo sa vyrobiť voľný kód draftu, skús znova.', 503);
}

if ($action === 'join') {
    if (!valid_code($code)) fail('Kód draftu má ' . CODE_LEN . ' znaky.');
    $token = rand_token();
    $room = mutate($code, function ($r) use ($token) {
        if (!empty($r['seats'][1])) fail('Draft je plný — hrajú v ňom už dvaja.', 409);
        $r['seats'][1] = $token;
        return $r;
    });
    out(array('code' => $code, 'seat' => 1, 'token' => $token, 'room' => public_room($room)));
}

if ($action === 'state') {
    if (!valid_code($code)) fail('Kód draftu má ' . CODE_LEN . ' znaky.');
    $r = read_room($code);
    $since = isset($_GET['v']) ? (int)$_GET['v'] : -1;
    if ((int)$r['ver'] === $since) out(array('nochange' => true));   // klient už tento stav má
    out(array('room' => public_room($r)));
}

if ($action === 'pick') {
    if (!valid_code($code)) fail('Kód draftu má ' . CODE_LEN . ' znaky.');
    $in    = body_json();
    $seat  = isset($in['seat']) ? (int)$in['seat'] : -1;
    $token = isset($in['token']) ? (string)$in['token'] : '';
    $id    = isset($in['id']) ? (int)$in['id'] : -1;
    if ($seat !== 0 && $seat !== 1) fail('Neplatné miesto hráča.');
    if ($id < 0 || $id > 999)       fail('Neplatný hrdina.');

    $room = mutate($code, function ($r) use ($seat, $token, $id) {
        if (empty($r['seats'][$seat]) || !hash_equals((string)$r['seats'][$seat], $token)) {
            fail('Toto miesto v drafte ti nepatrí.', 403);
        }
        if (empty($r['seats'][1])) fail('Súper sa ešte nepripojil.', 409);

        $count = array(0, 0);
        foreach ($r['picks'] as $p) {
            if ((int)$p['id'] === $id) fail('Tohto hrdinu už niekto vzal.', 409);
            $count[(int)$p['seat']]++;
        }
        if ($count[0] >= PER_SEAT && $count[1] >= PER_SEAT) fail('Draft je hotový.', 409);
        if (turn_of($r['picks']) !== $seat) fail('Nie si na ťahu.', 409);

        $r['picks'][] = array('seat' => $seat, 'id' => $id);
        return $r;
    });
    out(array('room' => public_room($room)));
}

fail('Neznáma akcia. Použi ?a=create, join, state, pick alebo ping.', 404);
