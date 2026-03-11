from __future__ import annotations

import argparse
import json
from pathlib import Path

from .scraper import AtomiaScraper


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Stiahne všetky nehnuteľnosti makléra z atomia.sk a vyexportuje detailné dáta."
    )
    parser.add_argument("agent_url", help="URL profilu makléra (napr. https://...#properties)")
    parser.add_argument(
        "-o",
        "--output",
        default="output/properties.json",
        help="Výstupný JSON súbor (default: output/properties.json)",
    )
    parser.add_argument("--limit", type=int, default=None, help="Voliteľne obmedziť počet nehnuteľností")
    parser.add_argument(
        "--pretty",
        action="store_true",
        help="Formátovaný JSON vhodný na ručnú kontrolu",
    )
    return parser


def main() -> None:
    args = build_parser().parse_args()
    scraper = AtomiaScraper()
    properties = scraper.scrape(args.agent_url, limit=args.limit)

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    payload = [item.to_dict() for item in properties]
    with output_path.open("w", encoding="utf-8") as file:
        json.dump(payload, file, ensure_ascii=False, indent=2 if args.pretty else None)

    print(f"Uložené: {output_path} (počet nehnuteľností: {len(payload)})")


if __name__ == "__main__":
    main()
