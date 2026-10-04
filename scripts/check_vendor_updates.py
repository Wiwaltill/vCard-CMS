#!/usr/bin/env python3
"""Read-only release check for bundled PHP libraries; no third-party dependencies."""
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "public/includes/vendor/packages.json"


def version_tuple(value):
    match = re.fullmatch(r"[vV]?(\d+)\.(\d+)\.(\d+)", value)
    if not match:
        raise ValueError(f"Unsupported stable version: {value!r}")
    return tuple(int(part) for part in match.groups())


def latest_release(repository):
    headers = {"Accept": "application/vnd.github+json", "User-Agent": "vCard-CMS-vendor-check"}
    token = os.environ.get("GITHUB_TOKEN")
    if token:
        headers["Authorization"] = "Bearer " + token
    request = urllib.request.Request(f"https://api.github.com/repos/{repository}/releases/latest", headers=headers)
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return json.load(response)
    except urllib.error.HTTPError as error:
        raise RuntimeError(f"GitHub API: HTTP {error.code}") from None
    except (urllib.error.URLError, TimeoutError, json.JSONDecodeError):
        raise RuntimeError("GitHub API unavailable or invalid response") from None


def check_packages(packages, fetch=latest_release):
    results = []
    seen = set()
    for package in packages:
        repository = package["repository"]
        current = package["version"]
        if not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", repository) or repository in seen:
            raise ValueError("Invalid or duplicate vendor repository")
        seen.add(repository)
        version_tuple(current)
        result = dict(package)
        try:
            release = fetch(repository)
            if release.get("draft") or release.get("prerelease"):
                raise ValueError("Expected a stable published release")
            latest = release["tag_name"]
            newest = version_tuple(latest)
            result.update(latest=latest, update=newest > version_tuple(current), major=newest[0] > version_tuple(current)[0])
        except (RuntimeError, ValueError, KeyError, TypeError) as error:
            result.update(error=str(error), update=False)
        results.append(result)
    return results


def escape(value):
    return str(value).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("|", "&#124;").replace("\r", " ").replace("\n", " ")


def report(results):
    lines = ["# Vendor-Versionen", "", "| Paket | Eingebunden | Neueste stabile Version | Status |", "| --- | --- | --- | --- |"]
    for item in results:
        repository = item["repository"]
        if "error" in item:
            latest, status = "—", "Prüfung fehlgeschlagen: " + item["error"]
        else:
            latest = item["latest"]
            status = "Update verfügbar" if item["update"] else "Kein neueres Release"
            if item["update"] and item["major"]:
                status += " – neue Hauptversion; PHP-Kompatibilität prüfen"
        lines.append(f"| [{escape(item['name'])}](https://github.com/{repository}/releases) | {escape(item['version'])} | {escape(latest)} | {escape(status)} |")
    lines.extend(["", "Dieser Check verändert keine Vendor-Dateien. Vor Updates PHP-Anforderungen, Lizenzhinweise und die Tests unter PHP 8.0 und 8.4 prüfen.", ""])
    return "\n".join(lines)


def annotation(message):
    return message.replace("%", "%25").replace("\r", "%0D").replace("\n", "%0A")


def main():
    try:
        packages = json.loads(MANIFEST.read_text())["packages"]
        if not isinstance(packages, list) or not packages:
            raise ValueError("Vendor manifest must contain a nonempty package list")
        results = check_packages(packages)
    except (ValueError, KeyError, TypeError, OSError) as error:
        print("Vendor manifest invalid: " + str(error))
        return 1
    summary = report(results)
    print(summary)
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as output:
            output.write(summary)
    if os.environ.get("GITHUB_ACTIONS") == "true":
        for item in results:
            if item.get("error"):
                print("::error::" + annotation(item["name"] + ": " + item["error"]))
            elif item["update"]:
                print("::warning::" + annotation(item["name"] + ": " + item["version"] + " → " + item["latest"]))
    return 1 if any(item.get("error") for item in results) else 0


if __name__ == "__main__":
    raise SystemExit(main())
