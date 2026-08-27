# Neela — Technologies, runtimes et infrastructure

## Objectif

Neela ne doit pas se limiter au suivi des dépendances Composer/npm.

À terme, il doit pouvoir répondre à une question plus large :

> Quels projets de mon parc nécessitent mon attention côté dépendances, runtimes ou infrastructure ?

Cela implique de détecter des éléments comme :

- PHP
- Node.js
- Symfony
- Laravel
- Docker
- et d'autres technologies ou runtimes

## Ne pas confondre dépendances et technologies

Les dépendances sont liées à un gestionnaire de dépendances :

```text
Project
└── Manifest
    └── Dependency
        └── Package
            └── Version
```

PHP, Docker ou Node.js ne doivent pas être modélisés comme des `Package`. Ils représentent plutôt des technologies, runtimes ou éléments d'infrastructure détectés dans le projet.

## Modèle

```text
Project
├── Manifest
│   └── Dependency
│       └── Package
│           └── Version
│
└── ProjectTechnology
    └── Technology
```

### Technology

Représente une technologie détectable dans un projet.

Exemples :

```text
PHP
Node.js
Symfony
Laravel
Docker
```

On peut lui associer un type :

```text
runtime
framework
infrastructure
language
tool
database
```

### ProjectTechnology

Représente la présence d'une technologie dans un projet.

Propriétés envisagées :

```text
id
project
technology
version
source
path
```

Exemple :

```text
ProjectTechnology

project: my-shop
technology: PHP
version: 8.3
source: composer.json
path: composer.json
```

## Détection de PHP

PHP est particulièrement important pour Neela car il est directement lié à Composer.

Sources possibles :

```text
composer.json
Dockerfile
.github/workflows/*
fichiers de configuration
```

Exemple :

```json
{
    "require": {
        "php": "^8.3"
    }
}
```

Neela peut enregistrer :

```text
Technology: PHP
Version / constraint: ^8.3
Source: composer.json
```

Il est utile de distinguer :

- version déclarée
- version réellement utilisée
- version du runtime détectée
- version requise par une dépendance

## Détection de Docker

Docker peut être détecté via :

```text
Dockerfile
docker-compose.yml
compose.yaml
```

Exemple :

```dockerfile
FROM php:8.3-fpm
```

Neela peut détecter :

```text
Technology: Docker
Image: php:8.3-fpm
```

et éventuellement :

```text
Technology: PHP
Version: 8.3
Source: Dockerfile
```

La gestion complète des mises à jour Docker est un sujet distinct et ne fait pas partie du MVP.

## Pourquoi gérer les technologies ?

Le dashboard devient beaucoup plus utile qu'un simple outil de dépendances.

Exemple :

```text
my-shop

Runtime
  PHP       8.3
  Node.js   22

Framework
  Symfony   7.3

Dependencies
  Composer  8 updates
  npm       3 updates

Infrastructure
  Docker    php:8.3-fpm
```

## Exemples de recherches

```text
Tous les projets PHP 8.2
```

```text
Tous les projets utilisant Symfony 6.x
```

```text
Tous les projets Laravel
```

```text
Tous les projets utilisant Docker
```

```text
Tous les projets PHP 8.2 ayant des mises à jour Composer
```

```text
Tous les projets Symfony ayant au moins une mise à jour majeure disponible
```

## Ne pas créer une entité par technologie

Éviter :

```text
PhpVersion
DockerVersion
NodeVersion
SymfonyVersion
LaravelVersion
```

Préférer :

```text
Technology
ProjectTechnology
```

Cela permet d'ajouter une nouvelle technologie sans modifier le modèle principal.

## Sources de détection

Une technologie peut être détectée depuis plusieurs sources :

```text
composer.json
composer.lock
package.json
Dockerfile
compose.yaml
.github/workflows/*.yml
```

Il est important de conserver la source de détection.

Exemple :

```text
ProjectTechnology

technology: PHP
version: 8.3
source: Dockerfile
path: docker/php/Dockerfile
```

Cela permet ensuite d'expliquer à l'utilisateur pourquoi Neela considère qu'une technologie est présente.

## Relation avec les Dependency Managers

Les technologies et les Dependency Managers sont deux concepts différents.

```text
DependencyManager
├── Composer
├── npm
├── Cargo
└── PyPI
```

Ils permettent d'analyser les manifests et dépendances.

```text
Technology
├── PHP
├── Symfony
├── Docker
└── Node.js
```

Ils représentent les technologies utilisées par le projet.

Un projet peut donc être :

```text
Project
│
├── Dependency Managers
│   ├── Composer
│   └── npm
│
├── Technologies
│   ├── PHP 8.3
│   ├── Symfony 7
│   ├── Node.js 22
│   └── Docker
│
└── Manifests
    ├── app/back/composer.json
    └── app/front/package.json
```

## Priorité

### MVP

Commencer par :

```text
Project
Manifest
DependencyManager
Dependency
Package
Version
```

Puis ajouter :

```text
Technology
ProjectTechnology
```

avec PHP comme première technologie détectée.

### Phase suivante

Ajouter :

```text
Node.js
Symfony
Laravel
Docker
```

Puis éventuellement :

```text
MySQL
PostgreSQL
Redis
Nginx
```

## Philosophie

Neela doit progressivement devenir un outil de **maintenance de parc logiciel**, et pas uniquement un dashboard Composer.

Les dépendances répondent à :

> Quelles dépendances peuvent être mises à jour ?

Les technologies répondent à :

> Qu'est-ce qui compose réellement mes projets ?

Les deux informations combinées permettent de répondre à :

> Quels projets de mon parc nécessitent mon attention et pourquoi ?
