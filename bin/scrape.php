<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/AtomiaScraper.php';

use AtomiaScraper\AtomiaScraper;
use AtomiaScraper\PropertyDetail;


function fail(string $message, int $code = 1): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . "\n");
    } else {
        echo $message . "\n";
    }
    exit($code);
}

function printHelp(): void
{
    echo "Použitie:\n";
    echo "  php bin/scrape.php <agent_url> [--output=output/properties.json] [--limit=10] [--pretty] [--quiet]\n\n";
    echo "Príklad:\n";
    echo "  php bin/scrape.php 'https://www.atomia.sk/makler/12-ing-michaela-karafa#properties' --pretty\n";
}

/** @param array<int, PropertyDetail> $properties */
function printSummary(array $properties): void
{
    echo "\nPrehľad načítaných nehnuteľností:\n";
    foreach ($properties as $index => $property) {
        $num = $index + 1;

        if (isset($property->attributes['error'])) {
            echo sprintf("%d. [CHYBA] %s\n", $num, $property->url);
            echo "   Dôvod: {$property->attributes['error']}\n";
            continue;
        }

        $title = $property->title ?? 'bez názvu';
        $location = $property->location ?? '-';
        $price = $property->price ?? '-';
        $images = count($property->images);

        echo sprintf("%d. %s\n", $num, $title);
        echo "   URL: {$property->url}\n";
        echo "   Lokalita: {$location}\n";
        echo "   Cena: {$price}\n";
        echo "   Obrázky: {$images}\n";
    }
}

$rawArgv = $GLOBALS['argv'] ?? ($_SERVER['argv'] ?? null);
if (!is_array($rawArgv)) {
    fail('Tento skript treba spustiť cez CLI: php bin/scrape.php <agent_url> [--output=...] [--limit=...] [--pretty] [--quiet]');
}

$args = $rawArgv;
array_shift($args);

if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true)) {
    printHelp();
    exit(0);
}

$agentUrl = array_shift($args);
if (!is_string($agentUrl) || $agentUrl === '') {
    fwrite(STDERR, "Chýba agent URL.\n");
    printHelp();
    exit(1);
}

$options = [
    'output' => 'output/properties.json',
    'limit' => null,
    'pretty' => false,
    'quiet' => false,
];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $options['output'] = substr($arg, 9);
    } elseif (str_starts_with($arg, '--limit=')) {
        $options['limit'] = (int) substr($arg, 8);
    } elseif ($arg === '--pretty') {
        $options['pretty'] = true;
    } elseif ($arg === '--quiet') {
        $options['quiet'] = true;
    } else {
        fail("Neznámy parameter: {$arg}");
    }
}

try {
    $scraper = new AtomiaScraper();

    if (!$options['quiet']) {
        echo "Načítavam profil makléra: {$agentUrl}\n";
    }

    $agentHtml = $scraper->fetchHtml($agentUrl);
    $links = $scraper->parsePropertyLinks($agentHtml, $agentUrl);
    if ($options['limit'] !== null) {
        $links = array_slice($links, 0, $options['limit']);
    }

    if (!$options['quiet']) {
        echo 'Nájdené inzeráty: ' . count($links) . "\n";
    }

    $properties = [];
    foreach ($links as $i => $link) {
        if (!$options['quiet']) {
            echo sprintf("[%d/%d] Načítavam detail: %s\n", $i + 1, count($links), $link);
        }

        try {
            $detailHtml = $scraper->fetchHtml($link);
            $properties[] = $scraper->parseDetail($detailHtml, $link);
        } catch (RuntimeException $e) {
            $properties[] = new PropertyDetail(url: $link, attributes: ['error' => $e->getMessage()]);
        }
    }

    $payload = array_map(static fn ($item) => $item->toArray(), $properties);

    $outputPath = $options['output'];
    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $jsonFlags = JSON_UNESCAPED_UNICODE;
    if ($options['pretty']) {
        $jsonFlags |= JSON_PRETTY_PRINT;
    }

    $json = json_encode($payload, $jsonFlags);
    if (!is_string($json)) {
        throw new RuntimeException('Nepodarilo sa vytvoriť JSON.');
    }

    file_put_contents($outputPath, $json);

    if (!$options['quiet']) {
        printSummary($properties);
    }

    echo "\nUložené: {$outputPath} (počet nehnuteľností: " . count($payload) . ")\n";
} catch (RuntimeException $e) {
    fail("Chyba: {$e->getMessage()}");
}
