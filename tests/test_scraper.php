<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/AtomiaScraper.php';

use AtomiaScraper\AtomiaScraper;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function testParsePropertyLinks(): void
{
    $html = <<<'HTML'
    <html><body>
      <a href="/nehnutelnost/byt-1">Byt 1</a>
      <a href="https://www.atomia.sk/nehnutelnost/dom-2">Dom 2</a>
      <a href="/kontakt">Kontakt</a>
      <a href="/nehnutelnost/byt-1">Byt 1 dup</a>
    </body></html>
    HTML;

    $scraper = new AtomiaScraper();
    $links = $scraper->parsePropertyLinks($html, 'https://www.atomia.sk/makler/12#properties');

    assertTrue(count($links) === 2, 'Počet linkov musí byť 2');
    assertTrue($links[0] === 'https://www.atomia.sk/nehnutelnost/byt-1', 'Prvý link nesedí');
    assertTrue($links[1] === 'https://www.atomia.sk/nehnutelnost/dom-2', 'Druhý link nesedí');
}

function testParseDetail(): void
{
    $html = <<<'HTML'
    <html><body>
      <h1>Priestranný 3-izbový byt</h1>
      <div class="description">Skvelá lokalita, kompletná rekonštrukcia.</div>
      <table>
        <tr><th>Cena</th><td>199 000 €</td></tr>
        <tr><th>Výmera</th><td>78 m²</td></tr>
        <tr><th>Lokalita</th><td>Bratislava - Ružinov</td></tr>
      </table>
      <img src="/images/flat.jpg" />
      <script type="application/ld+json">
        {
          "@type": "Apartment",
          "address": {"streetAddress": "Tomášikova 1"},
          "offers": {"price": "199000"}
        }
      </script>
    </body></html>
    HTML;

    $scraper = new AtomiaScraper();
    $detail = $scraper->parseDetail($html, 'https://www.atomia.sk/nehnutelnost/byt-1');

    assertTrue($detail->title === 'Priestranný 3-izbový byt', 'Názov nesedí');
    assertTrue($detail->price === '199000', 'Cena nesedí');
    assertTrue($detail->areaM2 === '78 m²', 'Výmera nesedí');
    assertTrue($detail->location === 'Tomášikova 1', 'Lokalita nesedí');
    assertTrue($detail->images[0] === 'https://www.atomia.sk/images/flat.jpg', 'Obrázok nesedí');
}

testParsePropertyLinks();
testParseDetail();

echo "OK\n";
