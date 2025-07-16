# 📦 Guide d'Installation Complet - HyperPlanning

## Structure Complète des Fichiers

```
hyperplanning/
├── hyperplanning.php                    ✅ Créé - Fichier principal du plugin
├── uninstall.php                        ✅ Créé - Script de désinstallation
├── composer.json                        ✅ Créé - Dépendances PHP
├── package.json                         ✅ Créé - Dépendances JS/CSS
├── phpunit.xml                          ✅ Créé - Configuration tests
├── README.md                            ✅ Créé - Documentation
├── LICENSE                              ❌ À créer - Licence GPL v2
├── .htaccess                            ✅ Créé - Sécurité
├── index.php                            ❌ À créer - Fichier vide de sécurité
│
├── admin/                               
│   ├── class-hp-admin.php               ✅ Créé - Classe admin principale
│   ├── class-hp-settings.php            ✅ Créé - Gestion des paramètres
│   ├── index.php                        ❌ À créer - Fichier vide
│   └── views/                           ❌ Dossier pour templates admin
│       └── index.php                    ❌ À créer
│
├── includes/
│   ├── class-hp-loader.php              ✅ Créé - Gestionnaire de hooks
│   ├── class-hp-activator.php           ✅ Créé - Activation du plugin
│   ├── class-hp-deactivator.php         ✅ Créé - Désactivation
│   ├── class-hp-constants.php           ✅ Créé - Constantes globales
│   ├── helpers.php                      ✅ Créé - Fonctions utilitaires
│   ├── index.php                        ❌ À créer
│   │
│   ├── models/
│   │   ├── class-hp-trainer.php         ✅ Créé - Modèle Formateur
│   │   ├── class-hp-calendar.php        ✅ Créé - Modèle Calendrier
│   │   ├── class-hp-event.php           ✅ Créé - Modèle Événement
│   │   └── index.php                    ❌ À créer
│   │
│   ├── sync/
│   │   ├── class-hp-sync-manager.php    ✅ Créé - Gestionnaire sync
│   │   ├── class-hp-google-sync.php     ✅ Créé - Sync Google
│   │   ├── class-hp-ical-sync.php       ✅ Créé - Sync iCal
│   │   └── index.php                    ❌ À créer
│   │
│   └── api/
│       ├── class-hp-rest-controller.php ✅ Créé - API REST
│       └── index.php                    ❌ À créer
│
├── public/
│   ├── class-hp-public.php              ✅ Créé - Classe publique
│   ├── class-hp-shortcodes.php          ✅ Créé - Shortcodes
│   ├── index.php                        ❌ À créer
│   └── views/                           ❌ Dossier pour templates publics
│       └── index.php                    ❌ À créer
│
├── assets/
│   ├── css/
│   │   ├── admin.css                    ✅ Créé - Styles admin
│   │   ├── public.css                   ✅ Créé - Styles publics
│   │   └── public-extended.css          ✅ Créé - Styles additionnels
│   │
│   ├── js/
│   │   ├── admin.js                     ✅ Créé - JS admin
│   │   └── public.js                    ✅ Créé - JS public
│   │
│   └── images/                          ❌ Dossier pour images
│       └── (logos, icônes...)
│
├── languages/
│   ├── hyperplanning.pot                ✅ Créé - Template traductions
│   ├── hyperplanning-fr_FR.po           ❌ À créer - Traduction française
│   └── hyperplanning-fr_FR.mo           ❌ À compiler - Fichier binaire
│
├── vendor/                              ❌ Généré par Composer
│   └── (dépendances Composer)
│
└── tests/
    ├── bootstrap.php                    ✅ Créé - Bootstrap tests
    ├── class-hp-test-case.php           ✅ Créé - Classe de base
    ├── test-trainer.php                 ✅ Créé - Tests Trainer
    ├── test-event.php                   ✅ Créé - Tests Event
    ├── test-calendar.php                ✅ Créé - Tests Calendar
    ├── test-sync.php                    ✅ Créé - Tests Sync
    └── test-api.php                     ✅ Créé - Tests API
```

## 📋 Instructions d'Installation Étape par Étape

### 1. Préparation de l'environnement

```bash
# Créer le dossier du plugin
mkdir wp-content/plugins/hyperplanning
cd wp-content/plugins/hyperplanning

# Créer la structure des dossiers
mkdir -p admin/views includes/{models,sync,api} public/views assets/{css,js,images} languages tests vendor
```

### 2. Copier tous les fichiers créés

Copiez tous les fichiers des artifacts dans leurs emplacements respectifs selon la structure ci-dessus.

