# DC Consult — Documentation complète

---

## Sommaire

1. [Présentation du projet](#1-présentation-du-projet)
2. [Stack technique](#2-stack-technique)
3. [Fonctionnement du projet](#3-fonctionnement-du-projet)
4. [Structure de la base de données](#4-structure-de-la-base-de-données)
5. [Déploiement en production](#5-déploiement-en-production)
6. [Configuration des emails](#6-configuration-des-emails)
7. [Configuration du serveur web](#7-configuration-du-serveur-web)
8. [Procédure de mise à jour](#8-procédure-de-mise-à-jour)
9. [Checklist finale](#9-checklist-finale)

---

## 1. Présentation du projet

DC Consult est une application web Symfony permettant à des utilisateurs de se connecter à un espace personnel pour gérer leurs documents. Un espace d'administration permet aux admins de gérer les utilisateurs, les projets et les documents.

---

## 2. Stack technique

| Technologie | Version | Rôle |
|---|---|---|
| PHP | 8.2+ | Langage serveur |
| Symfony | 7.4 | Framework |
| MySQL | 8.0 | Base de données |
| Doctrine ORM | 3.x | Gestion BDD |
| Symfony Mailer | 7.4 | Envoi d'emails |
| Brevo | — | Serveur SMTP (emails) |
| Symfony AssetMapper | 7.4 | Assets CSS/JS (pas de Node.js) |
| Symfony Rate Limiter | 7.4 | Anti brute-force connexion |

---

## 3. Fonctionnement du projet

### 3.1 Authentification

**Routes concernées :**

| Route | URL | Accès |
|---|---|---|
| Connexion | `/connexion` | Public |
| Déconnexion | `/deconnexion` | Connecté |
| Inscription | `/inscription` | Public |

**Comment ça fonctionne :**

- L'utilisateur se connecte avec son **email + mot de passe**
- Le formulaire est protégé par un **token CSRF** pour éviter les attaques cross-site
- Les mots de passe sont **hashés en bcrypt** — jamais stockés en clair
- Après 5 tentatives échouées, le compte est **bloqué 15 minutes** (login throttling)
- Après connexion, redirection automatique selon le rôle :
  - `ROLE_ADMIN` → `/admin/dashboard`
  - `ROLE_USER` → `/mon-espace/dashboard`

**Hiérarchie des rôles :**
- `ROLE_USER` : accès à l'espace personnel `/mon-espace/*`
- `ROLE_ADMIN` : hérite de `ROLE_USER` + accès à l'administration `/admin/*`

---

### 3.2 Mon Compte

**Routes concernées :**

| Action | URL | Méthode |
|---|---|---|
| Page mon compte | `/mon-espace/mon-compte` | GET |
| Modifier nom / prénom | `/mon-espace/mon-compte` | POST |
| Demander changement mot de passe | `/mon-espace/mon-compte/demande-changement-mdp` | POST |
| Formulaire nouveau mot de passe | `/mon-espace/mon-compte/confirmer-mdp/{token}` | GET/POST |
| Demander changement email | `/mon-espace/mon-compte/demande-changement-email` | POST |
| Confirmer depuis ancien email | `/mon-espace/mon-compte/confirmer-email-ancien/{token}` | GET |
| Valider depuis nouvel email | `/mon-espace/mon-compte/confirmer-email-nouveau/{token}` | GET |

**Modifier nom / prénom :**
Modification directe avec sauvegarde immédiate. Formulaire protégé par token CSRF.

**Changer le mot de passe (1 étape par email) :**
1. L'utilisateur clique "Recevoir un lien"
2. Un email est envoyé à son adresse actuelle avec un lien valable **1 heure**
3. Il clique le lien → formulaire pour définir un nouveau mot de passe (8 caractères minimum)
4. Confirmation du mot de passe obligatoire

**Changer l'email (2 étapes, double validation) :**
1. L'utilisateur saisit son nouvel email
2. Email envoyé à l'**ancien email** pour confirmer la demande (lien valable 1h)
3. Il clique → email envoyé au **nouvel email** pour valider (nouveau lien valable 1h)
4. Il clique → l'email est mis à jour en base de données

**Sécurité des tokens :**
Les tokens de vérification sont générés aléatoirement (`bin2hex(random_bytes(32))`). Seul leur **hash SHA-256** est stocké en base de données. Le token brut circule uniquement dans les emails. Ainsi, même en cas de fuite de la BDD, les tokens sont inutilisables.

---

### 3.3 Mes Documents

**Routes concernées :**

| Action | URL | Méthode |
|---|---|---|
| Liste des documents | `/mon-espace/mes-documents` | GET |
| Voir un document | `/mon-espace/mes-documents/voir/{id}` | GET |
| Ajouter un document | `/mon-espace/mes-documents/nouveau` | GET/POST |
| Modifier un document | `/mon-espace/mes-documents/modifier/{id}` | GET/POST |
| Supprimer un document | `/mon-espace/mes-documents/supprimer/{id}` | POST |

**Comment ça fonctionne :**
- Les fichiers sont uploadés et stockés dans `public/uploads/documents/`
- Taille maximum : **10 Mo** par fichier
- Un utilisateur ne peut accéder **qu'à ses propres documents** (contrôlé par `DocumentVoter`)
- La suppression est protégée par token CSRF
- Lors de la modification, le fichier existant est supprimé si un nouveau est fourni

---

### 3.4 Dashboard utilisateur

| Route | URL |
|---|---|
| Dashboard | `/mon-espace/dashboard` |

Page d'accueil de l'espace personnel, affiche le prénom de l'utilisateur connecté.

---

### 3.5 Administration — Dashboard

| Route | URL |
|---|---|
| Dashboard admin | `/admin/dashboard` |

---

### 3.6 Administration — Gestion des utilisateurs

| Action | URL |
|---|---|
| Liste des utilisateurs | `/admin/gestion-utilisateurs` |
| Voir un utilisateur | `/admin/gestion-utilisateurs/voir/{id}` |
| Ajouter un utilisateur | `/admin/gestion-utilisateurs/nouveau` |
| Modifier un utilisateur | `/admin/gestion-utilisateurs/modifier/{id}` |
| Supprimer un utilisateur | `/admin/gestion-utilisateurs/supprimer/{id}` |

L'admin peut créer des comptes avec le rôle `ROLE_USER` ou `ROLE_ADMIN`, modifier les informations et le mot de passe, et supprimer des comptes. La suppression d'un utilisateur supprime en cascade ses documents (configuré dans l'entité).

---

### 3.7 Administration — Gestion des projets

| Route | URL |
|---|---|
| Gestion projets | `/admin/gestion-projets` |

> Fonctionnalité en cours de développement.

---

### 3.8 Administration — Gestion des documents

| Route | URL |
|---|---|
| Gestion documents | `/admin/gestion-documents` |

> Fonctionnalité en cours de développement.

---

### 3.9 Pages publiques

| Page | URL |
|---|---|
| Accueil | `/` |
| Projets | `/projets` |
| Clients | `/clients` |
| Logiciels | `/logiciels` |
| Contact | `/contact` |

---

## 4. Structure de la base de données

### Table `utilisateur`

| Colonne | Type | Nullable | Description |
|---|---|---|---|
| `id` | int | Non | Clé primaire auto-incrémentée |
| `email` | varchar(180) | Non | Identifiant de connexion, unique |
| `roles` | json | Non | `[]` = ROLE_USER / `["ROLE_ADMIN"]` = admin |
| `password` | varchar | Non | Mot de passe hashé bcrypt |
| `nom` | varchar(255) | Non | Nom de famille |
| `prenom` | varchar(255) | Non | Prénom |
| `password_change_token` | varchar(255) | Oui | Hash SHA-256 du token de reset mot de passe |
| `password_change_token_expires_at` | datetime | Oui | Expiration du token (1h) |
| `pending_email` | varchar(180) | Oui | Nouvel email en attente de validation |
| `email_change_token` | varchar(255) | Oui | Hash SHA-256 du token de changement email |
| `email_change_token_expires_at` | datetime | Oui | Expiration du token (1h) |

### Table `document`

| Colonne | Type | Nullable | Description |
|---|---|---|---|
| `id` | int | Non | Clé primaire auto-incrémentée |
| `date` | datetime | Oui | Date d'ajout |
| `fichier` | varchar(255) | Non | Nom du fichier physique sur le serveur |
| `nom` | varchar(255) | Non | Nom affiché du document |
| `utilisateur_id` | int | Non | Clé étrangère → `utilisateur.id` |

---

## 5. Déploiement en production

### 5.1 Prérequis serveur

- PHP 8.2+ avec les extensions : `pdo_mysql`, `intl`, `ctype`, `iconv`, `mbstring`, `xml`, `curl`
- MySQL 8.0+
- Composer
- Git
- Apache ou Nginx

Vérifier PHP et ses extensions :
```bash
php -v
php -m | grep -E "pdo_mysql|intl|ctype|iconv|mbstring"
```

---

### 5.2 Récupérer le projet

```bash
git clone <url-du-repo> /var/www/dc-consult
cd /var/www/dc-consult
```

---

### 5.3 Installer les dépendances

```bash
composer install --no-dev --optimize-autoloader
```

- `--no-dev` : exclut les outils de développement (PHPUnit, Profiler, etc.)
- `--optimize-autoloader` : génère un autoloader optimisé pour la prod

---

### 5.4 Créer le fichier `.env.local`

Ce fichier contient toutes les informations confidentielles. Il ne doit **jamais** être commité sur git (déjà dans `.gitignore`). Il écrase les valeurs du `.env` de base.

```bash
nano /var/www/dc-consult/.env.local
```

Contenu complet :

```env
APP_ENV=prod
APP_SECRET=COLLE_ICI_LA_CLE_GENEREE

DATABASE_URL="mysql://UTILISATEUR_BDD:MOT_DE_PASSE_BDD@127.0.0.1:3306/NOM_BDD?serverVersion=8.0.32&charset=utf8mb4"

MAILER_DSN=smtp://TON_LOGIN_BREVO%40gmail.com:TON_MOT_DE_PASSE_SMTP@smtp-relay.brevo.com:587

DEFAULT_URI=https://tondomaine.fr
```

**Détail de chaque variable :**

`APP_ENV=prod`
Active le mode production : cache activé, profiler désactivé, erreurs masquées côté client.

`APP_SECRET`
Clé secrète utilisée pour les tokens CSRF et les sessions. Générer une clé unique et aléatoire :
```bash
openssl rand -hex 32
```
Ne jamais réutiliser la même clé entre plusieurs projets.

`DATABASE_URL`
Remplace `UTILISATEUR_BDD`, `MOT_DE_PASSE_BDD` et `NOM_BDD` par les identifiants MySQL de ton serveur de production.

`MAILER_DSN`
Identifiants SMTP Brevo. Le `@` dans le login s'écrit obligatoirement `%40` (encodage URL), sinon Symfony ne parse pas l'URL correctement. Les identifiants se trouvent sur [brevo.com](https://brevo.com) → Paramètres → SMTP & API → onglet SMTP.

`DEFAULT_URI`
URL de base utilisée pour générer les liens dans les emails (changement de mot de passe, changement d'email). Sans cette variable, les liens dans les emails pointent vers `localhost` et sont inutilisables.

---

### 5.5 Créer la base de données et jouer les migrations

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

---

### 5.6 Compiler le cache

```bash
php bin/console cache:clear
php bin/console cache:warmup
```

---

### 5.7 Compiler les assets

Le projet utilise Symfony AssetMapper — pas de Node.js requis :

```bash
php bin/console asset-map:compile
```

Les fichiers CSS/JS sont générés dans `public/assets/`.

---

### 5.8 Créer le dossier d'upload et gérer les permissions

```bash
mkdir -p /var/www/dc-consult/public/uploads/documents

chmod -R 775 /var/www/dc-consult/var/
chmod -R 775 /var/www/dc-consult/public/uploads/

chown -R www-data:www-data /var/www/dc-consult/var/
chown -R www-data:www-data /var/www/dc-consult/public/uploads/
```

> Sur CentOS/RHEL, remplace `www-data` par `apache`.

Le dossier d'upload est configuré dans `config/services.yaml` :
```yaml
parameters:
    documents_directory: '%kernel.project_dir%/public/uploads/documents'
```

---

## 6. Configuration des emails

### 6.1 Valider l'expéditeur sur Brevo

En production, Brevo exige que l'adresse expéditrice soit validée. Deux options :

**Option A — Valider une adresse email simple (rapide) :**
1. Sur [brevo.com](https://brevo.com) → Paramètres → Expéditeurs & IP → Expéditeurs
2. Ajouter ton adresse email → Brevo envoie un email de confirmation → cliquer le lien

**Option B — Valider le domaine entier (recommandé en prod) :**
1. Paramètres → Expéditeurs & IP → Domaines → Ajouter un domaine
2. Entrer `tondomaine.fr`
3. Ajouter les enregistrements DNS fournis (SPF et DKIM) chez ton registrar
4. Une fois propagés, valider sur Brevo
5. Permet d'utiliser n'importe quelle adresse `@tondomaine.fr` comme expéditeur

### 6.2 Configurer l'expéditeur dans le projet

Dans `config/packages/mailer.yaml`, mettre à jour l'expéditeur :

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        headers:
            From: 'DC Consult <noreply@tondomaine.fr>'
```

### 6.3 Emails envoyés par l'application

| Déclencheur | Destinataire | Template |
|---|---|---|
| Demande changement mot de passe | Adresse actuelle | `emails/confirmer_changement_mdp.html.twig` |
| Étape 1 changement email | Ancien email | `emails/confirmer_changement_email_ancien.html.twig` |
| Étape 2 changement email | Nouvel email | `emails/confirmer_changement_email_nouveau.html.twig` |

---

## 7. Configuration du serveur web

### Apache

```bash
nano /etc/apache2/sites-available/dc-consult.conf
```

```apache
<VirtualHost *:80>
    ServerName tondomaine.fr
    ServerAlias www.tondomaine.fr
    DocumentRoot /var/www/dc-consult/public

    <Directory /var/www/dc-consult/public>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/dc-consult-error.log
    CustomLog ${APACHE_LOG_DIR}/dc-consult-access.log combined
</VirtualHost>
```

```bash
a2ensite dc-consult.conf
a2enmod rewrite
systemctl restart apache2
```

### Nginx

```nginx
server {
    listen 80;
    server_name tondomaine.fr www.tondomaine.fr;
    root /var/www/dc-consult/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

```bash
nginx -t && systemctl reload nginx
```

### HTTPS avec Let's Encrypt

```bash
# Apache
apt install certbot python3-certbot-apache
certbot --apache -d tondomaine.fr -d www.tondomaine.fr

# Nginx
apt install certbot python3-certbot-nginx
certbot --nginx -d tondomaine.fr -d www.tondomaine.fr
```

Le renouvellement automatique est configuré par Certbot.

---

## 8. Procédure de mise à jour

À chaque nouvelle version à déployer :

```bash
cd /var/www/dc-consult
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
php bin/console asset-map:compile
```

---

## 9. Checklist finale

### Serveur
- [ ] PHP 8.2+ installé avec toutes les extensions (`pdo_mysql`, `intl`, `ctype`, `iconv`, `mbstring`)
- [ ] MySQL 8.0+ installé et démarré
- [ ] Composer installé
- [ ] Git installé

### Projet
- [ ] `git clone` effectué
- [ ] `composer install --no-dev --optimize-autoloader` exécuté

### Fichier `.env.local`
- [ ] Fichier créé à la racine du projet
- [ ] `APP_ENV=prod`
- [ ] `APP_SECRET` généré avec `openssl rand -hex 32`
- [ ] `DATABASE_URL` avec les bons identifiants de prod
- [ ] `MAILER_DSN` avec le login Brevo encodé (`%40` à la place du `@`)
- [ ] `DEFAULT_URI=https://tondomaine.fr`

### Base de données
- [ ] Base de données créée (`doctrine:database:create`)
- [ ] Toutes les migrations jouées (`doctrine:migrations:migrate`)

### Cache & Assets
- [ ] Cache vidé et réchauffé (`cache:clear` + `cache:warmup`)
- [ ] Assets compilés (`asset-map:compile`)

### Fichiers & Permissions
- [ ] Dossier `public/uploads/documents/` créé
- [ ] `var/` : permissions 775, propriétaire `www-data`
- [ ] `public/uploads/` : permissions 775, propriétaire `www-data`

### Serveur web
- [ ] Virtual Host configuré avec `DocumentRoot` sur `public/`
- [ ] `mod_rewrite` activé (Apache)
- [ ] HTTPS configuré avec Let's Encrypt

### Emails
- [ ] Expéditeur validé sur Brevo (adresse ou domaine)
- [ ] `config/packages/mailer.yaml` mis à jour avec le bon expéditeur
- [ ] Test d'envoi effectué depuis la page Mon Compte → "Recevoir un lien"

### Test final
- [ ] Connexion avec un compte utilisateur fonctionne
- [ ] Connexion avec un compte admin fonctionne
- [ ] Upload d'un document fonctionne
- [ ] Email de changement de mot de passe reçu avec un lien valide