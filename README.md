# DNS Tools

A minimal, fast DNS tooling web app. Vue 3 frontend, PHP backend, all powered by Linux CLI tools (`dig`, `whois`, `curl`, `host`).

**Live:** [dns.salmaster.dev](https://dns.salmaster.dev)

## Features

- **Subnet Calculator** — Split CIDR blocks or convert IP ranges to CIDR notation
- **WHOIS Lookup** — Domain and IP WHOIS with smart parsing (RIPE, ARIN, domain registries)
- **Dig Lookup** — DNS record queries across all types, export to zone file or CSV
- **DNS Propagation** — Compare DNS results across 8 public resolvers
- **Reverse DNS** — PTR lookup for any IP address
- **HTTP Headers** — Inspect response headers, follow redirects, security header audit

## Quick Deploy

```bash
git clone git@github.com:salagent44/saldns.git
cd saldns
docker compose up --build -d
```

The app will be running with the frontend on the `dns-frontend` container (port 80) and the API on `dns-api` (internal).

## Production Deploy (dns.salmaster.dev)

From the local repo, deploy everything in one command:

```bash
scp -i ~/.ssh/id_ed25519_linode -r api/ frontend/ docker-compose.yml Dockerfile.api \
  root@dns.salmaster.dev:/root/dns-tools/ && \
ssh -i ~/.ssh/id_ed25519_linode root@dns.salmaster.dev \
  "cd /root/dns-tools && docker compose up --build -d"
```

## Stack

- **Frontend:** Vue 3, Vite, Tailwind CSS (light/dark theme)
- **Backend:** PHP 8.3 + Apache (no framework, CLI tools only)
- **Infra:** Docker Compose, Caddy reverse proxy with auto-TLS
- **DNS tools used:** `dig`, `whois`, `host`, `curl`

## Network Setup

The `dns-frontend` container joins the `salgtd_default` external Docker network so Caddy (from a separate stack) can reverse proxy to it with automatic Let's Encrypt TLS. The API container is internal-only, accessed by nginx in the frontend container via `/api/` proxy pass.
