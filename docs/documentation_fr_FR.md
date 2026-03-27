# Documentation complète - Module MultiRapport

## Table des matières

1. [Introduction](#introduction)
2. [Architecture technique](#architecture-technique)
3. [Installation et configuration](#installation-et-configuration)
4. [Utilisation du module](#utilisation-du-module)
5. [Rapport financier](#rapport-financier)
6. [Statut Crédit](#statut-crédit)
7. [Export PDF](#export-pdf)
8. [Référence API](#référence-api)
9. [Dépannage](#dépannage)
10. [FAQ](#faq)

---

## Introduction

### Vue d'ensemble

Le module **MultiRapport** est une extension Dolibarr conçue pour fournir un reporting financier avancé avec la gestion d'un statut personnalisé "Crédit" pour les factures. Il s'intègre nativement dans l'écosystème Dolibarr et utilise les hooks et triggers standards.

### Fonctionnalités clés

| Fonctionnalité | Description |
|---------------|-------------|
| Rapport financier | Tableau de bord avec 6 métriques clés |
| Filtres temporels | Filtrage par date et heure précise |
| Statut Crédit | Marquage personnalisé des factures |
| Actions en masse | Traitement par lot des factures |
| Export PDF | Génération de rapports PDF professionnels |
| Intégration native | Hooks Dolibarr standard |

---

## Architecture technique

### Structure des fichiers

```
custom/multirapport/
├── class/
│   └── actions_multirapport.class.php    # Logique métier principale
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
│   └── multirapport.js.php                # JavaScript pour filtres
├── langs/
│   └── fr_FR/
│       └── multirapport.lang              # Traductions françaises
├── sql/
│   └── llx_multirapport_extrafields.sql   # Schéma de base de données
├── multirapport.php                       # Page de rapport
└── multirapportindex.php                  # Page d'accueil
```

### Classes principales

#### `modMultiRapport` (core/modules/modMultiRapport.class.php)

Définit le module dans Dolibarr :
- Numéro de module : 231200
- Dépendances : modFacture, modExpenseReport
- Menus : entrée principale + sous-menu Rapport
- Permissions : read, write, delete

#### `actions_multirapport` (class/actions_multirapport.class.php)

Gère les hooks et actions :
- `doActions()` : Traitement des actions (marquage Crédit)
- `printFieldListOption()` : Ajout du filtre "Crédit"
- `printFieldListWhere()` : Filtrage SQL pour les factures Crédit
- `printStatus()` : Affichage du statut dans les listes

#### `pdf_multirapport` (core/modules/rapport/pdf_multirapport.class.php)

Génère les rapports PDF :
- Template basé sur Sponge
- En-tête avec logo entreprise
- Tableau des métriques financières
- Liste des factures Crédit payées

### Schéma de données

Le module utilise le champ `close_code` de la table `facture` pour stocker le statut Crédit :

```sql
-- Marquer une facture comme Crédit
UPDATE llx_facture 
SET close_code = 'credit_status', 
    close_note = 'Invoice marked as Credit'
WHERE rowid = [ID_FACTURE]
  AND fk_statut = 1  -- Validated
  AND paye = 0;      -- Not paid
```

### Hooks utilisés

| Hook | Contexte | Utilisation |
|------|----------|-------------|
| doActions | invoicelist, facturelist | Actions en masse |
| doActions | invoicecard, facturecard | Action individuelle |
| printFieldListOption | invoicelist | Filtre statut Crédit |
| printFieldListWhere | invoicelist | Requête SQL filtrée |
| printStatus | invoicelist | Affichage statut |

---

## Installation et configuration

### Prérequis techniques

- **Dolibarr** : version 17.0 ou supérieure
- **PHP** : version 7.4 ou supérieure
- **Extensions PHP** : mysqli/pgsql, gd (pour les images)
- **Base de données** : MySQL 5.7+ ou PostgreSQL 10+

### Installation manuelle

```bash
# 1. Copier le module
cd /var/www/dolibarr/htdocs/custom
cp -r /chemin/vers/multirapport .

# 2. Définir les permissions
chown -R www-data:www-data multirapport
chmod -R 755 multirapport

# 3. Vider le cache Dolibarr
# Accédez à : Accueil > Outils > Infos système > Purger les caches
```

### Activation du module

1. Connectez-vous en tant qu'administrateur
2. Allez dans **Accueil > Configuration > Modules/Applications**
3. Localisez **MultiRapport** dans la section "Modules complémentaires"
4. Activez le module (interrupteur)

### Configuration des constantes

Accédez à **MultiRapport > Configuration** et définissez :

| Constante | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED | Activer le statut Crédit | 1 (activé) |

### Configuration des permissions

1. Allez dans **Utilisateurs & Groupes > Permissions**
2. Pour chaque utilisateur/groupe :
   - `multirapport->read` : Consulter les rapports
   - `multirapport->write` : Marquer les factures
   - `multirapport->delete` : Administrateur

---

## Utilisation du module

### Accès au rapport

**Chemin :** MultiRapport > Rapport

**URL directe :** `/custom/multirapport/multirapport.php`

### Interface du rapport

L'interface présente :

1. **Filtres temporels** (en haut)
   - Date début / Date fin
   - Heure début / Heure fin
   - Bouton "Générer le rapport"

2. **Tableau des métriques** (centre)
   - 6 lignes de métriques financières
   - Codes couleur pour les statuts spéciaux

3. **Liste des factures Crédit payées** (bas)
   - Référence, Tiers, Date, Montant TTC

### Marquage d'une facture comme Crédit

#### Méthode 1 : Action individuelle

1. Ouvrez la fiche d'une facture validée et non payée
2. Dans la barre d'actions, cliquez sur **"Marquer comme Crédit"**
3. Confirmez l'action
4. La facture est marquée avec le statut Crédit

#### Méthode 2 : Action en masse

1. Allez dans **Factures clients > Liste**
2. Cochez les factures à marquer (validées et non payées uniquement)
3. Dans le menu "Actions en masse", sélectionnez **"Marquer comme Crédit"**
4. Confirmez l'action
5. Les factures sélectionnées sont marquées

### Filtrage des factures Crédit

Dans la liste des factures :

1. Ouvrez le filtre **"Statut"**
2. Sélectionnez **"Crédit"**
3. La liste affiche uniquement les factures avec ce statut

---

## Rapport financier

### Métriques calculées

| Métrique | Description | Requête SQL (simplifiée) |
|----------|-------------|--------------------------|
| **Total Chiffre d'affaires** | Somme de toutes les factures | `SUM(total_ttc) WHERE fk_statut > 0` |
| **Total Factures payées** | Factures avec `paye = 1` | `SUM(total_ttc) WHERE paye = 1` |
| **Total Factures impayées** | Factures validées non payées | `SUM(total_ttc) WHERE fk_statut = 1 AND paye = 0 AND close_code IS NULL` |
| **Total Notes de frais** | Dépenses validées | `SUM(amount) FROM expense_report` |
| **Total Crédit (non payé)** | Factures marquées Crédit | `SUM(total_ttc) WHERE close_code = 'credit_status' AND paye = 0` |
| **Total Crédit payé** | Factures Crédit maintenant payées | `SUM(total_ttc) WHERE close_code = 'credit_status' AND paye = 1` |

### Filtres de date et heure

Les filtres permettent une analyse précise :

```php
// Exemple de filtre : 15/01/2024 09:00 à 15/01/2024 17:00
$date_start = '2024-01-15';
$hour_start = 9;
$date_end = '2024-01-15';
$hour_end = 17;

// Conversion timestamp
$date_start_ts = strtotime('2024-01-15 09:00:00');
$date_end_ts = strtotime('2024-01-15 17:59:59');
```

### Période analysée

Par défaut, le rapport couvre :
- **Date début** : Début du mois en cours
- **Date fin** : Fin du mois en cours
- **Heures** : 00:00 à 23:59

---

## Statut Crédit

### Principe de fonctionnement

Le statut Crédit utilise le mécanisme natif de Dolibarr (`close_code`) pour marquer des factures sans les clôturer définitivement.

### États d'une facture

```
Brouillon (0)
    ↓
Validée (1) ──► Marquer comme Crédit ──► close_code = 'credit_status'
    ↓                                          ↓
Payée (2) ◄────────────────────────────────────┘
    ↓
Clôturée (3)
```

### Règles métier

1. **Marquage possible uniquement si :**
   - Statut = Validée (1)
   - Non payée (paye = 0)

2. **Une facture Crédit peut :**
   - Être payée (statut devient Payée)
   - Conserver son `close_code = 'credit_status'` pour traçabilité

3. **Filtrage :**
   - Requête SQL : `fk_statut = 1 AND close_code = 'credit_status'`

---

## Export PDF

### Template utilisé

Le module utilise le template **Sponge** natif de Dolibarr (TCPDF) avec personnalisation.

### Structure du PDF généré

1. **En-tête** (par page)
   - Logo de l'entreprise
   - Nom et adresse de l'entreprise
   - Ligne de séparation

2. **Titre du rapport**
   - "Rapport financier MultiRapport"
   - Période analysée
   - Date de génération

3. **Tableau des métriques**
   - Header avec fond violet (#6B4C9A)
   - Lignes alternées (gris clair/blanc)
   - Métriques Crédit surlignées (orange/violet)

4. **Liste des factures Crédit payées** (si présentes)
   - Tableau avec : Référence, Tiers, Date, Montant
   - Pagination automatique

### Génération du PDF

Cliquez sur le bouton **"Générer PDF"** dans la page de rapport.

**Fichier généré :** `Rapport_MultiRapport_[DATE]_[HEURE].pdf`

**Emplacement :** `documents/multirapport/`

---

## Référence API

### Méthodes de `actions_multirapport`

#### `doActions($parameters, $object, $action, $hookmanager)`

Traite les actions "Marquer comme Crédit".

**Paramètres :**
- `$parameters['currentcontext']` : Contexte (invoicelist, invoicecard)
- `$object` : Objet facture
- `$action` : Action demandée

**Retour :**
- `1` : Action traitée
- `0` : Action non concernée

#### `printFieldListOption($parameters, $object)`

Ajoute l'option "Crédit" au filtre de statut.

#### `printFieldListWhere($parameters, $object)`

Modifie la clause WHERE pour filtrer les factures Crédit.

#### `markInvoiceAsCredit($invoice_id, $user)`

Marque une facture comme Crédit.

**Paramètres :**
- `$invoice_id` : ID de la facture
- `$user` : Objet utilisateur (permissions)

**Retour :**
- `1` : Succès
- `-1` : Échec

### Méthodes de `pdf_multirapport`

#### `write_file($dir, $metrics, $creditInvoices, $dateStart, $dateEnd, $hourStart, $hourEnd, $outputlangs)`

Génère le fichier PDF du rapport.

**Paramètres :**
- `$dir` : Répertoire de sortie
- `$metrics` : Tableau des métriques financières
- `$creditInvoices` : Liste des factures Crédit payées
- `$dateStart/$dateEnd` : Dates de la période
- `$hourStart/$hourEnd` : Heures de la période
- `$outputlangs` : Langue de sortie

---

## Dépannage

### Problèmes courants

#### Le menu n'apparaît pas

**Cause :** Cache Dolibarr non vidé

**Solution :**
```
Accueil > Outils > Infos système > Purger les caches
```

#### Erreur 404 sur multirapport.php

**Cause :** URL incorrecte dans le menu

**Solution :** Vérifiez que l'URL contient `/custom/` :
```php
'url' => '/custom/multirapport/multirapport.php'
```

#### Le total "Crédit" affiche 0

**Cause :** Mauvais statut dans la requête SQL

**Vérification :** Les factures Crédit doivent avoir :
- `fk_statut = 1` (VALIDATED), pas 2 (CLOSED)
- `close_code = 'credit_status'`
- `paye = 0` (pour Crédit non payé)

#### L'action "Marquer comme Crédit" ne fonctionne pas

**Vérifications :**
1. La constante `MULTIRAPPORT_INVOICE_CREDIT_STATUS_ENABLED` est-elle à 1 ?
2. L'utilisateur a-t-il la permission `multirapport->write` ?
3. La facture est-elle validée et non payée ?

### Logs et debug

Activez les logs Dolibarr pour tracer les actions :

```php
// Dans conf/conf.php
dolibarr.log.level = DEBUG
```

Fichier de log : `documents/dolibarr.log`

Recherchez les entrées :
```
MultiRapport::doActions
MultiRapport::markInvoiceAsCredit
```

---

## FAQ

### Q : Peut-on marquer une facture déjà payée comme Crédit ?

**R :** Non. Seules les factures validées et non payées peuvent être marquées comme Crédit. Si une facture Crédit est payée, elle conserve son marquage mais change de statut.

### Q : Le statut Crédit est-il visible par les clients ?

**R :** Non, c'est un statut interne qui n'apparaît pas sur les documents PDF envoyés aux clients (factures, avoirs, etc.).

### Q : Comment supprimer le statut Crédit d'une facture ?

**R :** Actuellement, il n'y a pas d'action "Démarquer" implémentée. Le statut est conservé pour l'historique. Contactez le support si cette fonctionnalité est nécessaire.

### Q : Le rapport peut-il être exporté en Excel/CSV ?

**R :** Pour l'instant, seul l'export PDF est disponible. L'export CSV/Excel pourra être ajouté dans une future version.

### Q : Les factures Crédit apparaissent-elles dans le chiffre d'affaires ?

**R :** Oui, les factures Crédit sont incluses dans le "Total Chiffre d'affaires" car elles représentent du revenu potentiel.

### Q : Puis-je personnaliser le template PDF ?

**R :** Oui, modifiez le fichier `core/modules/rapport/pdf_multirapport.class.php`. Suivez la structure de la classe Sponge de Dolibarr.

---

## Contact et support

Pour toute question ou problème :

1. Consultez cette documentation
2. Vérifiez les logs Dolibarr
3. Contactez le support avec :
   - Version Dolibarr
   - Version du module
   - Description du problème
   - Extraits des logs pertinents

---

**Document version :** 1.0.0  
**Dernière mise à jour :** 2025  
**Module MultiRapport pour Dolibarr ERP & CRM**
