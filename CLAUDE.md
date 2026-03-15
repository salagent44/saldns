# DNS Tooling App

## Project Overview
Minimal DNS tooling web app with Vue 3 frontend and PHP backend.

## Stack
- **Frontend**: Vue 3 + Vite, Tailwind CSS, dark theme
- **Backend**: Plain PHP API (no framework), Apache in Docker
- **Reverse Proxy**: Caddy (managed in salgtd stack on server)
- **Deployment**: Docker Compose, deployed to dns.salmaster.dev

## Features
1. **Subnet Calculator** — Paste a CIDR block (e.g. 10.0.0.0/16), pick a target prefix (/24, /23, etc.), get all child ranges listed one per line. Shows CIDR, first IP, last IP, host count.
2. **WHOIS Lookup** — Domain + IP support. Easy-to-read formatted output with parsed key fields.
3. **Dig Lookup** — Support all record types (A, AAAA, MX, NS, TXT, SOA, PTR, SRV, CAA). Optional DNS server override. Easy-to-read formatted output.

## Deployment
- **Production**: dns.salmaster.dev (Linode VPS at 172.232.24.49)
- **SSH**: `ssh -i ~/.ssh/id_ed25519_linode root@dns.salmaster.dev`
- **Project path on server**: `/root/dns-tools/`
- **Caddy config on server**: `/opt/salgtd/docker/Caddyfile` (add `dns.salmaster.dev` block)
- Containers join `salgtd_default` Docker network so Caddy can reverse proxy to `dns-frontend`
- Caddy handles TLS (auto Let's Encrypt)

## Commands
- `docker compose up --build -d` — build and run
- `docker compose down` — stop
- Deploy: `scp -i ~/.ssh/id_ed25519_linode -r api/ frontend/ docker-compose.yml Dockerfile.api root@dns.salmaster.dev:/root/dns-tools/`
- Then on server: `cd /root/dns-tools && docker compose up --build -d`
- Reload Caddy: `docker exec salgtd-caddy-1 caddy reload --config /etc/caddy/Caddyfile`
