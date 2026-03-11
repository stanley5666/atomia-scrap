from atomia_scraper.scraper import AtomiaScraper


def test_parse_property_links_deduplicates_and_normalizes() -> None:
    html = """
    <html><body>
      <a href="/nehnutelnost/byt-1">Byt 1</a>
      <a href="https://www.atomia.sk/nehnutelnost/dom-2">Dom 2</a>
      <a href="/kontakt">Kontakt</a>
      <a href="/nehnutelnost/byt-1">Byt 1 dup</a>
    </body></html>
    """
    scraper = AtomiaScraper()
    links = scraper.parse_property_links(html, "https://www.atomia.sk/makler/12#properties")
    assert links == [
        "https://www.atomia.sk/nehnutelnost/byt-1",
        "https://www.atomia.sk/nehnutelnost/dom-2",
    ]


def test_parse_detail_extracts_core_fields() -> None:
    html = """
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
    """
    scraper = AtomiaScraper()
    detail = scraper.parse_detail(html, "https://www.atomia.sk/nehnutelnost/byt-1")

    assert detail.title == "Priestranný 3-izbový byt"
    assert detail.price == "199000"
    assert detail.area_m2 == "78 m²"
    assert detail.location == "Tomášikova 1"
    assert detail.images == ["https://www.atomia.sk/images/flat.jpg"]
