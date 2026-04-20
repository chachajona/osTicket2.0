# osTicket 2.0 Phase 1 Task 5: Routing Architecture

## Complete Architecture Diagram

```
┌────────────────────────────────────────────────────────────────┐
│                        CLIENT                                  │
│                   (Browser / API Client)                       │
└────────────────────┬─────────────────────────────────────────┘
                     │
                     │ HTTPS Request
                     │ (Port 443)
                     ▼
        ┌────────────────────────────┐
        │    Reverse Proxy Server    │
        │   (Nginx or Apache)        │
        │   - SSL Termination        │
        │   - Rate Limiting          │
        │   - Header Manipulation    │
        │   - Static Asset Serving   │
        └────────────┬───────────────┘
                     │
        ┌────────────┼────────────────────┬──────────────────┐
        │            │                    │                  │
        │ Match: /scp/*, /api/*   │ Match: /static/*  │ Match: / (default)
        │            │                    │                  │
        ▼            ▼                    ▼                  ▼
    ┌──────────┐  ┌──────────┐    ┌─────────────┐   ┌──────────────┐
    │ Laravel  │  │ Laravel  │    │   Serve     │   │   Legacy     │
    │   SCP    │  │   API    │    │   Cached    │   │  osTicket    │
    │  (9000)  │  │  (9000)  │    │   Assets    │   │   (8080)     │
    └──────────┘  └──────────┘    └─────────────┘   └──────────────┘
         │            │
         └────┬───────┘
              │
         ┌────▼───────────┐
         │   PHP-FPM      │
         │   Pool: 9000   │
         │  (Laravel App) │
         └────────────────┘
```

## URL Routing Matrix

| URL Pattern | HTTP Method | Routes To | Handler | Notes |
|------------|-------------|-----------|---------|-------|
| `/scp/*` | GET/POST/PUT/DELETE | Laravel | PHP-FPM 9000 | Staff Control Panel |
| `/api/*` | GET/POST/PUT/DELETE | Laravel | PHP-FPM 9000 | API for Task 13 (compatibility) |
| `/api/v2/*` | GET/POST/PUT/DELETE | Laravel | PHP-FPM 9000 | v2 API endpoints |
| `/css/*` | GET | Cached | Nginx/Apache | Static assets (1y cache) |
| `/js/*` | GET | Cached | Nginx/Apache | Static assets (1y cache) |
| `/images/*` | GET | Cached | Nginx/Apache | Static assets (1y cache) |
| `/fonts/*` | GET | Cached | Nginx/Apache | Static assets (1y cache) |
| `/pages/*` | GET | Legacy osTicket | 8080 | Site pages (Phase 2 target) |
| `/kb/*` | GET | Legacy osTicket | 8080 | Knowledge base (Phase 2 target) |
| `/scp/apps/*` | GET/POST | Legacy osTicket | 8080 | Legacy staff apps (fallback) |
| `/*.php` | GET/POST | Blocked/Denied | - | Direct PHP execution prevented |
| `/.htaccess` | GET | 404 | - | Config files hidden |
| `/` | GET | Legacy osTicket | 8080 | Main portal |

## Rate Limiting Configuration

### Nginx
```
API Endpoints (/api/*)
├─ Limit: 10 requests/second
├─ Burst: 20 requests allowed
└─ Exceeds: Return 429 Too Many Requests

General Routes (/scp/*, /)
├─ Limit: 30 requests/second
├─ Burst: 50 requests allowed
└─ Exceeds: Return 429 Too Many Requests
```

### Apache
```
API Endpoints (/api/*)
├─ Limit: ~100 kB/s (≈10 req/sec)
└─ Exceeds: Return 503 Service Unavailable

General Routes (/*) 
├─ Limit: ~500 kB/s (≈30 req/sec)
└─ Exceeds: Return 503 Service Unavailable
```

## Security Headers Applied

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
    → Force HTTPS for all future connections (1 year)

X-Content-Type-Options: nosniff
    → Prevent MIME type sniffing attacks

X-Frame-Options: SAMEORIGIN
    → Prevent clickjacking, allow same-origin framing

X-XSS-Protection: 1; mode=block
    → Enable XSS filter in older browsers

Referrer-Policy: strict-origin-when-cross-origin
    → Control referrer information leakage
```

## Cache Strategy

### Static Assets (1 Year Cache)
- Extensions: `.css`, `.js`, `.jpg`, `.jpeg`, `.png`, `.gif`, `.svg`, `.woff`, `.woff2`, `.ttf`, `.eot`
- Cache-Control: `public, immutable`
- Expires: 1 year from request
- Gzip compressed: Yes
- Access log disabled: Yes

### Dynamic Content (1 Day Cache)
- Content-Type: `text/html`, `application/json`
- Cache-Control: `public` (with revalidation)
- Expires: 1 day from request
- Gzip compressed: Yes
- Access log enabled: Yes

### No Cache
- Session cookies
- Authorization headers
- Dynamically generated content

## SSL/TLS Configuration

### Protocol & Cipher Suite
```
Supported Protocols: TLSv1.2, TLSv1.3
Cipher Suite: HIGH:!aNULL:!MD5
Session Cache: Shared memory (10m timeout)
```

### Certificate Paths (Configure Before Deployment)
```
Nginx/Apache:
├─ /etc/ssl/certs/osticket.crt          (public certificate)
└─ /etc/ssl/private/osticket.key        (private key)

