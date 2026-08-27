# Neela

## Vision

Neela est un dashboard de **monitoring des dépendances** pour les
projets logiciels.

L'objectif est de répondre à une question simple :

> Parmi tous mes projets GitHub, lesquels ont des dépendances qui
> peuvent être mises à jour ?

Neela **n'automatise pas les mises à jour**. Il ne crée pas de branche,
de commit ou de Pull Request. Il analyse l'état des projets et présente
les mises à jour disponibles dans une interface centralisée.

Le projet commence avec **PHP / Composer**, mais est conçu pour pouvoir
gérer d'autres gestionnaires de dépendances comme **npm, PyPI, Cargo,
etc.**

Le projet vise à être open source et self hostable via docker

------------------------------------------------------------------------

## Objectifs

-   Connecter un compte ou une organisation GitHub.
-   Découvrir les repositories accessibles.
-   Identifier les projets utilisant un gestionnaire de dépendances
    supporté.
-   Récupérer `composer.json` et `composer.lock` pour les projets
    Composer.
-   Identifier les dépendances réellement installées.
-   Interroger les registries correspondants, notamment Packagist.
-   Déterminer les versions disponibles et leur compatibilité avec les
    contraintes du projet.
-   Classer les mises à jour : patch, minor, major.
-   Détecter les éventuelles vulnérabilités.
-   Centraliser toutes ces informations dans un dashboard.
-   Permettre de filtrer et rechercher les projets et dépendances.
-   Conserver l'historique des scans et de l'état des dépendances.

------------------------------------------------------------------------

## Ce que Neela ne fait pas

Neela n'est pas un bot de mise à jour comme Dependabot ou Renovate.

Il ne doit pas, dans sa fonction principale :

-   modifier les repositories ;
-   modifier `composer.json` ;
-   modifier `composer.lock` ;
-   créer des branches ;
-   créer des commits ;
-   créer des Pull Requests ;
-   lancer automatiquement des mises à jour de dépendances.

Neela est un **observateur** et un **outil d'aide à la maintenance**.

------------------------------------------------------------------------

## Exemple de dashboard

``` text
NEELA — Dependency Monitor

127 projets analysés

🔴 8 projets nécessitent une attention importante
🟠 23 projets ont des mises à jour
🟢 96 projets sont à jour

Projet              Updates       Security       Dernier scan
----------------------------------------------------------------
client-a             12 🔴          0             2 min
client-b              3 🟠          1 🔴          5 min
client-c              0 🟢          0             8 min
client-d              7 🟠          0             1 h
```

Un projet peut ensuite afficher le détail :

``` text
site-client-a

symfony/console
  installé : 6.4.18
  compatible : 6.4.21
  type : PATCH

doctrine/orm
  installé : 2.20.2
  compatible : 2.20.4
  type : PATCH

laravel/framework
  installé : 11.35.0
  compatible : 11.42.0
  type : MINOR

phpunit/phpunit
  installé : 11.5.8
  disponible : 12.0.0
  type : MAJOR
```

------------------------------------------------------------------------

# Architecture fonctionnelle

``` text
                         GitHub
                           │
                           ▼
                  Repository Discovery
                           │
                           ▼
                    Project Scanner
                           │
             ┌─────────────┴─────────────┐
             │                           │
       composer.json               composer.lock
             │                           │
             └─────────────┬─────────────┘
                           ▼
                  Dependency Analyzer
                           │
                           ▼
                    Packagist API
                           │
                           ▼
                  Version Resolver
                           │
                           ▼
                       Database
                           │
                           ▼
                      Dashboard
```

Les scans doivent être exécutés en arrière-plan afin de ne pas bloquer
l'interface.

------------------------------------------------------------------------

# Stack envisagée

## Backend

-   Symfony
-   Doctrine ORM
-   PostgreSQL
-   Symfony Messenger
-   Symfony Scheduler ou cron pour les scans périodiques

## Frontend

Pour le MVP :

-   Twig
-   Symfony UX
-   Stimulus
-   éventuellement Symfony UX Live Components

Pas de SPA React/Vue au départ.

L'objectif est de garder le frontend simple et de concentrer l'effort
sur le moteur d'analyse.

## Déploiement

-   Docker
-   PostgreSQL
-   Worker Messenger
-   Application Symfony

------------------------------------------------------------------------

# Modèle de données

Le modèle doit séparer l'identité d'un package de son utilisation dans
un projet.

## DependencyManager

Représente le gestionnaire de dépendances.

Exemples :

``` text
Composer
npm
PyPI
Cargo
```

Champs envisagés :

``` text
id
name
```

------------------------------------------------------------------------

## Vendor

Représente l'organisation ou l'éditeur associé aux packages.

Exemples :

``` text
Symfony
Doctrine
Laravel
```

Champs envisagés :

``` text
id
name
dependency_manager_id
```

------------------------------------------------------------------------

## Package

Représente l'identité d'un package.

Exemples :

``` text
symfony/console
doctrine/orm
laravel/framework
```

Modèle :

``` text
Package
- id
- vendor_id
- name
```

Le package complet peut être reconstruit avec le vendor et le nom.

Pour Composer :

``` text
vendor = symfony
name = console

=> symfony/console
```

Pour d'autres écosystèmes, le modèle devra pouvoir gérer les conventions
de nommage propres au gestionnaire.

------------------------------------------------------------------------

## Version

Représente une version publiée d'un package.

``` text
Version
- id
- package_id
- version
- normalized_version
- released_at
- requires_php
```

