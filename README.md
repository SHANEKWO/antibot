# AntiBot

Système anti-bot PHP léger et efficace. Redirige les humains vers votre site, bloque silencieusement les bots vers Google.

## Fonctionnalités

- **Détection multi-couche** : User-Agent, headers, IP reputation, fingerprinting
- **Proof of Work** : Challenge cryptographique invisible pour les humains
- **Redirection silencieuse** : Les bots sont redirigés sans savoir qu'ils sont détectés
- **Dashboard** : Statistiques en temps réel
- **Zero dépendance** : PHP pur, pas de base de données

## Installation rapide

```bash
git clone https://github.com/SHANEKWO/antibot.git
cd antibot
make prod
```

## Prérequis serveur

- **PHP 8.0+**
- **Apache** avec `mod_rewrite` activé (ou Nginx)
- **OpenSSL** (pour génération clé secrète)

### Ubuntu/Debian

```bash
sudo apt update
sudo apt install php apache2 libapache2-mod-php
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## Configuration production

### 1. Clone et configuration

```bash
cd /var/www
git clone https://github.com/SHANEKWO/antibot.git
cd antibot
make prod
```

Le setup interactif vous demandera :
- URL de redirection (humains) → votre site
- URL de blocage (bots) → google.fr par défaut
- Mot de passe dashboard

### 2. Configuration Apache

Créez un vhost `/etc/apache2/sites-available/antibot.conf` :

```apache
<VirtualHost *:80>
    ServerName votredomaine.com
    DocumentRoot /var/www/antibot

    <Directory /var/www/antibot>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/antibot_error.log
    CustomLog ${APACHE_LOG_DIR}/antibot_access.log combined
</VirtualHost>
```

Activez le site :

```bash
sudo a2ensite antibot.conf
sudo systemctl reload apache2
```

### 3. HTTPS avec Let's Encrypt

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d votredomaine.com
```

### 4. Sécurisation dashboard

Éditez `.htaccess` pour restreindre l'accès au dashboard par IP :

```apache
<Files "dashboard.php">
    Require ip 123.456.789.0
</Files>
```

## Configuration Nginx (alternative)

```nginx
server {
    listen 80;
    server_name votredomaine.com;
    root /var/www/antibot;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ ^/(lib|data|logs|cache)/ {
        deny all;
    }
}
```

## Commandes disponibles

```bash
make help          # Voir toutes les commandes
make prod          # Configurer pour la production
make check-prod    # Vérifier la configuration
make dev           # Serveur local :8080 (test)
make test          # Tests automatiques
make logs          # Logs en temps réel
make stats         # Statistiques
make clean         # Nettoyer logs/cache
```

## Comment ça marche

```
Visiteur
    │
    ▼
┌─────────────┐     Score < 25      ┌─────────────┐
│  Analyse    │ ──────────────────► │ Votre site  │
│  du risque  │                     └─────────────┘
└─────────────┘
    │
    │ Score 25-69
    ▼
┌─────────────┐     Challenge OK    ┌─────────────┐
│  Challenge  │ ──────────────────► │ Votre site  │
│  JavaScript │                     └─────────────┘
└─────────────┘
    │
    │ Score ≥ 70 ou échec
    ▼
┌─────────────┐
│  Google.fr  │  (bot redirigé silencieusement)
└─────────────┘
```

## Signaux détectés

| Signal | Impact |
|--------|--------|
| curl/wget/python | Bloqué |
| Puppeteer/Selenium | Bloqué |
| IP Datacenter (AWS, OVH...) | +40 points |
| VPN/Proxy | +25 points |
| Headers manquants | +15-25 points |
| FAI français (Orange, Free...) | -20 points (bonus) |

## Configuration avancée

Éditez `config.php` :

```php
return [
    'target_url' => 'https://votresite.com',  // Humains → ici
    'block_url' => 'https://google.fr',        // Bots → ici

    'instant_redirect_threshold' => 25,        // < 25 = redirect instantané
    'challenge_threshold' => 40,               // >= 40 = challenge JS
    'block_threshold' => 70,                   // >= 70 = bloqué

    'pow_difficulty' => 4,                     // Difficulté Proof of Work
    'dashboard_password' => 'changez-moi',     // Mot de passe dashboard
];
```

## Dashboard

Accédez à `/dashboard.php` pour voir :
- Total requêtes / autorisées / bloquées
- Graphiques par jour et par heure
- Top IPs bloquées
- Raisons de blocage
- Dernières requêtes en temps réel

## Structure

```
antibot/
├── config.php          # Configuration
├── index.php           # Point d'entrée
├── verify.php          # Validation challenge
├── dashboard.php       # Statistiques
├── .htaccess           # Protection Apache
├── Makefile            # Commandes
└── lib/
    ├── RiskEngine.php  # Analyse de risque
    ├── Fingerprint.php # Tokens & PoW
    ├── RateLimit.php   # Rate limiting
    └── Logger.php      # Logs
```

## Checklist production

- [ ] `make prod` exécuté
- [ ] Mot de passe dashboard changé (pas `admin123`)
- [ ] HTTPS activé
- [ ] Dashboard restreint par IP
- [ ] Vhost Apache/Nginx configuré
- [ ] Permissions OK (`make check-prod`)

## Licence

MIT
