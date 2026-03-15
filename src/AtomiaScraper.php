<?php

declare(strict_types=1);

namespace AtomiaScraper;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class PropertyDetail
{
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $listingId = null,
        public ?string $type = null,
        public ?string $status = null,
        public ?string $location = null,
        public ?string $price = null,
        public ?string $areaM2 = null,
        public ?string $description = null,
        /** @var array<int, string> */
        public array $images = [],
        /** @var array<string, string> */
        public array $attributes = [],
        /** @var array<string, mixed> */
        public array $jsonld = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'listing_id' => $this->listingId,
            'type' => $this->type,
            'status' => $this->status,
            'location' => $this->location,
            'price' => $this->price,
            'area_m2' => $this->areaM2,
            'description' => $this->description,
            'images' => $this->images,
            'attributes' => $this->attributes,
            'jsonld' => $this->jsonld,
        ];
    }
}

final class AtomiaScraper
{
    public function __construct(
        private int $timeout = 20,
        private string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
    ) {
    }

    public function fetchHtml(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "User-Agent: {$this->userAgent}\r\n",
            ],
            'https' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "User-Agent: {$this->userAgent}\r\n",
            ],
        ]);

        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            $lastError = error_get_last();
            $message = is_array($lastError) && isset($lastError['message']) ? $lastError['message'] : 'neznáma chyba';
            throw new RuntimeException("Nepodarilo sa stiahnuť {$url}: {$message}");
        }

        return $html;
    }

    /** @return array<int, string> */
    public function parsePropertyLinks(string $agentHtml, string $baseUrl): array
    {
        $dom = $this->createDom($agentHtml);
        $xpath = new DOMXPath($dom);

        $links = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim($anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            if (!preg_match('~/(nehnutelnost|property)/~i', $href)) {
                continue;
            }

            $links[$this->normalizeUrl($this->urlJoin($baseUrl, html_entity_decode($href)))] = true;
        }

        $result = array_keys($links);
        sort($result);

        return $result;
    }

    /** @return array<int, string> */
    public function parsePaginationLinks(string $agentHtml, string $baseUrl): array
    {
        $dom = $this->createDom($agentHtml);
        $xpath = new DOMXPath($dom);

        $links = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim($anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $absolute = $this->normalizeUrl($this->urlJoin($baseUrl, html_entity_decode($href)));
            if (!$this->isSameHost($baseUrl, $absolute)) {
                continue;
            }

            if (!preg_match('~/(makler|agent)/~i', $absolute)) {
                continue;
            }

            if (!$this->looksLikePaginationLink($href, $anchor)) {
                continue;
            }

            $links[$absolute] = true;
        }

        $result = array_keys($links);
        sort($result);

        return $result;
    }

    /** @return array<int, string> */
    public function collectPropertyLinks(string $agentUrl, ?int $maxPages = null): array
    {
        $queue = [$this->normalizeUrl($agentUrl)];
        $visited = [];
        $properties = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (!is_string($current)) {
                continue;
            }
            if (isset($visited[$current])) {
                continue;
            }

            if ($maxPages !== null && count($visited) >= $maxPages) {
                break;
            }

            $visited[$current] = true;
            $html = $this->fetchHtml($current);

            foreach ($this->parsePropertyLinks($html, $current) as $link) {
                $properties[$link] = true;
            }

            foreach ($this->parsePaginationLinks($html, $current) as $pageUrl) {
                if (!isset($visited[$pageUrl])) {
                    $queue[] = $pageUrl;
                }
            }
        }

        $result = array_keys($properties);
        sort($result);

        return $result;
    }

    public function parseDetail(string $html, string $url): PropertyDetail
    {
        $dom = $this->createDom($html);
        $xpath = new DOMXPath($dom);

        $detail = new PropertyDetail(url: $url);
        $detail->title = $this->extractText($xpath, '//h1[1]');
        $detail->description = $this->extractDescription($xpath);
        $detail->images = $this->extractImages($xpath, $url);
        $detail->attributes = $this->extractAttributes($xpath);
        $detail->jsonld = $this->extractJsonLd($xpath);

        $offers = $detail->jsonld['offers'] ?? null;
        if (is_array($offers)) {
            $detail->price = $detail->price ?? $this->norm((string) ($offers['price'] ?? ''));
        }

        if ($detail->jsonld !== []) {
            $detail->location = $detail->location ?? $this->jsonLdLocation($detail->jsonld);
            if (isset($detail->jsonld['@type']) && is_string($detail->jsonld['@type'])) {
                $detail->type = $detail->type ?? $detail->jsonld['@type'];
            }

            $image = $detail->jsonld['image'] ?? null;
            if (is_string($image) && $image !== '') {
                array_unshift($detail->images, $image);
            }
            if (is_array($image)) {
                foreach (array_reverse($image) as $img) {
                    if (is_string($img) && $img !== '') {
                        array_unshift($detail->images, $img);
                    }
                }
            }
        }

        foreach ($detail->attributes as $key => $value) {
            $lk = mb_strtolower($key);
            if (str_contains($lk, 'lokalita') || str_contains($lk, 'adresa') || str_contains($lk, 'mesto') || str_contains($lk, 'obec')) {
                $detail->location ??= $value;
            }
            if (str_contains($lk, 'cena') || str_contains($lk, 'price')) {
                $detail->price ??= $value;
            }
            if (str_contains($lk, 'výmera') || str_contains($lk, 'rozloha') || str_contains($lk, 'plocha')) {
                $detail->areaM2 ??= $value;
            }
            if (str_contains($lk, 'stav')) {
                $detail->status ??= $value;
            }
            if (str_contains($lk, 'typ') || str_contains($lk, 'druh')) {
                $detail->type ??= $value;
            }
            if (str_contains($lk, 'id') || str_contains($lk, 'číslo')) {
                $detail->listingId ??= $value;
            }
        }

        $detail->images = array_values(array_unique($detail->images));

        return $detail;
    }

    /** @return array<int, PropertyDetail> */
    public function scrape(string $agentUrl, ?int $limit = null): array
    {
        $links = $this->collectPropertyLinks($agentUrl);
        if ($limit !== null) {
            $links = array_slice($links, 0, $limit);
        }

        $properties = [];
        foreach ($links as $link) {
            try {
                $detailHtml = $this->fetchHtml($link);
                $properties[] = $this->parseDetail($detailHtml, $link);
            } catch (RuntimeException $e) {
                $properties[] = new PropertyDetail(url: $link, attributes: ['error' => $e->getMessage()]);
            }
        }

        return $properties;
    }

    private function createDom(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        $normalized = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $normalized);

        return $dom;
    }

    private function extractText(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);
        if (!$node) {
            return null;
        }

        return $this->norm($node->textContent);
    }

    private function extractDescription(DOMXPath $xpath): ?string
    {
        $queries = [
            '//*[@itemprop="description"][1]',
            '//*[contains(@class, "property-description")][1]',
            '//*[contains(@class, "detail-description")][1]',
            '//*[contains(@class, "description")][1]',
        ];

        foreach ($queries as $query) {
            $text = $this->extractText($xpath, $query);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function extractImages(DOMXPath $xpath, string $baseUrl): array
    {
        $images = [];

        foreach ($xpath->query('//img[@src]') ?: [] as $img) {
            if ($img instanceof DOMElement) {
                $src = trim($img->getAttribute('src'));
                if ($src !== '') {
                    $images[] = $this->urlJoin($baseUrl, html_entity_decode($src));
                }
            }
        }

        foreach ($xpath->query('//*[@data-src]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $src = trim($node->getAttribute('data-src'));
                if ($src !== '') {
                    $images[] = $this->urlJoin($baseUrl, html_entity_decode($src));
                }
            }
        }

        return $images;
    }

    /** @return array<string, mixed> */
    private function extractJsonLd(DOMXPath $xpath): array
    {
        $targetTypes = ['Residence', 'Offer', 'Product', 'Apartment', 'House'];

        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $raw = trim((string) $script->textContent);
            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }

            $candidates = array_is_list($decoded) ? $decoded : [$decoded];
            foreach ($candidates as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                $type = $candidate['@type'] ?? null;
                if (is_string($type) && in_array($type, $targetTypes, true)) {
                    return $candidate;
                }
                if (is_array($type)) {
                    foreach ($type as $tv) {
                        if (is_string($tv) && in_array($tv, $targetTypes, true)) {
                            return $candidate;
                        }
                    }
                }
            }
        }

        return [];
    }

    /** @return array<string, string> */
    private function extractAttributes(DOMXPath $xpath): array
    {
        $attributes = [];

        foreach ($xpath->query('//table//tr') ?: [] as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }
            $cells = $xpath->query('./th|./td', $row);
            if (($cells?->length ?? 0) < 2) {
                continue;
            }

            $key = $this->norm($cells->item(0)?->textContent ?? '');
            $value = $this->norm($cells->item(1)?->textContent ?? '');
            if ($key !== null && $value !== null) {
                $attributes[$key] = $value;
            }
        }

        foreach ($xpath->query('//li') ?: [] as $li) {
            $text = $this->norm($li->textContent ?? '');
            if ($text === null || !str_contains($text, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $text, 2));
            if ($key !== '' && $value !== '') {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /** @param array<string, mixed> $jsonld */
    private function jsonLdLocation(array $jsonld): ?string
    {
        $location = $jsonld['address'] ?? $jsonld['location'] ?? null;
        if (is_string($location)) {
            return $this->norm($location);
        }

        if (!is_array($location)) {
            return null;
        }

        foreach (['streetAddress', 'addressLocality', 'addressRegion', 'name'] as $key) {
            if (isset($location[$key]) && is_scalar($location[$key])) {
                return $this->norm((string) $location[$key]);
            }
        }

        return null;
    }

    private function norm(string $value): ?string
    {
        $clean = preg_replace('/\s+/u', ' ', html_entity_decode(trim($value)));
        if (!is_string($clean)) {
            return null;
        }

        $clean = trim($clean);

        return $clean === '' ? null : $clean;
    }

    private function normalizeUrl(string $url): string
    {
        return preg_replace('/#.*$/', '', $url) ?? $url;
    }

    private function looksLikePaginationLink(string $href, DOMElement $anchor): bool
    {
        $rel = mb_strtolower(trim($anchor->getAttribute('rel')));
        $class = mb_strtolower(trim($anchor->getAttribute('class')));
        $text = mb_strtolower(trim($anchor->textContent));

        if (preg_match('~([?&]page=|/page/)~i', $href)) {
            return true;
        }

        if (str_contains($rel, 'next') || str_contains($class, 'pagination') || str_contains($class, 'pager')) {
            return true;
        }

        return $text === 'ďalšia' || $text === 'next' || $text === '>'; 
    }

    private function isSameHost(string $base, string $candidate): bool
    {
        $baseHost = parse_url($base, PHP_URL_HOST);
        $candidateHost = parse_url($candidate, PHP_URL_HOST);

        return is_string($baseHost) && is_string($candidateHost) && mb_strtolower($baseHost) === mb_strtolower($candidateHost);
    }

    private function urlJoin(string $base, string $path): string
    {
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false || !isset($baseParts['scheme'], $baseParts['host'])) {
            return $path;
        }

        $root = $baseParts['scheme'] . '://' . $baseParts['host'];
        if (isset($baseParts['port'])) {
            $root .= ':' . $baseParts['port'];
        }

        if (str_starts_with($path, '/')) {
            return $root . $path;
        }

        $basePath = $baseParts['path'] ?? '/';
        $dir = rtrim((string) preg_replace('~/[^/]*$~', '/', $basePath), '/');

        return $root . ($dir === '' ? '' : $dir) . '/' . ltrim($path, '/');
    }
}
