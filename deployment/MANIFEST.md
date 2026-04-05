# Deployment Directory Manifest

## Phase 1 Task 5: Reverse Proxy + Routing Setup — COMPLETE ✓

This directory contains production-ready reverse proxy configurations and deployment documentation for osTicket 2.0's hybrid Laravel/Legacy architecture.

---

## File Inventory

### 1. README.md (500 lines)
**Comprehensive Deployment Guide**

Contents:
- Overview with routing strategy diagram
- Prerequisites checklist
- Installation instructions for Nginx (recommended) and Apache
- PHP-FPM configuration and verification
- SSL/TLS setup (Let's Encrypt and self-signed)
- Verification and testing procedures (with curl examples)
- Monitoring and troubleshooting guide
- Common issues and solutions with remediation steps
- Performance tuning recommendations
- Gradual migration path for Phase 2-3
- Security hardening checklist
- Quick reference command guide

**Status**: Ready for deployment
**Target Users**: DevOps engineers, system administrators
**Audience**: Production deployment

---

### 2. nginx.conf (236 lines)
**Primary Reverse Proxy Configuration (Recommended)**

Features:
- Upstream definitions for Laravel (9000) and legacy osTicket (8080)
- Rate limiting zones (10 req/s API, 30 req/s general)
- SSL/TLS configuration (TLS 1.2+, modern ciphers)
- HTTP → HTTPS redirect
- Routing rules:
  - `/scp/*` → Laravel SCP (Staff Control Panel)
  - `/api/*` → Laravel API (compatibility layer)
  - `/*` → Legacy osTicket
- Static asset serving with 1-year cache headers
- Gzip compression enabled
- Security headers (HSTS, X-Content-Type-Options, etc.)
- Health check endpoint (`/health`)
- PHP-FPM FastCGI configuration
- Logging and error handling
- Performance optimization (buffering, timeouts)

**Installation**: `/etc/nginx/sites-available/osticket`
**Testing**: `sudo nginx -t`
**Reload**: `sudo systemctl reload nginx`

---

### 3. apache.conf (244 lines)
**Alternative Apache2 Reverse Proxy Configuration**

Features:
- Same functionality as Nginx but using Apache2 modules
- VirtualHost blocks for HTTP (redirect) and HTTPS
- Proxy configuration using mod_proxy_http
- Rate limiting via mod_ratelimit
- SSL/TLS with strong cipher suite
- Same routing strategy as Nginx
- Headers and expires modules for caching control
- Gzip compression via mod_deflate
- Security headers
- Process monitoring configuration

**Installation**: `/etc/apache2/sites-available/osticket.conf`
**Testing**: `sudo apachectl configtest`
**Reload**: `sudo systemctl reload apache2`
**Required modules**: proxy, proxy_http, rewrite, ssl, headers, deflate, expires, ratelimit

---

### 4. php-fpm.conf (93 lines)
**PHP-FPM Pool Configuration**

Contains:
- Laravel pool (osticket-laravel):
  - Listen: 127.0.0.1:9000
  - Process manager: dynamic (2-10 spare servers, max 20 children)
  - Slow request logging (10s threshold)
  - Error and slow log paths
  - Environment variables for production
- Optional legacy pool (commented out for reference)
- Configuration notes and verification steps

**Installation**: `/etc/php/8.2/fpm/pool.d/osticket.conf` (adjust PHP version as needed)
**Testing**: `sudo php-fpm -t`
**Reload**: `sudo systemctl reload php8.2-fpm`

---

### 5. verify-routing.sh (204 lines, executable)
**Automated Routing Verification Script**

Test categories:
- Security & protocol tests (HTTP redirect, HSTS)
- Legacy osTicket routes (/, /pages/)
- Laravel SCP routes (/scp/*, auth-aware)
- Laravel API routes (/api/*, /api/v2/*)
- Static asset caching headers
- Gzip compression detection
- Health endpoint check (Nginx)
- Rate limiting information

**Usage**: 
```bash
bash deployment/verify-routing.sh [domain] [protocol]
bash deployment/verify-routing.sh 127.0.0.1 https
bash deployment/verify-routing.sh example.com https
```

**Output**: Color-coded pass/fail summary with troubleshooting guide
**Testing**: 10+ automated tests, ~1 minute runtime

---

### 6. ARCHITECTURE.md (279 lines)
**Architecture Reference Documentation**

Contents:
- Complete architecture diagram (ASCII)
- URL routing matrix (all routes documented)
- Rate limiting configuration details
- Security headers explanation
- Cache strategy (static vs. dynamic)
- SSL/TLS configuration details
- PHP-FPM configuration reference
- File structure overview
- Deployment checklist
- Troubleshooting quick reference table
- Future migration phases (Phase 2-3)
- Performance metrics and baselines
- Next steps for Phase 1.1+

**Status**: Reference documentation
**Target Users**: All team members
**Audience**: Architecture review and onboarding

---

## Deployment Requirements

### System Requirements
- Linux with systemd (Ubuntu 20.04+, Debian 11+, CentOS 8+)
- Nginx 1.18+ OR Apache 2.4+
- PHP 8.2+ with PHP-FPM
- 2+ CPU cores, 2GB+ RAM

### Network Requirements
- Domain name with DNS configured
- SSL certificate (Let's Encrypt recommended)
- Ports 80 (HTTP) and 443 (HTTPS) open
- Internal port 9000 available for PHP-FPM

### Credentials & Access
- Root or sudo access to server
- Write access to /etc/nginx/ or /etc/apache2/
- Write access to /var/www/ (Laravel installation)

---

## Quick Start

### For Nginx (Recommended)

```bash
# 1. Copy configuration
sudo cp deployment/nginx.conf /etc/nginx/sites-available/osticket
sudo ln -s /etc/nginx/sites-available/osticket /etc/nginx/sites-enabled/

# 2. Edit configuration (update domain, paths, SSL cert)
sudo nano /etc/nginx/sites-available/osticket

# 3. Test and reload
sudo nginx -t
sudo systemctl reload nginx

# 4. Verify
bash deployment/verify-routing.sh your-domain.com https
```

### For Apache

```bash
# 1. Copy configuration
sudo cp deployment/apache.conf /etc/apache2/sites-available/osticket.conf
sudo a2ensite osticket

# 2. Enable required modules
sudo a2enmod proxy proxy_http rewrite ssl headers deflate expires ratelimit

# 3. Edit configuration
sudo nano /etc/apache2/sites-available/osticket.conf

# 4. Test and reload
sudo apachectl configtest
sudo systemctl reload apache2

# 5. Verify
bash deployment/verify-routing.sh your-domain.com https
```

---

## Verification Checklist

After deployment, verify:

- [ ] HTTP → HTTPS redirect works
- [ ] `/scp/` routes to Laravel (shows SCP interface or login page)
- [ ] `/api/tickets/` routes to Laravel (returns JSON or auth error)
- [ ] `/pages/` routes to legacy osTicket (shows site pages)
- [ ] `/` routes to legacy osTicket (shows public portal)
- [ ] Static assets have Cache-Control headers
- [ ] Gzip compression is active
- [ ] SSL certificate is valid
- [ ] Logs show no errors (check /var/log/nginx/ or /var/log/apache2/)
- [ ] Health endpoint returns 200 (Nginx: `/health`)

---

## Documentation Map

| Document | Purpose | Audience |
|----------|---------|----------|
| README.md | Step-by-step deployment guide | DevOps, Admins |
| ARCHITECTURE.md | Reference and troubleshooting | All team members |
| nginx.conf | Production Nginx config | Server operators |
| apache.conf | Alternative Apache config | Server operators |
| php-fpm.conf | PHP-FPM pool setup | Server operators |
| verify-routing.sh | Automated testing | QA, DevOps |
| MANIFEST.md (this file) | What was delivered | All |

---

## Key Features Delivered

✅ **Routing Strategy**
- `/scp/*` routes to Laravel SCP
- `/api/*` routes to Laravel API (Task 13 compatibility)
- Everything else routes to legacy osTicket
- Static assets served with optimal caching

✅ **Security**
- SSL/TLS termination at reverse proxy
- HTTP → HTTPS redirect
- Security headers (HSTS, X-Frame-Options, etc.)
- Rate limiting (10 req/s for APIs, 30 req/s general)
- Direct PHP execution prevented

✅ **Performance**
- Gzip compression enabled
- 1-year cache for static assets
- Static assets immutable flag
- FastCGI caching for PHP-FPM
- Access logs disabled for static assets

✅ **Maintainability**
- Clear, well-documented configurations
- Both Nginx and Apache options
- Comprehensive README with examples
- Automated verification script
- Architecture documentation

✅ **Operational Readiness**
- Deployment checklist included
- Troubleshooting guide provided
- Quick reference commands documented
- Performance tuning recommendations
- Migration path for future phases

---

## Migration Path (Future Phases)

| Phase | Status | Routes | Notes |
|-------|--------|--------|-------|
| 1 (Current) | ✅ Complete | `/scp/*`, `/api/*` → Laravel | SCP + API only |
| 2 | 📅 Planned | `/kb/*`, `/pages/*` → Laravel | KB and pages |
| 3 | 📅 Planned | `/*` → Laravel | Complete migration |

To migrate routes in Phase 2:
1. Update location blocks in nginx.conf / ProxyPass in apache.conf
2. Test configuration syntax
3. Reload reverse proxy
4. Run verify-routing.sh to validate

---

## Support & Troubleshooting

See `deployment/README.md` for:
- Common issues and solutions
- Log file locations and how to review them
- Performance tuning recommendations
- Monitoring and alerting setup
- Certificate renewal procedures
- Gradual migration procedures

---

## File Sizes & Line Counts

```
ARCHITECTURE.md       279 lines   10 KB
README.md            500 lines   12 KB
apache.conf          244 lines  8.7 KB
nginx.conf           236 lines  7.3 KB
php-fpm.conf          93 lines  2.5 KB
verify-routing.sh    204 lines  6.6 KB
───────────────────────────────────────
TOTAL              1,556 lines   47 KB
```

---

## Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-04-05 | Sisyphus | Initial deployment configs for Phase 1 Task 5 |

---

## Next Steps After Deployment

1. **Monitor first 24 hours** — Check logs for unexpected errors
2. **Test all routes** — Run verify-routing.sh daily for first week
3. **Tune rate limiting** — Adjust if legitimate traffic is throttled
4. **Set up monitoring** — Configure alerts for 502 errors, high response times
5. **Plan Phase 2** — Schedule migration of /kb/* and /pages/* routes
6. **Document your domain** — Update team wiki with deployed domain/paths
7. **Enable HTTPS caching** — Configure CDN if applicable (optional)

---

**Deployment Status**: ✅ Ready for Production
**Last Updated**: 2026-04-05
**Task**: Phase 1 Task 5 — Reverse Proxy + Routing Setup
