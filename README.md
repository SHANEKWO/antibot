# AntiBot

Système anti-bot PHP léger et efficace. Redirige les humains vers votre site, bloque silencieusement les bots vers Google.

## Fonctionnalités

- **Détection multi-couche** : User-Agent, headers, IP reputation, fingerprinting
- **Proof of Work** : Challenge cryptographique invisible pour les humains
- **Redirection silencieuse** : Les bots sont redirigés sans savoir qu'ils sont détectés
- **Dashboard** : Statistiques en temps réel
- **Zero dépendance** : PHP pur, pas de base de données

## Installation

```bash
git clone https://github.com/votre-repo/antibot.git
cd antibot
make install
```

Le setup interactif vous demandera :
- URL de redirection (humains)
- URL de blocage (bots)
- Mot de passe dashboard

## Utilisation

```bash
make help          # Voir toutes les commandes
make dev           # Serveur local :8080
make check-prod    # Vérifier avant déploiement
make prod          # Déployer en production
```

## Configuration

Éditez `config.php` :

```php
'target_url' => 'https://votresite.com',  // Humains → ici
'block_url' => 'https://google.fr',        // Bots → ici
'block_threshold' => 70,                   // Score de blocage
'dashboard_password' => 'changez-moi',     // Mot de passe dashboard
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

## Licence

MIT
