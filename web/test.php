<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/AtomiaScraper.php';

use AtomiaScraper\AtomiaScraper;
use AtomiaScraper\PropertyDetail;

$defaults = [
    'agent_url' => 'https://www.atomia.sk/makler/12-ing-michaela-karafa#properties',
    'limit' => '5',
    'pretty' => '1',
];

$input = [
    'agent_url' => trim((string) ($_POST['agent_url'] ?? $defaults['agent_url'])),
    'limit' => trim((string) ($_POST['limit'] ?? $defaults['limit'])),
    'pretty' => isset($_POST['pretty']) ? '1' : $defaults['pretty'],
];

$logs = [];
$resultJson = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($input['agent_url'] === '') {
            throw new RuntimeException('Zadaj URL makléra.');
        }

        $limit = $input['limit'] === '' ? null : max(1, (int) $input['limit']);

        $scraper = new AtomiaScraper();
        $logs[] = 'Načítavam profil makléra: ' . $input['agent_url'];

        $links = $scraper->collectPropertyLinks($input['agent_url']);
        if ($limit !== null) {
            $links = array_slice($links, 0, $limit);
        }

        $logs[] = 'Nájdené inzeráty: ' . count($links);

        $properties = [];
        foreach ($links as $i => $link) {
            $logs[] = sprintf('[%d/%d] Načítavam detail: %s', $i + 1, count($links), $link);

            try {
                $detailHtml = $scraper->fetchHtml($link);
                $properties[] = $scraper->parseDetail($detailHtml, $link);
            } catch (RuntimeException $e) {
                $properties[] = new PropertyDetail(url: $link, attributes: ['error' => $e->getMessage()]);
            }
        }

        $payload = array_map(static fn ($item) => $item->toArray(), $properties);
        $flags = JSON_UNESCAPED_UNICODE;
        if ($input['pretty'] === '1') {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($payload, $flags);
        if (!is_string($json)) {
            throw new RuntimeException('Nepodarilo sa vytvoriť JSON.');
        }

        $resultJson = $json;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <title>Atomia scraper – web test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem auto; max-width: 1000px; padding: 0 1rem; }
        form { display: grid; gap: .75rem; margin-bottom: 1.25rem; }
        label { font-weight: 600; }
        input[type="text"], input[type="number"] { width: 100%; padding: .55rem; }
        button { width: fit-content; padding: .55rem 1rem; cursor: pointer; }
        .box { border: 1px solid #ddd; padding: .9rem; border-radius: 8px; margin-top: .75rem; }
        .error { background: #fff3f3; border-color: #f4aaaa; color: #9b1c1c; }
        pre { white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>
<h1>Atomia scraper – test cez HTML</h1>

<form method="post">
    <div>
        <label for="agent_url">URL makléra</label>
        <input id="agent_url" name="agent_url" type="text" required value="<?= h($input['agent_url']) ?>">
    </div>

    <div>
        <label for="limit">Limit inzerátov</label>
        <input id="limit" name="limit" type="number" min="1" value="<?= h($input['limit']) ?>">
    </div>

    <label>
        <input type="checkbox" name="pretty" value="1" <?= $input['pretty'] === '1' ? 'checked' : '' ?>>
        Pretty JSON
    </label>

    <button type="submit">Spustiť test</button>
</form>

<?php if ($error !== null): ?>
    <div class="box error">
        <strong>Chyba:</strong> <?= h($error) ?>
    </div>
<?php endif; ?>

<?php if ($logs !== []): ?>
    <div class="box">
        <h2>Log</h2>
        <pre><?= h(implode("\n", $logs)) ?></pre>
    </div>
<?php endif; ?>

<?php if ($resultJson !== null): ?>
    <div class="box">
        <h2>Výsledok (JSON)</h2>
        <pre><?= h($resultJson) ?></pre>
    </div>
<?php endif; ?>

</body>
</html>
