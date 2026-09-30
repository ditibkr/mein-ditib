# SpendenPortal PHP – DITIB Krefeld

PHP 8.3-FPM + dein vorhandener Nginx-Proxy · SQLite

## Architektur

```
Browser → Nginx-Proxy (vorhanden) → php-fpm Container :9000
```

## Schnellstart

### 1. Netzwerk prüfen
```bash
# Wie heißt dein Nginx-Proxy-Netzwerk?
docker network ls
```
Den Namen in `docker-compose.yml` bei `proxy_network` eintragen.

### 2. Konfiguration anpassen
```bash
nano config/settings.php   # IMAP, SMTP, Passwort
```

### 3. Container starten
```bash
docker compose up -d --build
```

### 4. Nginx-Proxy konfigurieren
Die fertige Nginx-Konfiguration liegt unter `docker/nginx-proxy.conf`.
Pfade und Domain anpassen, dann:
```bash
# Beispiel für Nginx Proxy Manager: Host über GUI anlegen
# Beispiel für klassischen Nginx:
cp docker/nginx-proxy.conf /etc/nginx/sites-available/spendenportal
ln -s /etc/nginx/sites-available/spendenportal /etc/nginx/sites-enabled/
nginx -t && nginx -s reload
```

## Dateien live ändern – KEIN Neustart nötig ✅

| Datei/Ordner | Inhalt |
|---|---|
| `config/settings.php` | Alle Einstellungen |
| `static/css/portal.css` | Design-System |
| `templates/*.php` | HTML-Seiten |
| `src/*.php` | PHP-Logik |

Nur bei neuen Composer-Paketen: `docker compose up -d --build`

## Cron-Job manuell testen
```bash
docker compose exec spendenportal_php php /var/www/html/src/cron_job.php
```
