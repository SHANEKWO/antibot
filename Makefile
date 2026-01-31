# ============================================
# ANTI-BOT SYSTEM - MAKEFILE
# ============================================

# Configuration
TARGET_URL ?= https://loopcar.fr
PHP_BIN ?= php
PORT ?= 8080

# Couleurs
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m

.PHONY: help install setup permissions dirs config clean dev test deploy logs stats

# ============================================
# AIDE
# ============================================

help:
	@echo ""
	@echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║         ANTI-BOT SYSTEM - COMMANDS         ║$(NC)"
	@echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"
	@echo ""
	@echo "  $(YELLOW)SETUP$(NC)"
	@echo "  make install          Setup interactif complet"
	@echo ""
	@echo "  $(YELLOW)DEV$(NC)"
	@echo "  make dev              Serveur local :8080"
	@echo "  make test             Test curl + navigateur"
	@echo "  make logs             Logs temps réel"
	@echo ""
	@echo "  $(YELLOW)PRODUCTION$(NC)"
	@echo "  make check-prod       Vérifier avant déploiement"
	@echo "  make prod             Déployer en production (interactif)"
	@echo "  make zip              Créer antibot.zip"
	@echo ""
	@echo "  $(YELLOW)MAINTENANCE$(NC)"
	@echo "  make stats            Statistiques"
	@echo "  make clean            Nettoyer logs/cache"
	@echo ""

# ============================================
# INSTALLATION COMPLETE (INTERACTIVE)
# ============================================

install: banner setup dirs permissions secret done
	@echo ""

banner:
	@echo ""
	@echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║      INSTALLATION ANTI-BOT SYSTEM          ║$(NC)"
	@echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"
	@echo ""

