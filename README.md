# ESMOS Healthcare Platform
Project to simulate live deployment of a meal kit ordering website on Microsoft Azure. 
Docker Compose stack for ESMOS — Odoo 17 + Moodle + Nginx on Azure Southeast Asia.

## Services
- **Odoo 17** — operations and meal planning (external)
- **Moodle** — compliance training (internal only, via `training.*` subdomain)
- **Nginx** — reverse proxy + SSL termination
- **PostgreSQL 15** — Odoo database
- **MariaDB 10.6** — Moodle database

## First-time setup

1. Ensure `odoo_backup.sql` is in the project root (included in the repo) — it is automatically imported by PostgreSQL on first startup
2. Set up SSL certificates (see [SSL setup](#ssl-setup) below)
3. Run:
   ```bash
   docker compose up -d --build
   ```
4. Wait a few minutes for both databases to initialize:
   - PostgreSQL will import `odoo_backup.sql` automatically
   - Moodle will install its DB and set up the training course

### Local development — hosts file

Add these entries to your hosts file (`C:\Windows\System32\drivers\etc\hosts` on Windows, `/etc/hosts` on Linux/Mac):

```
127.0.0.1 esmos.local
127.0.0.1 training.esmos.local
```

### SSL setup

The `nginx/certs/` directory is gitignored — you must generate or provide certs locally.

**For local development** — generate self-signed certs:

```bash
mkdir -p nginx/certs
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx/certs/privkey.pem \
  -out nginx/certs/fullchain.pem \
  -subj "/CN=localhost/O=ESMOS/C=SG"
```

Your browser will show a security warning — click through it (Advanced > Proceed).

**For production (Azure VM)** — use Let's Encrypt with your domain:

```bash
sudo apt install certbot
sudo certbot certonly --standalone -d yourdomain.com -d training.yourdomain.com
cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem nginx/certs/fullchain.pem
cp /etc/letsencrypt/live/yourdomain.com/privkey.pem nginx/certs/privkey.pem
```

Both files must exist at these paths before starting nginx:
- `nginx/certs/fullchain.pem` — the full certificate chain (public)
- `nginx/certs/privkey.pem` — the private key

## Access URLs

| Service | URL |
|---------|-----|
| Odoo (via Nginx) | `https://esmos.local` |
| Moodle (via Nginx) | `https://training.esmos.local` |
| Odoo (direct) | `http://localhost:8069` |

## Ports

| Service | Port |
|---------|------|
| Nginx HTTPS | 443 |
| Nginx HTTP (redirect) | 80 |
| Odoo (direct) | 8069 |

## Test accounts

### Moodle

| Username | Password | Role |
|----------|----------|------|
| admin | moodle2140 | Administrator |
| staff1 | Esmos2024! | Student (Alice Tan) |
| staff2 | Esmos2024! | Student (Bob Lim) |
| staff3 | Esmos2024! | Student (Carol Wong) |

### Odoo

| Username | Password |
|----------|----------|
| admin | admin |

## Fresh restart

To wipe all data and start clean:

```bash
docker compose down -v && docker compose up -d --build
```

---

## Load Testing & Horizontal Scaling

### Overview

Load testing was conducted using **Locust** to compare system performance before and after horizontal scaling. A dedicated Azure load test VM (`ESMOS-Milestone3-LB-VM`) was used to avoid resource contention with the production services.

**Hard requirement:** 50 concurrent Moodle users with zero failures.
**Target:** 100 concurrent Odoo users.

### Changes Made for Scaling

**`docker-compose.yml`:**
| Change | Reason |
|---|---|
| Removed `ports: "8069:8069"` from Odoo | Host port binding prevents multiple replicas |
| Removed `container_name: esmos-moodle` | Fixed container names prevent multiple replicas |
| Added `deploy.resources` limits (1 CPU, 1GB RAM) | Prevents replicas from starving each other |
| Increased PostgreSQL `max_connections` to 200 | Prevents connection pool exhaustion with 2 Odoo replicas |

**`nginx/conf.d/esmos.conf`:**
| Change | Reason |
|---|---|
| Added `resolver 127.0.0.11 valid=5s` | Forces Nginx to re-resolve Docker DNS per request |
| Variable-based `proxy_pass` | Enables round-robin across replicas |

### Running the Load Tests

Install Locust on the load test VM:
```bash
bash load-tests/setup-venv.sh
source load-tests/.venv/bin/activate
```

**Step 1 — Run baseline (single instance):**
```bash
bash load-tests/run_baseline.sh
```

**Step 2 — Scale up on prod VM:**
```bash
docker compose up -d --scale odoo=2 --scale moodle=2 --no-recreate
docker exec esm_milestone3-nginx-1 nginx -s reload
```

**Step 3 — Run scaled test:**
```bash
bash load-tests/run_scaled.sh
```

**Step 4 — Compare results:**
```bash
bash load-tests/compare.sh
```

**Step 5 — Scale back down after testing:**
```bash
docker compose up -d --scale odoo=1 --scale moodle=1 --no-recreate
```

### Results

| Metric | Odoo Baseline | Odoo Scaled | Change |
|---|---|---|---|
| Avg response time | 438ms | 373ms | -15% |
| p95 response time | 2600ms | 2200ms | -15% |
| Requests/sec | 41.1 | 42.5 | +3% |
| Failures | 220 | 222 | Stable |

| Metric | Moodle Baseline | Moodle Scaled | Change |
|---|---|---|---|
| Avg response time | 694ms | 612ms | -12% |
| p95 response time | 1400ms | 1300ms | -7% |
| Requests/sec | 18.97 | 19.49 | +3% |
| Failures | 0 | 0 | Met |

### Analysis

Horizontal scaling resulted in measurable improvements across both services. Odoo showed a 15% reduction in average and p95 response times under 100 concurrent users. Moodle met the hard requirement of 50 concurrent users with zero failures.

The remaining Odoo failures under load are attributed to the single PostgreSQL instance becoming the bottleneck — adding more Odoo replicas distributes application-layer load but all replicas share the same database. The recommended next step would be implementing PostgreSQL read replicas with PgBouncer connection pooling.
