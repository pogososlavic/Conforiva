#!/usr/bin/env python3
"""Read public discovery URLs; never log cookies, credentials or raw HTML."""

import argparse
import concurrent.futures
import datetime
import json
import os
import re
import ssl
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from html.parser import HTMLParser
from pathlib import Path


class Metadata(HTMLParser):
    def __init__(self):
        super().__init__()
        self.robots = None
        self.canonical = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "meta" and attrs.get("name", "").lower() == "robots":
            self.robots = attrs.get("content")
        if tag == "link" and attrs.get("rel", "").lower() == "canonical":
            self.canonical = attrs.get("href")


def inspect(url):
    result = {"url": url}
    request = urllib.request.Request(url, headers={"User-Agent": "HOMYA-Google-Readiness/1.0"})
    try:
        with urllib.request.urlopen(request, timeout=20, context=ssl.create_default_context()) as response:
            result.update(status=response.status, final_url=response.url,
                          content_type=response.headers.get("Content-Type"),
                          robots_header=response.headers.get("X-Robots-Tag"))
            body = response.read(5_000_001)
            if len(body) > 5_000_000:
                result["body_limit_reached"] = True
                return result
            content = body.decode("utf-8", errors="replace")
            if urllib.parse.urlparse(url).path == "/robots.txt":
                result["robots_file"] = content[:10000]
            elif ".xml" in urllib.parse.urlparse(url).path:
                try:
                    root = ET.fromstring(body)
                    result["xml_root"] = root.tag
                    result["xml_entries"] = len(root.findall(".//{*}loc")) if "sitemap" in url else len(root.findall(".//item"))
                except ET.ParseError:
                    result["xml_invalid"] = True
            elif "text/html" in (result["content_type"] or ""):
                metadata = Metadata()
                metadata.feed(content)
                result.update(robots_meta=metadata.robots, canonical=metadata.canonical)
    except urllib.error.HTTPError as error:
        result.update(status=error.code, robots_header=error.headers.get("X-Robots-Tag"))
    except (urllib.error.URLError, TimeoutError, OSError) as error:
        result["network_error"] = type(error).__name__
        result["reason_type"] = type(getattr(error, "reason", error)).__name__
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--origin", default="https://homya.eu")
    parser.add_argument("--output", help="Save a new JSON report under output/; no overwriting")
    args = parser.parse_args()
    origin = args.origin.rstrip("/")
    parsed = urllib.parse.urlparse(origin)
    if parsed.scheme != "https" or not parsed.hostname or parsed.username or parsed.password or parsed.path or parsed.query or parsed.fragment:
        parser.error("Use an HTTPS origin without credentials, path or query.")
    destination = None
    if args.output:
        if not re.fullmatch(r"output/(?:[A-Za-z0-9][A-Za-z0-9_-]*/)*[A-Za-z0-9][A-Za-z0-9_-]*\.json", args.output):
            parser.error("Use a relative JSON filename under output/.")
        destination = Path(__file__).resolve().parent.parent / args.output
        if destination.exists() or destination.is_symlink() or any(parent.is_symlink() for parent in destination.parents):
            parser.error("Choose a new report path without symbolic links.")
    paths = ["/fr", "/en", "/de", "/robots.txt", "/sitemap.xml", "/google/merchant/fr.xml",
             "/fr/contact", "/fr/livraison", "/fr/paiement", "/fr/retractation", "/fr/confidentialite"]
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        results = list(pool.map(inspect, [origin + path for path in paths]))
    report = json.dumps({"checked_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
                         "origin": origin, "method": "public GET only; no forms or account access", "pages": results},
                        ensure_ascii=False, indent=2)
    if destination:
        destination.parent.mkdir(parents=True, mode=0o700, exist_ok=True)
        fd = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as output:
            output.write(report + "\n")
    print(report)


if __name__ == "__main__":
    main()