setup:
	@echo "$(YELLOW)→ Configuration interactive...$(NC)"
	@echo ""
	@read -p "  URL de redirection (humains) [https://loopcar.fr]: " target_url; \
	target_url=$${target_url:-https://loopcar.fr}; \
	sed -i "s|'target_url' => '.*'|'target_url' => '$$target_url'|" config.php; \
	echo "  $(GREEN)✓$(NC) Target: $$target_url"
	@echo ""
	@read -p "  URL de blocage (bots) [https://google.fr]: " block_url; \
	block_url=$${block_url:-https://google.fr}; \
	sed -i "s|'block_url' => '.*'|'block_url' => '$$block_url'|" config.php; \
	echo "  $(GREEN)✓$(NC) Block: $$block_url"
	@echo ""
	@read -p "  Mot de passe dashboard: " dash_pwd; \
	if [ -n "$$dash_pwd" ]; then \
		sed -i "s|'dashboard_password' => '.*'|'dashboard_password' => '$$dash_pwd'|" config.php; \
		echo "  $(GREEN)✓$(NC) Mot de passe configuré"; \
	else \
		echo "  $(YELLOW)⚠$(NC) Mot de passe par défaut (admin123) - À changer!"; \
	fi
	@echo ""

dirs:
	@echo "$(YELLOW)→ Création des répertoires...$(NC)"
	@mkdir -p data/patterns
	@mkdir -p data/ip_cache
	@mkdir -p data/ratelimit
	@mkdir -p logs
	@mkdir -p cache
	@echo "  $(GREEN)✓$(NC) Répertoires créés"

permissions:
	@echo "$(YELLOW)→ Permissions...$(NC)"
	@chmod 755 .
	@chmod 644 *.php
	@chmod 644 .htaccess
	@chmod -R 755 lib
	@chmod -R 777 data
	@chmod -R 777 logs
	@chmod -R 777 cache
	@echo "  $(GREEN)✓$(NC) Permissions configurées"

secret:
	@echo "$(YELLOW)→ Génération clé secrète...$(NC)"
	@if grep -q "CHANGE_THIS" lib/Fingerprint.php 2>/dev/null; then \
		SECRET=$$(openssl rand -hex 32); \
		sed -i "s|CHANGE_THIS_TO_A_RANDOM_SECRET_KEY_IN_PRODUCTION|$$SECRET|" lib/Fingerprint.php; \
		echo "  $(GREEN)✓$(NC) Nouvelle clé générée"; \
	else \
		echo "  $(GREEN)✓$(NC) Clé déjà configurée"; \
	fi

done:
	@echo ""
	@echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║            INSTALLATION TERMINÉE           ║$(NC)"
	@echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"
	@echo ""
	@echo "  $(YELLOW)Configuration:$(NC)"
	@echo "  • Humains → $(shell grep target_url config.php | cut -d"'" -f4)"
	@echo "  • Bots    → $(shell grep block_url config.php | cut -d"'" -f4)"
	@echo "  • Dashboard: /dashboard.php"
	@echo ""
	@echo "  $(YELLOW)Commandes:$(NC)"
	@echo "  • make dev        Serveur local"
	@echo "  • make check-prod Vérifier avant déploiement"
	@echo "  • make zip        Créer archive pour upload"
	@echo ""

# ============================================
# DEVELOPPEMENT
# ============================================

dev:
	@echo ""
	@echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║         SERVEUR DE DÉVELOPPEMENT           ║$(NC)"
	@echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"
	@echo ""
	@echo "  $(YELLOW)URL:$(NC) http://localhost:$(PORT)"
	@echo "  $(YELLOW)Redirection:$(NC) $(shell grep target_url config.php | cut -d"'" -f4)"
	@echo ""
	@echo "  Ctrl+C pour arrêter"
	@echo ""
	@$(PHP_BIN) -S localhost:$(PORT) -t .

# ============================================
# TESTS
# ============================================

test: test-bot test-headers test-info
	@echo ""
	@echo "$(GREEN)✓ Tests terminés$(NC)"
	@echo ""

test-bot:
	@echo ""
	@echo "$(YELLOW)→ Test 1: Détection bot (curl)...$(NC)"
	@STATUS=$$(curl -s -o /dev/null -w "%{http_code}" http://localhost:$(PORT)/ 2>/dev/null || echo "000"); \
	if [ "$$STATUS" = "403" ]; then \
		echo "  $(GREEN)✓$(NC) curl bloqué (403) - OK"; \
	elif [ "$$STATUS" = "000" ]; then \
		echo "  $(RED)✗$(NC) Serveur non démarré (make dev d'abord)"; \
	else \
		echo "  $(RED)✗$(NC) curl non bloqué ($$STATUS)"; \
	fi

test-headers:
	@echo ""
	@echo "$(YELLOW)→ Test 2: Headers manquants...$(NC)"
	@STATUS=$$(curl -s -o /dev/null -w "%{http_code}" -H "User-Agent: Mozilla/5.0" http://localhost:$(PORT)/ 2>/dev/null || echo "000"); \
	if [ "$$STATUS" = "200" ] || [ "$$STATUS" = "302" ]; then \
		echo "  $(GREEN)✓$(NC) Requête avec UA passée ($$STATUS) - OK"; \
	elif [ "$$STATUS" = "000" ]; then \
		echo "  $(RED)✗$(NC) Serveur non démarré"; \
	else \
		echo "  $(YELLOW)⚠$(NC) Status: $$STATUS"; \
	fi

test-info:
	@echo ""
	@echo "$(YELLOW)→ Test 3: Ouvre dans ton navigateur...$(NC)"
	@echo "  $(GREEN)URL:$(NC) http://localhost:$(PORT)/"
	@echo "  Tu devrais être redirigé vers $(shell grep target_url config.php | cut -d"'" -f4)"

# ============================================
# LOGS & STATS
# ============================================

logs:
	@echo ""
	@echo "$(GREEN)═══ LOGS EN TEMPS RÉEL ═══$(NC)"
	@echo "(Ctrl+C pour quitter)"
	@echo ""
	@tail -f logs/*.log 2>/dev/null || echo "Pas de logs pour l'instant"

stats:
	@echo ""
	@echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║              STATISTIQUES                  ║$(NC)"
	@echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"
	@echo ""
	@if [ -d logs ] && [ "$$(ls -A logs 2>/dev/null)" ]; then \
		echo "  $(YELLOW)Total requêtes:$(NC)"; \
		wc -l logs/*.log 2>/dev/null | tail -1 | awk '{print "    " $$1 " requêtes"}'; \
		echo ""; \
		echo "  $(YELLOW)Par statut:$(NC)"; \
		grep -h "ALLOWED" logs/*.log 2>/dev/null | wc -l | awk '{print "    ✓ Autorisés: " $$1}'; \
		grep -h "BLOCKED" logs/*.log 2>/dev/null | wc -l | awk '{print "    ✗ Bloqués:   " $$1}'; \
		echo ""; \
		echo "  $(YELLOW)Top 10 IPs:$(NC)"; \
		grep -oh 'IP: [0-9.]*' logs/*.log 2>/dev/null | sort | uniq -c | sort -rn | head -10 | awk '{print "    " $$1 "x " $$3}'; \
		echo ""; \
		echo "  $(YELLOW)Raisons de blocage:$(NC)"; \
		grep -oh 'Reason: [A-Z_]*' logs/*.log 2>/dev/null | sort | uniq -c | sort -rn | head -5 | awk '{print "    " $$1 "x " $$3}'; \
	else \
		echo "  Pas encore de logs"; \
	fi
	@echo ""

# ============================================
# NETTOYAGE
# ============================================

clean:
	@echo "$(YELLOW)→ Nettoyage logs et cache...$(NC)"
	@rm -rf logs/*
	@rm -rf cache/*
	@rm -rf data/ratelimit/*
	@echo "  $(GREEN)✓$(NC) Nettoyé"

clean-all: clean
	@echo "$(YELLOW)→ Reset complet...$(NC)"
	@rm -rf data/patterns/*
	@rm -rf data/ip_cache/*
	@echo "  $(GREEN)✓$(NC) Reset complet effectué"

# ============================================
# DEPLOIEMENT PRODUCTION
# ============================================

prod:
	@echo ""
	@echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║        DÉPLOIEMENT PRODUCTION              ║$(NC)"
	@echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"
	@echo ""
	@read -p "  URL de redirection (humains): " target_url; \
	if [ -n "$$target_url" ]; then \
		sed -i "s|'target_url' => '.*'|'target_url' => '$$target_url'|" config.php; \
	fi
	@read -p "  URL blocage (bots) [https://google.fr]: " block_url; \
	block_url=$${block_url:-https://google.fr}; \
	sed -i "s|'block_url' => '.*'|'block_url' => '$$block_url'|" config.php
	@read -p "  Mot de passe dashboard: " dash_pwd; \
	if [ -n "$$dash_pwd" ]; then \
		sed -i "s|'dashboard_password' => '.*'|'dashboard_password' => '$$dash_pwd'|" config.php; \
	fi
	@echo ""
	@echo "$(YELLOW)→ Génération clé secrète...$(NC)"
	@SECRET=$$(openssl rand -hex 32); \
	sed -i "s|'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY_IN_PRODUCTION'|'$$SECRET'|" lib/Fingerprint.php; \
	sed -i "s|private static string \$$secretKey = '[a-f0-9]\{64\}'|private static string \$$secretKey = '$$SECRET'|" lib/Fingerprint.php; \
	echo "  $(GREEN)✓$(NC) Clé secrète générée"
	@echo ""
	@read -p "  Hôte SSH (ex: user@monserveur.com): " ssh_host; \
	read -p "  Chemin distant [/var/www/antibot]: " remote_path; \
	remote_path=$${remote_path:-/var/www/antibot}; \
	echo ""; \
	echo "$(YELLOW)→ Upload vers $$ssh_host:$$remote_path...$(NC)"; \
	rsync -avz --progress \
		--exclude 'logs/*' \
		--exclude 'data/patterns/*' \
		--exclude 'data/ip_cache/*' \
		--exclude 'data/ratelimit/*' \
		--exclude 'cache/*' \
		--exclude '.git' \
		--exclude 'Makefile' \
		--exclude 'README.md' \
		./ $$ssh_host:$$remote_path/; \
	echo ""; \
	echo "$(YELLOW)→ Configuration serveur...$(NC)"; \
	ssh $$ssh_host "cd $$remote_path && mkdir -p data/patterns data/ip_cache data/ratelimit logs cache && chmod -R 777 data logs cache"; \
	echo ""; \
	echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"; \
	echo "$(GREEN)║            DÉPLOIEMENT TERMINÉ             ║$(NC)"; \
	echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"; \
	echo ""; \
	echo "  $(GREEN)✓$(NC) Anti-bot déployé!"; \
	echo ""; \
	echo "  $(YELLOW)Dashboard:$(NC) https://TONDOMAINE$$remote_path/dashboard.php"; \
	echo ""

deploy:
	@echo "$(YELLOW)Utilise 'make prod' pour le déploiement interactif$(NC)"
	@echo ""
	@if [ -z "$(HOST)" ]; then \
		echo "Ou: make deploy HOST=user@server.com PATH=/var/www/antibot"; \
		exit 1; \
	fi
	@DEPLOY_PATH=$${PATH:-/var/www/antibot}; \
	rsync -avz --exclude 'logs/*' --exclude 'data/*' --exclude 'cache/*' --exclude '.git' \
		./ $(HOST):$$DEPLOY_PATH/ && \
	ssh $(HOST) "cd $$DEPLOY_PATH && mkdir -p data/patterns data/ip_cache data/ratelimit logs cache && chmod -R 777 data logs cache" && \
	echo "$(GREEN)✓ Déployé!$(NC)"

# ============================================
# CHECK PROD
# ============================================

check-prod:
	@echo ""
	@echo "$(GREEN)╔════════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║         CHECKLIST PRODUCTION               ║$(NC)"
	@echo "$(GREEN)╚════════════════════════════════════════════╝$(NC)"
	@echo ""
	@echo "$(YELLOW)1. Mot de passe dashboard:$(NC)"
	@grep -q "admin123" config.php && echo "   $(RED)✗ Encore 'admin123' - À CHANGER!$(NC)" || echo "   $(GREEN)✓ Modifié$(NC)"
	@echo ""
	@echo "$(YELLOW)2. URL cible:$(NC)"
	@grep "target_url" config.php | head -1
	@echo ""
	@echo "$(YELLOW)3. Permissions:$(NC)"
	@test -w data && echo "   $(GREEN)✓ data/ writable$(NC)" || echo "   $(RED)✗ data/ non writable$(NC)"
	@test -w logs && echo "   $(GREEN)✓ logs/ writable$(NC)" || echo "   $(RED)✗ logs/ non writable$(NC)"
	@echo ""
	@echo "$(YELLOW)4. Fichiers présents:$(NC)"
	@test -f index.php && echo "   $(GREEN)✓ index.php$(NC)" || echo "   $(RED)✗ index.php$(NC)"
	@test -f verify.php && echo "   $(GREEN)✓ verify.php$(NC)" || echo "   $(RED)✗ verify.php$(NC)"
	@test -f dashboard.php && echo "   $(GREEN)✓ dashboard.php$(NC)" || echo "   $(RED)✗ dashboard.php$(NC)"
	@test -f .htaccess && echo "   $(GREEN)✓ .htaccess$(NC)" || echo "   $(RED)✗ .htaccess$(NC)"
	@echo ""
	@echo "$(YELLOW)5. À faire sur le serveur:$(NC)"
	@echo "   • Configurer HTTPS (Let's Encrypt)"
	@echo "   • Restreindre dashboard.php par IP dans .htaccess"
	@echo "   • Changer le mot de passe dashboard"
	@echo ""

# ============================================
# ZIP POUR UPLOAD MANUEL
# ============================================

zip:
	@echo "$(YELLOW)→ Création archive...$(NC)"
	@zip -r antibot.zip . -x "logs/*" -x "data/*" -x "cache/*" -x ".git/*" -x "*.zip"
	@echo "  $(GREEN)✓$(NC) antibot.zip créé"
	@ls -lh antibot.zip
