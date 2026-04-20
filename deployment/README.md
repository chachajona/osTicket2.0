# osTicket 2.0 Reverse Proxy & Routing Deployment Guide

## Overview

This document provides deployment instructions for configuring a reverse proxy (Nginx or Apache) to route requests between the **Laravel SCP** (new Staff Control Panel) and **legacy osTicket**.

### Routing Strategy

```
┌─────────────────────────────────────────────────────────┐
│  Client Request (HTTPS)                                 │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────────┐
        │  Reverse Proxy (SSL Term.)   │
        │  Nginx or Apache             │
        └──────────────┬───────────────┘
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
    /scp/*         /api/*        /everything
    routes to      routes to      else routes
    Laravel        Laravel        to Legacy
    (9000)         (9000)         osTicket
                                  (8080)
```

---

## Prerequisites

1. **Two separate PHP environments**:
   - Laravel application (PHP-FPM on port 9000)
   - Legacy osTicket (Apache/PHP on port 8080 internally, or separate PHP-FPM pool)

2. **Reverse proxy installed**:
   - Nginx (recommended) or Apache2

3. **SSL certificates**:
   - Self-signed or Let's Encrypt (see below)

4. **System access**: Root/sudo privileges

---

## Installation & Configuration

### Option A: Nginx Setup (Recommended)

#### 1. Install Nginx

```bash
sudo apt update
sudo apt install nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

#### 2. Copy Nginx Configuration

```bash
# Backup existing config
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup

# Copy our configuration
sudo cp deployment/nginx.conf /etc/nginx/sites-available/osticket
sudo ln -s /etc/nginx/sites-available/osticket /etc/nginx/sites-enabled/
```

#### 3. Update Server Names & Paths

Edit `/etc/nginx/sites-available/osticket`:

```bash
sudo nano /etc/nginx/sites-available/osticket
```

Replace:
- `server_name _;` → `server_name your-domain.com www.your-domain.com;`
- `/var/www/osticket2.0` → Your actual Laravel installation path
- `/var/www/osticket` → Your actual legacy osTicket path
- SSL certificate paths → Your actual certificate locations

#### 4. Test & Reload

```bash
# Test configuration syntax
sudo nginx -t

# If no errors, reload Nginx
sudo systemctl reload nginx

# View logs
sudo tail -f /var/log/nginx/osticket_error.log
sudo tail -f /var/log/nginx/osticket_access.log
```

---

### Option B: Apache Setup

#### 1. Install Apache

```bash
sudo apt update
sudo apt install apache2 libapache2-mod-proxy-http
sudo a2enmod proxy proxy_http rewrite ssl headers deflate expires ratelimit
sudo systemctl start apache2
sudo systemctl enable apache2
```

#### 2. Copy Apache Configuration

```bash
# Backup existing config
sudo cp /etc/apache2/apache2.conf /etc/apache2/apache2.conf.backup

# Copy our configuration
sudo cp deployment/apache.conf /etc/apache2/sites-available/osticket.conf
sudo a2ensite osticket
sudo a2dissite 000-default  # Disable default site if desired
```

#### 3. Update Server Names & Paths

Edit `/etc/apache2/sites-available/osticket.conf`:

```bash
sudo nano /etc/apache2/sites-available/osticket.conf
```

Replace:
- `ServerName osticket.example.com` → Your actual domain
- `DocumentRoot /var/www/osticket2.0` → Your actual paths
- SSL certificate paths → Your actual certificate locations

#### 4. Test & Reload

```bash
# Test configuration
sudo apachectl configtest

# Should output: Syntax OK

# If OK, reload Apache
sudo systemctl reload apache2

# View logs
sudo tail -f /var/log/apache2/osticket_error.log
sudo tail -f /var/log/apache2/osticket_access.log
```

---

## PHP-FPM Configuration

### 1. Create PHP-FPM Pool

```bash
# Copy FPM configuration
sudo cp deployment/php-fpm.conf /etc/php/8.2/fpm/pool.d/osticket.conf
```

### 2. Verify PHP-FPM

```bash
# Test FPM configuration
sudo php-fpm -t

# Reload FPM
sudo systemctl reload php8.2-fpm

