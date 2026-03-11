#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/AtomiaScraper.php';

use AtomiaScraper\AtomiaScraper;

function printHelp(): void
{
    echo "Použitie:\n";
    echo "  php bin/scrape.php <agent_url> [--output=output/properties.json] [--limit=10] [--pretty]\n\n";
    echo "Príklad:\n";
    echo "  php bin/scrape.php 'https://www.atomia.sk/makler/12-ing-michaela-karafa#properties' --pretty\n";
}

$args = $argv;
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
];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $options['output'] = substr($arg, 9);
    } elseif (str_starts_with($arg, '--limit=')) {
        $options['limit'] = (int) substr($arg, 8);
    } elseif ($arg === '--pretty') {
        $options['pretty'] = true;
    } else {
        fwrite(STDERR, "Neznámy parameter: {$arg}\n");
        exit(1);
    }
}

$scraper = new AtomiaScraper();
$properties = $scraper->scrape($agentUrl, $options['limit']);
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

echo "Uložené: {$outputPath} (počet nehnuteľností: " . count($payload) . ")\n";
