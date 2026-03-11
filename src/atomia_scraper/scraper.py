from __future__ import annotations

from dataclasses import asdict, dataclass, field
from html import unescape
import json
import re
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin
from urllib.request import Request, urlopen

PROPERTY_LINK_PATTERN = re.compile(r"/nehnutelnost/|/property/", re.IGNORECASE)
HREF_PATTERN = re.compile(r"<a[^>]+href=[\"']([^\"']+)[\"']", re.IGNORECASE)
IMG_SRC_PATTERN = re.compile(r"<img[^>]+src=[\"']([^\"']+)[\"']", re.IGNORECASE)
DATA_SRC_PATTERN = re.compile(r"data-src=[\"']([^\"']+)[\"']", re.IGNORECASE)
SCRIPT_JSONLD_PATTERN = re.compile(
    r"<script[^>]+type=[\"']application/ld\+json[\"'][^>]*>(.*?)</script>",
    re.IGNORECASE | re.DOTALL,
)


@dataclass(slots=True)
class PropertyDetail:
    url: str
    title: str | None = None
    listing_id: str | None = None
    type: str | None = None
    status: str | None = None
    location: str | None = None
    price: str | None = None
    area_m2: str | None = None
    description: str | None = None
    images: list[str] = field(default_factory=list)
    attributes: dict[str, str] = field(default_factory=dict)
    jsonld: dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class AtomiaScraper:
    def __init__(self, timeout: int = 20, user_agent: str | None = None) -> None:
        self.timeout = timeout
        self.user_agent = (
            user_agent
            or "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
            "(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36"
        )

    def fetch_html(self, url: str) -> str:
        request = Request(url, headers={"User-Agent": self.user_agent})
        try:
            with urlopen(request, timeout=self.timeout) as response:
                charset = response.headers.get_content_charset() or "utf-8"
                return response.read().decode(charset, errors="replace")
        except (HTTPError, URLError) as exc:
            raise RuntimeError(f"Nepodarilo sa stiahnuť {url}: {exc}") from exc

    def parse_property_links(self, agent_html: str, base_url: str) -> list[str]:
        links: set[str] = set()
        for href in HREF_PATTERN.findall(agent_html):
            if href.startswith("#"):
                continue
            if PROPERTY_LINK_PATTERN.search(href):
                links.add(urljoin(base_url, unescape(href.strip())))
        return sorted(links)

    def parse_detail(self, html: str, url: str) -> PropertyDetail:
        detail = PropertyDetail(url=url)
        detail.title = _extract_title(html)
        detail.description = _extract_description(html)
        detail.images = _extract_images(html, url)
        detail.jsonld = _extract_jsonld(html)
        detail.attributes = _extract_attributes(html)

        offers = detail.jsonld.get("offers", {}) if isinstance(detail.jsonld, dict) else {}
        if isinstance(offers, dict):
            detail.price = _pick_first(detail.price, _norm(str(offers.get("price") or "")))

        if detail.jsonld and isinstance(detail.jsonld, dict):
            detail.location = _pick_first(detail.location, _jsonld_location(detail.jsonld))
            t = detail.jsonld.get("@type")
            if isinstance(t, str):
                detail.type = _pick_first(detail.type, t)

            image = detail.jsonld.get("image")
            if isinstance(image, str):
                detail.images = [image, *detail.images]
            elif isinstance(image, list):
                detail.images = [*(img for img in image if isinstance(img, str)), *detail.images]

        for key, value in detail.attributes.items():
            lk = key.lower()
            if any(s in lk for s in ("lokalita", "adresa", "mesto", "obec")):
                detail.location = _pick_first(detail.location, value)
            if any(s in lk for s in ("cena", "price")):
                detail.price = _pick_first(detail.price, value)
            if "výmera" in lk or "rozloha" in lk or "plocha" in lk:
                detail.area_m2 = _pick_first(detail.area_m2, value)
            if "stav" in lk:
                detail.status = _pick_first(detail.status, value)
            if "typ" in lk or "druh" in lk:
                detail.type = _pick_first(detail.type, value)
            if "id" in lk or "číslo" in lk:
                detail.listing_id = _pick_first(detail.listing_id, value)

        detail.images = list(dict.fromkeys(detail.images))
        return detail

    def scrape(self, agent_url: str, limit: int | None = None) -> list[PropertyDetail]:
        agent_html = self.fetch_html(agent_url)
        links = self.parse_property_links(agent_html, agent_url)
        if limit is not None:
            links = links[:limit]

        properties: list[PropertyDetail] = []
        for link in links:
            try:
                detail_html = self.fetch_html(link)
                properties.append(self.parse_detail(detail_html, link))
            except RuntimeError as exc:
                properties.append(PropertyDetail(url=link, attributes={"error": str(exc)}))
        return properties


