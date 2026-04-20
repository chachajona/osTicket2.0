# osTicket 2.0 Reverse Proxy — Quick Start

## For Nginx (Recommended)

```bash
# 1. Install Nginx and PHP-FPM
sudo apt update && sudo apt install nginx php8.2-fpm

# 2. Copy configurations
sudo cp deployment/nginx.conf /etc/nginx/sites-available/osticket
sudo ln -s /etc/nginx/sites-available/osticket /etc/nginx/sites-enabled/
sudo cp deployment/php-fpm.conf /etc/php/8.2/fpm/pool.d/osticket.conf

# 3. Edit Nginx config (update domain, paths, SSL cert)
sudo nano /etc/nginx/sites-available/osticket
# Find and replace:
# - server_name _;  →  server_name your-domain.com;
# - /var/www/osticket2.0  →  [your Laravel path]
# - /var/www/osticket  →  [your legacy osTicket path]
# - /etc/ssl/certs/osticket.crt  →  [your cert path]
# - /etc/ssl/private/osticket.key  →  [your key path]

# 4. Test and reload
sudo nginx -t
sudo systemctl reload nginx php8.2-fpm

# 5. Verify (once deployed)
bash deployment/verify-routing.sh your-domain.com https
```

## For Apache

```bash
# 1. Install Apache and PHP-FPM
sudo apt update && sudo apt install apache2 php8.2-fpm

# 2. Enable modules
sudo a2enmod proxy proxy_http rewrite ssl headers deflate expires ratelimit

# 3. Copy configurations
sudo cp deployment/apache.conf /etc/apache2/sites-available/osticket.conf
sudo cp deployment/php-fpm.conf /etc/php/8.2/fpm/pool.d/osticket.conf
sudo a2ensite osticket

# 4. Edit Apache config
sudo nano /etc/apache2/sites-available/osticket.conf
# Update domain, paths, and certificate locations (same as Nginx)

# 5. Test and reload
sudo apachectl configtest
sudo systemctl reload apache2 php8.2-fpm

# 6. Verify
bash deployment/verify-routing.sh your-domain.com https
```

## SSL Certificate Setup

### Option A: Let's Encrypt (Recommended for Production)

```bash
sudo apt install certbot
sudo certbot certonly --standalone -d your-domain.com -d www.your-domain.com

# Update config with:
# /etc/letsencrypt/live/your-domain.com/fullchain.pem
# /etc/letsencrypt/live/your-domain.com/privkey.pem
```

### Option B: Self-Signed (Development Only)

```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/osticket.key \
  -out /etc/ssl/certs/osticket.crt
```

## Verification

After deployment, test these URLs:

```bash
# HTTP redirect
curl -I http://your-domain.com/
# Should return: 301 or 302 redirect to https://

# HTTPS works
curl -k https://your-domain.com/
# Should work without certificate errors (with -k for self-signed)

# Laravel SCP routes to correct backend
curl -k https://your-domain.com/scp/
# Should show Laravel SCP (or login page for auth)

# API routes to Laravel
curl -k https://your-domain.com/api/tickets/ | head -20
# Should return JSON or auth error

# Legacy still accessible
curl -k https://your-domain.com/pages/
# Should show legacy osTicket pages

# Static assets cached
curl -I -k https://your-domain.com/css/app.css
# Should show: Cache-Control: public, immutable
```

## Troubleshooting Quick Fixes

**502 Bad Gateway?**
```bash
systemctl status php8.2-fpm
systemctl restart php8.2-fpm
tail -f /var/log/php-fpm/osticket-laravel.error.log
```

**SSL certificate issues?**
```bash
openssl x509 -in /etc/ssl/certs/osticket.crt -text -noout | grep -E "Before|After"
# For Let's Encrypt: certbot certificates
```

**Routes return 404?**
```bash
# Check error logs
tail -f /var/log/nginx/osticket_error.log
# or
tail -f /var/log/apache2/osticket_error.log
```

**Files not found after "502"?**
```bash
# Verify upstream paths exist
ls -la /var/www/osticket2.0/public/
ls -la /var/www/osticket/

# Fix permissions if needed
sudo chown -R www-data:www-data /var/www/osticket2.0/
```

## Full Documentation

See deployment/README.md for:
- Complete installation guide
- Performance tuning
- Monitoring and logging
- Migration path for Phase 2-3
- SSL certificate renewal
- Rate limiting tuning

## Useful Commands

```bash
# Test configuration syntax
nginx -t
apachectl configtest

# Reload without downtime
systemctl reload nginx
systemctl reload apache2

# View logs in real-time
tail -f /var/log/nginx/osticket_access.log
tail -f /var/log/nginx/osticket_error.log
tail -f /var/log/php-fpm/osticket-laravel.error.log

# Check listening ports
netstat -tlnp | grep -E ':80|:443|:9000|:8080'

# Monitor PHP-FPM processes
watch -n 1 'ps aux | grep php-fpm | grep -v grep | wc -l'
```

---

**Next**: See deployment/README.md for detailed deployment guide.