# Check status
sudo systemctl status php8.2-fpm

# View running processes
ps aux | grep php-fpm
```

### 3. Create Log Directory

```bash
sudo mkdir -p /var/log/php-fpm
sudo chown www-data:www-data /var/log/php-fpm
sudo chmod 755 /var/log/php-fpm
```

---

## SSL/TLS Configuration

### Option 1: Let's Encrypt (Recommended for Production)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx  # For Nginx
# or
sudo apt install certbot python3-certbot-apache  # For Apache

# Obtain certificate
sudo certbot certonly --standalone -d your-domain.com -d www.your-domain.com

# Certificates will be saved to:
# /etc/letsencrypt/live/your-domain.com/fullchain.pem
# /etc/letsencrypt/live/your-domain.com/privkey.pem

# Update nginx.conf or apache.conf with these paths:
# ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
# ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

# Auto-renew (certbot does this automatically)
sudo systemctl enable certbot.timer
```

### Option 2: Self-Signed Certificate (Development Only)

```bash
# Generate self-signed certificate
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/osticket.key \
  -out /etc/ssl/certs/osticket.crt

# Update permissions
sudo chmod 600 /etc/ssl/private/osticket.key
sudo chmod 644 /etc/ssl/certs/osticket.crt
```

---

## Verification & Testing

### 1. Test Legacy osTicket Routes

```bash
# Test main page (should reach legacy)
curl -H "Host: your-domain.com" https://your-domain.com/
curl -k https://127.0.0.1/  # With self-signed cert

# Test Pages API (legacy)
curl -k https://127.0.0.1/pages/

# Test Knowledge Base (legacy)
curl -k https://127.0.0.1/kb/
```

### 2. Test Laravel SCP Routes

```bash
# Test SCP routes (should reach Laravel)
curl -k https://127.0.0.1/scp/
curl -k https://127.0.0.1/scp/dashboard/

# Test with headers to see which backend handles it
curl -v -k https://127.0.0.1/scp/ 2>&1 | grep -E "X-|Server|backend"
```

### 3. Test API Routes

```bash
# Test API (should reach Laravel)
curl -k https://127.0.0.1/api/tickets/
curl -k https://127.0.0.1/api/v2/tickets/
```

### 4. Test Static Assets

```bash
# Test Laravel assets
curl -I -k https://127.0.0.1/css/app.css
curl -I -k https://127.0.0.1/js/app.js

# Check response headers
# Should show: Cache-Control: public, immutable
# Expires header should be 1 year in future
```

### 5. Test Rate Limiting (Nginx)

```bash
# For Nginx, run rapid requests to /api endpoint
for i in {1..50}; do curl -s -k https://127.0.0.1/api/tickets/ > /dev/null; done

# Should see some 429 (Too Many Requests) responses
```

### 6. Check Health Endpoint

```bash
# Nginx provides /health endpoint
curl -k https://127.0.0.1/health
# Output: healthy
```

---

## Monitoring & Troubleshooting

### View Real-Time Logs

```bash
# Nginx
sudo tail -f /var/log/nginx/osticket_access.log
sudo tail -f /var/log/nginx/osticket_error.log

# Apache
sudo tail -f /var/log/apache2/osticket_access.log
sudo tail -f /var/log/apache2/osticket_error.log

# PHP-FPM
sudo tail -f /var/log/php-fpm/osticket-laravel.error.log
sudo tail -f /var/log/php-fpm/osticket-laravel.slow.log
```

### Common Issues

#### 1. "502 Bad Gateway" or "Proxy Error"

**Cause**: Upstream PHP-FPM not responding

**Solution**:
```bash
# Check if PHP-FPM is running
sudo systemctl status php8.2-fpm

# Check if port 9000 is listening
sudo netstat -tlnp | grep 9000

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

#### 2. "403 Forbidden" on static assets

**Cause**: File permissions or directory listing disabled

**Solution**:
```bash
# Check permissions
ls -la /var/www/osticket2.0/public/

# Fix permissions (if needed)
sudo chown -R www-data:www-data /var/www/osticket2.0/
sudo chmod -R 755 /var/www/osticket2.0/
```

#### 3. SSL Certificate Errors

**Solution**:
```bash
# Test certificate
openssl s_client -connect 127.0.0.1:443