### 3. Créer les fichiers index.php manquants

Créez un fichier `index.php` dans chaque dossier avec ce contenu :
```php
<?php
// Silence is golden.
```

### 4. Installer les dépendances PHP

```bash
# Installer Composer si ce n'est pas déjà fait
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Installer les dépendances
composer install --no-dev --optimize-autoloader
```

### 5. Créer le fichier LICENSE

```bash
# Télécharger la licence GPL v2
curl -o LICENSE https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
```

### 6. Compiler les traductions (optionnel)

```bash
# Si vous avez créé des fichiers .po
msgfmt languages/hyperplanning-fr_FR.po -o languages/hyperplanning-fr_FR.mo
```

### 7. Configuration WordPress minimale

Ajoutez à votre `wp-config.php` :
```php
// Mode debug pour le développement
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Configuration HyperPlanning (optionnel)
define('HYPERPLANNING_DEBUG', true);
```

### 8. Activer le plugin

1. Connectez-vous à l'admin WordPress
2. Allez dans **Extensions > Extensions installées**
3. Cherchez **HyperPlanning**
4. Cliquez sur **Activer**

### 9. Configuration initiale

1. **Configurer Google Calendar** (optionnel) :
   - Allez sur [Google Cloud Console](https://console.cloud.google.com)
   - Créez un nouveau projet
   - Activez l'API Google Calendar
   - Créez des identifiants OAuth 2.0
   - Ajoutez l'URL de redirection : `https://votresite.com/wp-admin/admin.php?page=hyperplanning-google-auth`
   - Copiez le Client ID et Client Secret

2. **Dans WordPress** :
   - Allez dans **HyperPlanning > Paramètres**
   - Entrez vos clés Google (si utilisé)
   - Configurez le fuseau horaire
   - Définissez l'intervalle de synchronisation

3. **Ajouter votre premier formateur** :
   - **HyperPlanning > Formateurs > Ajouter**
   - Remplissez les informations
   - Activez la synchronisation si nécessaire

## 🔧 Dépannage Installation

### Erreur : "Fatal error: Class not found"

```bash
# Vérifiez que Composer a bien généré l'autoloader
composer dump-autoload -o
```

### Erreur : "Google Calendar API error"

1. Vérifiez que votre site est en HTTPS
2. Vérifiez les clés API dans les paramètres
3. Vérifiez que l'API est activée dans Google Cloud Console

### Tables non créées

```php
// Désactivez et réactivez le plugin
// Ou exécutez manuellement :
HP_Activator::activate();
```

### Problèmes de permissions

```bash
# Ajuster les permissions
chmod -R 755 wp-content/plugins/hyperplanning
chmod -R 644 wp-content/plugins/hyperplanning/*.php
```

## 🚀 Démarrage Rapide

### Ajouter le calendrier principal sur une page

```
[hyperplanning_calendar]
```

### Afficher un formateur spécifique

```
[hyperplanning_trainer_calendar trainer="1"]
```

### Liste des formateurs

```
[hyperplanning_trainers_list columns="3"]
```

## 📊 Vérification de l'Installation

### Checklist Post-Installation

- [ ] Le plugin apparaît dans la liste des extensions
- [ ] Le menu HyperPlanning est visible dans l'admin
- [ ] Les tables sont créées dans la base de données
- [ ] Les rôles `hp_trainer` et `hp_coordinator` existent
- [ ] Les assets CSS/JS sont chargés correctement
- [ ] Les shortcodes fonctionnent sur le frontend
- [ ] L'API REST répond à `/wp-json/hyperplanning/v1/`

### Test Rapide

1. Créez un formateur test
2. Ajoutez un événement
3. Affichez le calendrier avec `[hyperplanning_calendar]`
4. Vérifiez que l'événement apparaît

## 🔐 Sécurité Post-Installation

1. **Protégez les dossiers sensibles** :
   - Le fichier `.htaccess` est déjà configuré
   - Vérifiez que `/includes/` n'est pas accessible

2. **Configurez les sauvegardes** :
   - Tables : `wp_hp_*`
   - Dossier : `/wp-content/uploads/hyperplanning/`

3. **Surveillez les logs** :
   - Logs WordPress : `/wp-content/debug.log`
   - Logs HyperPlanning : `/wp-content/hp-debug.log`

## 📱 Support

- **Documentation** : Consultez le README.md
- **Issues** : Créez un ticket sur GitHub
- **Email** : support@hyperplanning.com

---

🎉 **Félicitations !** Votre plugin HyperPlanning est maintenant installé et prêt à l'emploi !