Un package peut avoir plusieurs versions.

``` text
symfony/console
    ├── 6.4.18
    ├── 6.4.19
    ├── 6.4.20
    ├── 6.4.21
    └── 7.3.0
```

------------------------------------------------------------------------

## Project

Représente un projet logiciel, initialement un repository GitHub.

Champs envisagés :

``` text
id
name
provider
clone_url
ssh_url
https_url
default_branch
```

Un projet pourra évoluer pour ne pas être strictement dépendant de
GitHub.

------------------------------------------------------------------------

## Dependency

Représente l'utilisation d'un package par un projet.

C'est une entité importante car la version disponible dépend à la fois
du package et des contraintes du projet.

``` text
Dependency
- id
- project_id
- package_id
- constraint
- locked_version
- dependency_type
```

Exemple :

``` text
Project: my-shop
Package: symfony/console
constraint: ^6.4
locked_version: 6.4.18
dependency_type: require
```

------------------------------------------------------------------------

## Scan

Représente une analyse effectuée sur un projet.

``` text
Scan
- id
- project_id
- status
- started_at
- completed_at
- error
```

Le scan permet de conserver l'historique et de connaître la fraîcheur
des informations affichées.

------------------------------------------------------------------------

# Relations principales

``` text
DependencyManager
        │
        └── Vendor
              │
              └── Package
                    │
                    └── Version

Project
   │
   └── Dependency
          │
          └── Package

Project
   │
   └── Scan
```

------------------------------------------------------------------------

# Analyse Composer

Pour Composer, `composer.lock` est la source de vérité concernant les
versions réellement installées.

`composer.json` reste nécessaire pour connaître les contraintes
déclarées par le projet.

Il faut donc utiliser les deux :

``` text
composer.lock
    ↓
version réellement installée

composer.json
    ↓
contrainte autorisée

Packagist
    ↓
versions disponibles

         ↓

Version Resolver
         ↓
version compatible la plus récente
```

Exemple :

``` text
composer.json

"symfony/console": "^6.4"
```

``` text
composer.lock

symfony/console = 6.4.18
```

Packagist :

``` text
6.4.19
6.4.20
6.4.21
7.3.0
```

Résultat :

``` text
6.4.18 → 6.4.21   PATCH disponible
6.4.18 → 7.3.0    MAJOR disponible mais hors contrainte actuelle
```

Il ne faut donc pas simplement comparer la version installée avec la
version `latest`.

------------------------------------------------------------------------

# Packagist

Pour Composer, Packagist est la source principale des informations de
packages et de versions.

Neela devra récupérer notamment :

-   versions disponibles ;
-   dates de publication ;
-   contraintes PHP ;
-   métadonnées utiles au package ;
-   éventuellement les informations de sécurité disponibles.

La logique de résolution doit tenir compte des contraintes Composer.

------------------------------------------------------------------------

# Catégorisation des mises à jour

Neela pourra classer les mises à jour selon SemVer lorsque cela est
applicable :

``` text
PATCH
6.4.18 → 6.4.21

MINOR
6.4.x → 6.5.x

MAJOR
6.x → 7.x
```

Il faudra toutefois prévoir que tous les gestionnaires ou packages ne
suivent pas parfaitement SemVer.

------------------------------------------------------------------------

# Dashboard

Le dashboard doit être orienté **maintenance de parc**, et non gestion
individuelle des Pull Requests.

Vues envisagées :

## Vue globale

``` text
Projets
Updates
Vulnérabilités
Derniers scans
```

## Vue projets

Filtres :

-   à jour ;
-   patch disponible ;
-   minor disponible ;
-   major disponible ;
-   vulnérabilité ;
-   gestionnaire de dépendances ;
-   vendor ;
-   package.

## Vue technologies / vendors

Exemple :

``` text
Symfony      42 projets
Doctrine     31 projets
Laravel      18 projets
```

Puis :

``` text
Symfony

42 projets utilisent Symfony

7  avec major disponible
14 avec minor disponible
23 avec patch disponible
8  à jour
```

------------------------------------------------------------------------

# Philosophie du projet

Neela doit répondre rapidement à :

> **"Quels projets de mon parc nécessitent mon attention ?"**

et non :

> "Comment mettre automatiquement mes dépendances à jour ?"

Le produit doit donc privilégier :

-   visibilité ;
-   centralisation ;
-   simplicité ;
-   lecture seule ;
-   analyse fiable ;
-   historique ;
-   recherche ;
-   filtrage.

------------------------------------------------------------------------

# Évolutions possibles

Une fois le support Composer stabilisé :

1.  npm
2.  PyPI
3.  Cargo
4.  autres gestionnaires

Puis éventuellement :

-   GitLab ;
-   autres sources de repositories ;
-   notifications ;
-   historique des versions ;
-   tendances de maintenance ;
-   détection des projets abandonnés ;
-   règles personnalisées ;
-   API ;
-   intégration CI.

Les fonctionnalités d'automatisation des mises à jour ne sont pas un
objectif prioritaire.

------------------------------------------------------------------------

# Positionnement

Neela se situe entre :

``` text
Dependabot / Renovate
        │
        │  automatisent les updates
        ▼

      [ NEELA ]
        │
        │  observe et priorise
        ▼

   Maintenance du parc
```

Neela ne cherche pas à remplacer le moteur de résolution de Composer,
npm, etc.

Il cherche à fournir une **vue centralisée et exploitable de l'état des
dépendances d'un parc de projets**.
