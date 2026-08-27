# Neela

**Neela** est un dashboard de **monitoring des dépendances** pour vos projets logiciels. Il répond à une question simple :

> Parmi tous mes projets, lesquels ont des dépendances qui peuvent être mises à jour ?

Neela **n'automatise pas les mises à jour**. Il ne crée ni branche, ni commit, ni Pull Request. Il se connecte à vos dépôts, analyse leurs manifests de dépendances, et centralise le résultat dans une interface unique. C'est un observateur, pas un bot comme Dependabot ou Renovate.

Le projet est open source et pensé pour être **self-hostable via Docker**.

## Exemple

```text
NEELA — Dependency Monitor

127 projets analysés

🔴 8 projets nécessitent une attention importante
🟠 23 projets ont des mises à jour
🟢 96 projets sont à jour
```

Puis, projet par projet :

```text
site-client-a

symfony/console
  installé : 6.4.18
  compatible : 6.4.21
  type : PATCH

laravel/framework
  installé : 11.35.0
  compatible : 11.42.0
  type : MINOR
```

## Fonctionnalités

- **Import de projets** GitHub par lien SSH (authentification par token, configurable depuis la page Paramètres)
- **Découverte automatique des manifests** dans un repo : `composer.json`/`composer.lock`, `package.json`/`package-lock.json`
- **Résolution des dépendances** en confrontant la version verrouillée (lockfile) à la contrainte déclarée et aux versions disponibles sur le registre (Packagist, npm), avec classement **patch / minor / major**
- **Détection de technologies** (Symfony, Laravel, React, Vue pour l'instant) avec statut de support/fin de vie via [endoflife.date](https://endoflife.date)
- **Dashboard global**, vues par projet, par manifest, par package, par vendor, par technologie, historique des scans
- **Scans asynchrones** (Symfony Messenger) pour ne jamais bloquer l'interface
- Interface en français et en anglais

### Ce que Neela ne fait pas (pas encore, ou pas par design)

- Pas de détection de vulnérabilités (à venir)
- Pas de découverte automatique des repos d'un compte/organisation GitHub — l'import se fait projet par projet
- Pas de scans périodiques planifiés — un scan se déclenche à l'import ou manuellement via "Rescan"
- Pas de gestionnaires de dépendances autres que Composer et npm pour l'instant (Cargo, PyPI... prévus)
- Aucune modification de vos dépôts : lecture seule, toujours

## Stack

- **Backend** : Symfony 8.1, PHP 8.5, Doctrine ORM, PostgreSQL, Symfony Messenger
- **Frontend** : Twig, Stimulus, Symfony UX — pas de SPA, volontairement simple
- **Runtime** : [FrankenPHP](https://frankenphp.dev) + Caddy (HTTPS automatique, HTTP/3)
- **Déploiement** : Docker / Docker Compose

## Self-hosting

L'image officielle est publiée sur Docker Hub : [`sruuua/neela`](https://hub.docker.com/r/sruuua/neela).

Un déploiement minimal nécessite trois services : l'application, un worker qui consomme les scans en tâche de fond, et une base PostgreSQL. Aucune infrastructure supplémentaire (queue externe, cache...) n'est requise : par défaut, la queue de messages réutilise la base de données.

```yaml
# compose.yml
services:
  app:
    image: sruuua/neela:latest
    restart: unless-stopped
    environment:
      SERVER_NAME: ${SERVER_NAME:-:80}
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: postgresql://${POSTGRES_USER:-neela}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB:-neela}?serverVersion=16&charset=utf8
      MESSENGER_TRANSPORT_DSN: doctrine://default
      # Optionnel : token GitHub par défaut (peut aussi être défini depuis la page Paramètres)
      GITHUB_TOKEN: ${GITHUB_TOKEN:-}
      # Requis par Caddy/FrankenPHP au démarrage, même si Neela n'utilise pas Mercure lui-même
      CADDY_MERCURE_JWT_SECRET: ${CADDY_MERCURE_JWT_SECRET}
    ports:
      - "8080:80"
    volumes:
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      database:
        condition: service_healthy

  worker:
    image: sruuua/neela:latest
    restart: unless-stopped
    environment:
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: postgresql://${POSTGRES_USER:-neela}:${POSTGRES_PASSWORD}@database:5432/${POSTGRES_DB:-neela}?serverVersion=16&charset=utf8
      MESSENGER_TRANSPORT_DSN: doctrine://default
      GITHUB_TOKEN: ${GITHUB_TOKEN:-}
    # -d memory_limit=512M est nécessaire : le memory_limit par défaut de l'image (128M) est
    # plus bas que le seuil --memory-limit ci-dessous, donc PHP tuerait le process en OOM avant
    # que Messenger n'ait la moindre chance de redémarrer proprement, laissant des scans bloqués.
    command: php -d memory_limit=512M bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
    depends_on:
      database:
        condition: service_healthy

  database:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-neela}
      POSTGRES_USER: ${POSTGRES_USER:-neela}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "${POSTGRES_DB:-neela}", "-U", "${POSTGRES_USER:-neela}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    volumes:
      - database_data:/var/lib/postgresql/data

volumes:
  caddy_data:
  caddy_config:
  database_data:
```

Puis, à côté de ce `compose.yml`, un fichier `.env` :

```dotenv
POSTGRES_PASSWORD=change-me
APP_SECRET=generate-a-random-secret
CADDY_MERCURE_JWT_SECRET=generate-another-random-secret
# GITHUB_TOKEN=ghp_xxx
```

`APP_SECRET` et `CADDY_MERCURE_JWT_SECRET` peuvent être générés avec `openssl rand -hex 32`.

Démarrage :

```console
docker compose up -d
```

Les migrations de base de données s'appliquent automatiquement au démarrage du container `app`. Neela est ensuite accessible sur `http://localhost:8080`.

> [!TIP]
> Si vous avez un nom de domaine pointant vers votre serveur, mettez `SERVER_NAME=votre-domaine.example.com` et exposez les ports 80/443 : Caddy provisionnera automatiquement un certificat HTTPS via Let's Encrypt.

Une fois démarré, allez dans **Paramètres** pour renseigner votre token d'accès personnel GitHub (nécessaire pour les dépôts privés et pour éviter les limites de taux de l'API GitHub), puis ajoutez un projet depuis **Projets → Nouveau projet**.

## Développement local

Ce dépôt est basé sur [Symfony Docker](https://github.com/dunglas/symfony-docker) et fournit un environnement de développement complet (Dev Container inclus) :

1. [Installer Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. `docker compose build --pull --no-cache`
3. `docker compose up --wait`
4. Ouvrir `https://localhost` (accepter le certificat TLS auto-généré)
5. `docker compose down --remove-orphans` pour arrêter

Docs complémentaires héritées du template : [options disponibles](docs/options.md), [services additionnels](docs/extra-services.md), [Xdebug](docs/xdebug.md), [certificats TLS](docs/tls.md), [MySQL au lieu de PostgreSQL](docs/mysql.md), [troubleshooting](docs/troubleshooting.md), [agents de code IA](docs/agents.md).

## Licence

MIT.
