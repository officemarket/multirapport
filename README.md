# Module MultiRapport pour Dolibarr

![Dolibarr version](https://img.shields.io/badge/Dolibarr-17.0%2B-blue)
![PHP version](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/License-GPL--v3-green)

## Présentation

**MultiRapport** est un module Dolibarr avancé qui permet de générer des rapports financiers détaillés avec un système de statut "Crédit" personnalisé pour les factures. Il offre des fonctionnalités de reporting, d'export PDF et d'actions en masse sur les factures.

### Fonctionnalités principales

- 📊 **Rapport financier complet** avec filtres date/heure
- 💳 **Statut "Crédit" personnalisé** pour les factures
- 🎯 **Actions en masse** "Marquer comme Crédit" sur les factures
- 📄 **Export PDF professionnel** avec template Sponge
- 🔍 **Filtrage avancé** par statut Crédit dans la liste des factures

<!--
![Screenshot multirapport](img/screenshot_multirapport.png?raw=true "MultiRapport"){imgmd}
-->

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files in the module directories under `langs`.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more information, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->


## Prérequis

| Composant | Version requise |
|-----------|-----------------|
| Dolibarr | 17.0 ou supérieur |
| PHP | 7.4 ou supérieur |
| TCPDF | Inclus dans Dolibarr |

## Installation

### Méthode 1 : Installation via l'interface Dolibarr

1. Téléchargez le module au format ZIP
2. Allez dans **Accueil > Configuration > Modules/Applications**
3. Cliquez sur **"Déployer un module externe"**
4. Sélectionnez le fichier ZIP et cliquez sur **"Envoyer"**
5. Activez le module **MultiRapport** dans la liste

### Méthode 2 : Installation manuelle

```bash
# Copiez le dossier du module dans custom/
cp -r multirapport /var/www/dolibarr/custom/

# Définissez les permissions correctes
chown -R www-data:www-data /var/www/dolibarr/custom/multirapport
chmod -R 755 /var/www/dolibarr/custom/multirapport
```

### Activation

1. Connectez-vous à Dolibarr en tant qu'administrateur
2. Allez dans **Accueil > Configuration > Modules/Applications**
3. Recherchez **"MultiRapport"**
4. Activez le module en cliquant sur l'interrupteur

### Configuration initiale

1. Accédez à **MultiRapport > Configuration**
2. Activez l'option **"Activer le statut Crédit pour les factures"**
3. Définissez les permissions pour les utilisateurs

<!--

Note: If this screen tells you that there is no "custom" directory, check that your setup is correct:

- In your Dolibarr installation directory, edit the `htdocs/conf/conf.php` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading `//`) and assign the proper value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:

```
custom/multirapport/
├── class/
│   ├── actions_multirapport.class.php    # Actions et hooks
│   └── api_multirapport.class.php         # API (si activée)
├── core/
│   └── modules/
│       ├── modMultiRapport.class.php      # Définition du module
│       └── rapport/
│           └── pdf_multirapport.class.php # Générateur PDF
├── css/
│   └── multirapport.css.php               # Styles CSS
├── img/
│   └── multirapport.png                   # Icône du module
├── js/
│   └── multirapport.js.php                # JavaScript (filtres)
├── langs/
│   └── fr_FR/
│       └── multirapport.lang              # Traductions
├── sql/
│   └── llx_multirapport_extrafields.sql   # Champs extra
├── multirapport.php                       # Page de rapport
└── multirapportindex.php                  # Page d'accueil
```

## Support

Pour signaler un problème ou demander une évolution :

1. Consultez d'abord la [documentation complète](docs/documentation_fr_FR.md)
2. Vérifiez les logs Dolibarr (**Système > Outils > Logs**)
3. Contactez le support avec les informations suivantes :
   - Version de Dolibarr
   - Version du module MultiRapport
   - Description détaillée du problème
   - Logs d'erreur éventuels

## Licence

Ce module est distribué sous licence **GPL v3**.

Copyright (C) 2024-2025 - Tous droits réservés.

## Changelog

### Version 1.0.0
- ✨ Première version stable
- 📊 Rapport financier avec 6 métriques
- 💳 Système de statut Crédit pour factures
- 📄 Export PDF avec template Sponge
- 🎯 Actions en masse "Marquer comme Crédit"
- 🔍 Filtre par statut Crédit

---

**Développé pour Dolibarr ERP & CRM**