Let's Encrypt:
├─ /etc/letsencrypt/live/domain/fullchain.pem
└─ /etc/letsencrypt/live/domain/privkey.pem
```

## PHP-FPM Configuration

### Laravel Pool (osticket-laravel)
```
Listen: 127.0.0.1:9000 (TCP socket)
Process Manager: dynamic
├─ Max Children: 20
├─ Start Servers: 5
├─ Min Spare: 2
├─ Max Spare: 10
└─ Max Requests: 500 (restart after 500 reqs)

Slow Request Timeout: 10 seconds
Error Log: /var/log/php-fpm/osticket-laravel.error.log
Slow Log: /var/log/php-fpm/osticket-laravel.slow.log
```

### Environment Variables
```
APP_ENV=production
APP_DEBUG=false
CACHE_DRIVER=redis        (configured in Laravel .env)
SESSION_DRIVER=redis      (configured in Laravel .env)
QUEUE_CONNECTION=redis    (configured in Laravel .env)
```

## File Structure

```
osTicket2.0/
├── deployment/
│   ├── README.md                  ← Full deployment guide
│   ├── nginx.conf                 ← Nginx reverse proxy config
│   ├── apache.conf                ← Apache reverse proxy config
│   ├── php-fpm.conf               ← PHP-FPM pool configuration
│   └── verify-routing.sh          ← Automated verification script
├── public/
│   ├── index.php                  ← Laravel entry point
│   ├── .htaccess                  ← Apache fallback routing
│   ├── css/                       ← Compiled CSS (npm run build)
│   ├── js/                        ← Compiled JS (npm run build)
│   └── images/                    ← Static images
├── osticket/
│   ├── .htaccess                  ← Legacy routing rules
│   ├── index.php                  ← Legacy entry point
│   └── api/
│       └── .htaccess              ← Legacy API routing
└── routes/
    ├── api.php                    ← Laravel API routes
    ├── web.php                    ← Laravel SCP routes
    └── console.php                ← Artisan commands
```

## Deployment Checklist

- [ ] **Pre-Deployment**
  - [ ] Install Nginx (or Apache)
  - [ ] Install PHP-FPM 8.2+
  - [ ] Obtain SSL certificate (Let's Encrypt or self-signed)
  - [ ] Configure domain name (DNS)
  
- [ ] **Configuration**
  - [ ] Copy nginx.conf (or apache.conf) to /etc/nginx/sites-available/
  - [ ] Copy php-fpm.conf to /etc/php/8.2/fpm/pool.d/
  - [ ] Update domain names in config files
  - [ ] Update certificate paths
  - [ ] Update upstream server addresses if needed
  
- [ ] **Verification**
  - [ ] Test syntax: `nginx -t` or `apachectl configtest`
  - [ ] Reload services: `systemctl reload nginx` + `systemctl reload php8.2-fpm`
  - [ ] Run verify-routing.sh to test all routes
  - [ ] Check logs for errors
  
- [ ] **Monitoring**
  - [ ] Tail access logs: `tail -f /var/log/nginx/osticket_access.log`
  - [ ] Tail error logs: `tail -f /var/log/nginx/osticket_error.log`
  - [ ] Monitor PHP-FPM: `watch -n 1 'ps aux | grep php-fpm'`
  - [ ] Check port bindings: `netstat -tlnp | grep -E ':80|:443|:9000|:8080'`

## Troubleshooting Quick Reference

| Problem | Solution |
|---------|----------|
| 502 Bad Gateway | Check PHP-FPM status: `systemctl status php8.2-fpm` |
| SSL certificate error | Verify paths in config, check cert expiration: `openssl x509 -in cert.pem -text -noout` |
| Routes return 404 | Check reverse proxy logs for upstream errors |
| Slow responses | Check PHP-FPM slow log, increase max_children if pool saturated |
| Static assets 403 | Verify file permissions, check directory listing disabled |
| Rate limiting too strict | Adjust limits in nginx.conf or apache.conf |
| Gzip not working | Verify `Accept-Encoding: gzip` in request headers |

## Future Migration (Phases 2-3)

```
Phase 1 (Current)
/scp/* → Laravel (SCP)
/api/* → Laravel (API)
/* → Legacy osTicket

Phase 2 (KB & Pages)
/scp/* → Laravel
/api/* → Laravel
/kb/* → Laravel         ← NEW
/pages/* → Laravel      ← NEW
/* → Legacy osTicket

Phase 3 (Complete)
/* → Laravel           ← All traffic
Legacy osTicket → Decommissioned
```

## Performance Metrics

### Expected Baseline
- Static assets: ~1ms (cached)
- Laravel SCP routes: ~50-200ms (PHP-FPM processing)
- API requests: ~100-300ms (database queries)
- Legacy osTicket: ~200-500ms (legacy PHP code)

### Optimization Applied
- Gzip compression: ~60% size reduction for text
- Browser caching: 1 year for versioned assets
- Reverse proxy caching: Potential 99% cache hit for static assets
- PHP-FPM process pooling: Faster than CGI, lower memory than persistent connections

## Next Steps (After Task 5)

1. **Task 1.1**: Complete Laravel authentication system
2. **Task 4**: Finalize auth bridge middleware (legacy session interop)
3. **Task 6**: Implement API endpoints for dashboard/metrics
4. **Task 13**: Create API compatibility layer for legacy clients
5. **Phase 2**: Migrate /kb/* and /pages/* to Laravel