# Check certificate expiration
openssl x509 -in /etc/ssl/certs/osticket.crt -text -noout | grep -E "Before|After"

# For Let's Encrypt, check renewal date
sudo certbot certificates
```

#### 4. Requests Timing Out

**Cause**: PHP-FPM pool exhausted or application slow

**Solution**:
```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Increase pm.max_children in php-fpm.conf (see deployment/php-fpm.conf)

# View slow logs
sudo tail -f /var/log/php-fpm/osticket-laravel.slow.log

# Monitor active connections
watch -n 1 'ps aux | grep php-fpm | grep -v grep | wc -l'
```

---

## Performance Tuning

### 1. Gzip Compression

Both Nginx and Apache configurations include gzip compression. Verify it's working:

```bash
# Should return: Content-Encoding: gzip
curl -I -H "Accept-Encoding: gzip" -k https://127.0.0.1/css/app.css | grep Content-Encoding
```

### 2. Cache Headers

Verify cache headers are being sent:

```bash
# Should return: Cache-Control: public, immutable
curl -I -k https://127.0.0.1/css/app.css | grep Cache-Control
curl -I -k https://127.0.0.1/js/app.js | grep Cache-Control
```

### 3. Connection Pooling

PHP-FPM uses connection pooling. Monitor active connections:

```bash
# View pool stats (requires Nginx with stub_status or Apache mod_status)
curl -k https://127.0.0.1/server-status 2>/dev/null | head -20
```

### 4. Rate Limiting Tuning

Adjust rate limits in configuration files:

```nginx
# Nginx: Change in nginx.conf
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;

# Apache: Change in apache.conf
# Rate ~100  # kB/s (adjust number)
```

---

## Gradual Migration Path

As you migrate more features to Laravel, update the reverse proxy configuration:

### Phase 1 (Current)
- `/scp/*` → Laravel (Staff Control Panel)
- `/api/*` → Laravel (API compatibility layer)
- Everything else → Legacy osTicket

### Phase 2 (Future)
- `/scp/*` → Laravel
- `/api/*` → Laravel
- `/kb/*` → Laravel (Knowledge Base)
- `/pages/*` → Laravel
- Everything else → Legacy osTicket

### Phase 3 (Complete)
- Everything → Laravel
- Legacy osTicket decommissioned

**To migrate routes**: Update the reverse proxy configuration file and reload.

---

## Security Considerations

1. **HTTPS Only**: Enforce HTTP → HTTPS redirect (done in configs)
2. **Security Headers**: Added X-Frame-Options, X-Content-Type-Options, HSTS
3. **Hide Server Details**: Remove Server header in production
4. **Rate Limiting**: Configured for API protection
5. **File Access**: Sensitive files (.htaccess, logs) blocked
6. **PHP Execution**: Only index.php and API handlers execute
7. **SSL/TLS**: TLS 1.2+ only, strong ciphers

---

## Useful Commands Reference

```bash
# Nginx
sudo nginx -t                          # Test config
sudo systemctl reload nginx            # Reload
sudo systemctl restart nginx           # Restart
sudo systemctl status nginx            # Status
tail -f /var/log/nginx/access.log      # Monitor

# Apache
sudo apachectl configtest              # Test config
sudo systemctl reload apache2          # Reload
sudo systemctl restart apache2         # Restart
sudo systemctl status apache2          # Status
tail -f /var/log/apache2/access.log    # Monitor

# PHP-FPM
sudo php-fpm -t                        # Test config
sudo systemctl reload php8.2-fpm       # Reload
sudo systemctl restart php8.2-fpm      # Restart
sudo systemctl status php8.2-fpm       # Status

# Check listening ports
sudo netstat -tlnp | grep -E "9000|8080|:80|:443"

# Check process counts
ps aux | grep -E "nginx|apache2|php-fpm" | grep -v grep | wc -l
```

---

## Support & Maintenance

- Review logs regularly for errors
- Monitor PHP-FPM pool exhaustion
- Keep SSL certificates current (auto-renewal for Let's Encrypt)
- Update Nginx/Apache for security patches
- Plan migration path as features complete