def _strip_tags(raw: str) -> str:
    return re.sub(r"<[^>]+>", " ", raw)


def _norm(value: str | None) -> str | None:
    if value is None:
        return None
    compact = re.sub(r"\s+", " ", unescape(value)).strip()
    return compact or None


def _pick_first(current: str | None, candidate: str | None) -> str | None:
    return current or candidate


def _extract_title(html: str) -> str | None:
    match = re.search(r"<h1[^>]*>(.*?)</h1>", html, flags=re.IGNORECASE | re.DOTALL)
    if not match:
        return None
    return _norm(_strip_tags(match.group(1)))


def _extract_description(html: str) -> str | None:
    patterns = [
        r"<[^>]+itemprop=[\"']description[\"'][^>]*>(.*?)</[^>]+>",
        r"<div[^>]+class=[\"'][^\"']*property-description[^\"']*[\"'][^>]*>(.*?)</div>",
        r"<div[^>]+class=[\"'][^\"']*detail-description[^\"']*[\"'][^>]*>(.*?)</div>",
        r"<div[^>]+class=[\"'][^\"']*description[^\"']*[\"'][^>]*>(.*?)</div>",
    ]
    for pattern in patterns:
        match = re.search(pattern, html, flags=re.IGNORECASE | re.DOTALL)
        if match:
            return _norm(_strip_tags(match.group(1)))
    return None


def _extract_images(html: str, base_url: str) -> list[str]:
    sources = [*IMG_SRC_PATTERN.findall(html), *DATA_SRC_PATTERN.findall(html)]
    return [urljoin(base_url, unescape(src.strip())) for src in sources if src.strip()]


def _extract_jsonld(html: str) -> dict[str, Any]:
    for raw in SCRIPT_JSONLD_PATTERN.findall(html):
        blob = raw.strip()
        if not blob:
            continue
        try:
            data = json.loads(blob)
        except json.JSONDecodeError:
            continue

        candidates = data if isinstance(data, list) else [data]
        for item in candidates:
            if not isinstance(item, dict):
                continue
            type_value = item.get("@type")
            targets = {"Residence", "Offer", "Product", "Apartment", "House"}
            if type_value in targets:
                return item
            if isinstance(type_value, list) and any(t in targets for t in type_value):
                return item
    return {}


def _jsonld_location(data: dict[str, Any]) -> str | None:
    location = data.get("address") or data.get("location")
    if isinstance(location, str):
        return _norm(location)
    if isinstance(location, dict):
        for key in ("streetAddress", "addressLocality", "addressRegion", "name"):
            if location.get(key):
                return _norm(str(location[key]))
    return None


def _extract_attributes(html: str) -> dict[str, str]:
    attributes: dict[str, str] = {}

    for row in re.findall(r"<tr[^>]*>(.*?)</tr>", html, flags=re.IGNORECASE | re.DOTALL):
        cells = re.findall(r"<(?:th|td)[^>]*>(.*?)</(?:th|td)>", row, flags=re.IGNORECASE | re.DOTALL)
        if len(cells) >= 2:
            key = _norm(_strip_tags(cells[0]))
            value = _norm(_strip_tags(cells[1]))
            if key and value:
                attributes[key] = value

    li_blocks = re.findall(r"<li[^>]*>(.*?)</li>", html, flags=re.IGNORECASE | re.DOTALL)
    for raw in li_blocks:
        text = _norm(_strip_tags(raw))
        if text and ":" in text:
            key, value = [part.strip() for part in text.split(":", 1)]
            if key and value:
                attributes[key] = value

    return attributes
