#!/usr/bin/env python3
"""Configure ghaith-art.com zone: DNS, SSL, cache, performance. Token from env."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

ZONE = "9939c9d0c91bfe8d5a317e9e7e99001a"
ORIGIN_IP = "91.107.255.35"
TOKEN = os.environ["CLOUDFLARE_API_TOKEN"]
ACCOUNT = os.environ["CLOUDFLARE_ACCOUNT_ID"]


def req(method: str, url: str, body=None):
    data = None if body is None else json.dumps(body).encode()
    r = urllib.request.Request(
        url,
        data=data,
        method=method,
        headers={
            "Authorization": f"Bearer {TOKEN}",
            "Content-Type": "application/json",
        },
    )
    try:
        with urllib.request.urlopen(r) as resp:
            return json.load(resp)
    except urllib.error.HTTPError as e:
        err = e.read().decode()[:1500]
        return {"success": False, "http": e.code, "body": err}


def setting(name: str, value):
    out = req(
        "PATCH",
        f"https://api.cloudflare.com/client/v4/zones/{ZONE}/settings/{name}",
        {"value": value},
    )
    ok = out.get("success")
    print(f"setting {name}: {'ok' if ok else out}")
    return ok


def main() -> int:
    recs = req(
        "GET",
        f"https://api.cloudflare.com/client/v4/zones/{ZONE}/dns_records?per_page=100",
    )
    if not recs.get("success"):
        print("dns list failed", recs)
        return 1

    wanted = {
        "api.ghaith-art.com": ("A", ORIGIN_IP, True),
    }
    by_name = {}
    for rec in recs.get("result") or []:
        by_name.setdefault(rec["name"], []).append(rec)
        print(f"dns {rec['type']} {rec['name']} {rec.get('content')} proxied={rec.get('proxied')}")

    for name, (typ, content, proxied) in wanted.items():
        existing = [r for r in by_name.get(name, []) if r["type"] in ("A", "AAAA", "CNAME")]
        if existing:
            rec = existing[0]
            out = req(
                "PATCH",
                f"https://api.cloudflare.com/client/v4/zones/{ZONE}/dns_records/{rec['id']}",
                {"type": typ, "name": name, "content": content, "proxied": proxied, "ttl": 1},
            )
            print(f"update {name}: {'ok' if out.get('success') else out}")
        else:
            out = req(
                "POST",
                f"https://api.cloudflare.com/client/v4/zones/{ZONE}/dns_records",
                {"type": typ, "name": name, "content": content, "proxied": proxied, "ttl": 1},
            )
            print(f"create {name}: {'ok' if out.get('success') else out}")

    setting("ssl", "strict")
    setting("always_use_https", "on")
    setting("min_tls_version", "1.2")
    setting("tls_1_3", "on")
    setting("automatic_https_rewrites", "on")
    setting("brotli", "on")
    setting("early_hints", "on")
    setting("http2", "on")
    setting("http3", "on")
    setting("websockets", "on")
    setting("rocket_loader", "off")
    setting("email_obfuscation", "off")
    setting("hotlink_protection", "off")
    setting("ip_geolocation", "on")
    setting("browser_check", "on")
    setting("security_level", "medium")
    setting("opportunistic_encryption", "on")
    setting("0rtt", "off")
    setting("pseudo_ipv4", "off")
    setting(
        "security_header",
        {
            "strict_transport_security": {
                "enabled": True,
                "max_age": 31536000,
                "include_subdomains": True,
                "preload": False,
                "nosniff": True,
            }
        },
    )

    out = req(
        "PATCH",
        f"https://api.cloudflare.com/client/v4/zones/{ZONE}/argo/tiered_caching",
        {"value": "on"},
    )
    print("tiered_cache:", "ok" if out.get("success") else out)

    # Cache Rules: bypass admin/api JSON; cache /storage hard.
    ruleset = req(
        "GET",
        f"https://api.cloudflare.com/client/v4/zones/{ZONE}/rulesets/phases/http_request_cache_settings/entrypoint",
    )
    rules = [
        {
            "description": "Bypass Filament and API JSON",
            "expression": '(http.host eq "api.ghaith-art.com" and (starts_with(http.request.uri.path, "/admin") or starts_with(http.request.uri.path, "/livewire") or starts_with(http.request.uri.path, "/api/")))',
            "action": "set_cache_settings",
            "action_parameters": {"cache": False},
        },
        {
            "description": "Cache media and DZI tiles",
            "expression": '(http.host eq "api.ghaith-art.com" and starts_with(http.request.uri.path, "/storage/"))',
            "action": "set_cache_settings",
            "action_parameters": {
                "cache": True,
                "edge_ttl": {"mode": "respect_origin"},
                "browser_ttl": {"mode": "respect_origin"},
            },
        },
    ]
    if ruleset.get("success") and ruleset.get("result"):
        rid = ruleset["result"]["id"]
        out = req(
            "PUT",
            f"https://api.cloudflare.com/client/v4/zones/{ZONE}/rulesets/{rid}",
            {
                "name": ruleset["result"].get("name") or "default",
                "kind": "zone",
                "phase": "http_request_cache_settings",
                "rules": rules,
            },
        )
        print("cache_rules update:", "ok" if out.get("success") else out)
    else:
        out = req(
            "POST",
            f"https://api.cloudflare.com/client/v4/zones/{ZONE}/rulesets",
            {
                "name": "ghaith cache",
                "kind": "zone",
                "phase": "http_request_cache_settings",
                "rules": rules,
            },
        )
        print("cache_rules create:", "ok" if out.get("success") else out)

    # Pages project
    pages = req(
        "GET",
        f"https://api.cloudflare.com/client/v4/accounts/{ACCOUNT}/pages/projects/ghaith-art",
    )
    if pages.get("success"):
        print("pages project exists")
    else:
        out = req(
            "POST",
            f"https://api.cloudflare.com/client/v4/accounts/{ACCOUNT}/pages/projects",
            {"name": "ghaith-art", "production_branch": "main"},
        )
        print("pages create:", "ok" if out.get("success") else out)

    return 0


if __name__ == "__main__":
    sys.exit(main())
