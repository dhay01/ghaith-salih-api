#!/usr/bin/env python3
"""Create the R2 bucket, public domain, CORS, and a cache rule. Token from env."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request

ACCOUNT = os.environ["CLOUDFLARE_ACCOUNT_ID"]
TOKEN = os.environ["CLOUDFLARE_API_TOKEN"]
ZONE = os.environ.get("CLOUDFLARE_ZONE_ID", "9939c9d0c91bfe8d5a317e9e7e99001a")
BUCKET = os.environ.get("R2_BUCKET", "ghaith-art-media")
DOMAIN = os.environ.get("R2_PUBLIC_HOST", "images.ghaith-art.com")
API = f"https://api.cloudflare.com/client/v4/accounts/{ACCOUNT}"


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
        err = e.read().decode()[:2000]
        return {"success": False, "http": e.code, "body": err}


def main() -> int:
    verify = req(
        "GET",
        f"https://api.cloudflare.com/client/v4/accounts/{ACCOUNT}/tokens/verify",
    )
    if not verify.get("success"):
        verify = req("GET", "https://api.cloudflare.com/client/v4/user/tokens/verify")
    if not verify.get("success"):
        print("token verify failed", verify.get("http"), (verify.get("body") or "")[:300])
        return 1
    print("token: ok")

    created = req(
        "POST",
        f"{API}/r2/buckets",
        {"name": BUCKET, "locationHint": "weur"},
    )
    if created.get("success"):
        print(f"bucket {BUCKET}: created")
    elif created.get("http") == 409 or "already exists" in str(created.get("body", "")).lower():
        print(f"bucket {BUCKET}: exists")
    else:
        listed = req("GET", f"{API}/r2/buckets")
        names = [b.get("name") for b in (listed.get("result") or {}).get("buckets") or []]
        if not names and isinstance(listed.get("result"), list):
            names = [b.get("name") for b in listed["result"]]
        if BUCKET in names:
            print(f"bucket {BUCKET}: exists")
        else:
            print("bucket create failed", created)
            return 1

    domain = req(
        "POST",
        f"{API}/r2/buckets/{BUCKET}/domains/custom",
        {
            "domain": DOMAIN,
            "enabled": True,
            "zoneId": ZONE,
            "minTLS": "1.2",
        },
    )
    if domain.get("success"):
        print(f"domain {DOMAIN}: attached")
    elif "already" in str(domain).lower() or domain.get("http") in (409, 400):
        print(f"domain {DOMAIN}: {domain.get('body') or 'exists'}")
    else:
        print("domain attach failed", domain)
        return 1

    cors = req(
        "PUT",
        f"{API}/r2/buckets/{BUCKET}/cors",
        {
            "rules": [
                {
                    "id": "public-read",
                    "allowed": {
                        "methods": ["GET", "HEAD"],
                        "origins": ["*"],
                        "headers": ["Range", "Origin"],
                    },
                    "exposeHeaders": ["ETag", "Content-Length", "Content-Type"],
                    "maxAgeSeconds": 86400,
                }
            ]
        },
    )
    print("cors:", "ok" if cors.get("success") else cors)

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
            "description": "Cache media and DZI tiles on origin",
            "expression": '(http.host eq "api.ghaith-art.com" and starts_with(http.request.uri.path, "/storage/"))',
            "action": "set_cache_settings",
            "action_parameters": {
                "cache": True,
                "edge_ttl": {"mode": "respect_origin"},
                "browser_ttl": {"mode": "respect_origin"},
            },
        },
        {
            "description": "Cache hashed Pages assets",
            "expression": '(http.host in {"ghaith-art.com" "www.ghaith-art.com"} and starts_with(http.request.uri.path, "/assets/"))',
            "action": "set_cache_settings",
            "action_parameters": {
                "cache": True,
                "edge_ttl": {"mode": "override_origin", "default": 2678400},
                "browser_ttl": {"mode": "override_origin", "default": 31536000},
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
        print("cache_rules:", "ok" if out.get("success") else out)
    else:
        print("cache_rules: no entrypoint", ruleset)

    return 0 if cors.get("success") else 1


if __name__ == "__main__":
    sys.exit(main())
