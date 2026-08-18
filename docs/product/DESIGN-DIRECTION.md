# G-POS — Direction produit et visuelle

**Statut : DESIGN FOUNDATION v0.1**

## 1. Ambition

G-POS doit donner la sensation d’un outil professionnel haut de gamme sans ressembler à un ERP lourd.

Référence d’expérience interne : la simplicité, le calme et l’évidence obtenus avec GamaDrive.

Le produit doit être utilisable :

- dans une petite boutique sur téléphone ;
- sur tablette au comptoir ;
- sur ordinateur par un responsable ;
- dans une organisation multi-sites ;
- avec une connectivité parfois imparfaite.

## 2. Promesse UX

> **Votre activité commerciale, connectée à votre réseau.**

La première question de chaque écran est :

> **Qu’est-ce que l’utilisateur veut accomplir maintenant ?**

Pas : « quelles données pouvons-nous afficher ? »

## 3. Shell principal

Navigation primaire proposée :

- **Accueil**
- **Vendre**
- **Achats**
- **Commandes**
- **Stock**
- **Livraisons**
- **Caisse**

Navigation secondaire/contextuelle :

- Catalogue
- Clients
- Fournisseurs
- Documents
- Rapports
- Paramètres
- Journal / audit pour les rôles autorisés

Le réseau commercial V2 doit apparaître naturellement dans les contextes Clients, Fournisseurs, Achats et Commandes — pas comme un onglet social artificiel « Network » plaqué sur V1.

## 4. Accueil

L’accueil n’est pas un mur de statistiques.

Ordre recommandé :

### A. Contexte actif

Nom de l’activité, point de vente ou organisation active.

### B. À faire maintenant

Maximum 3 à 5 actions prioritaires :

- commande à préparer ;
- livraison à confirmer ;
- caisse à clôturer ;
- stock critique ;
- paiement à vérifier.

### C. Actions rapides

- Vendre
- Acheter
- Ajouter un produit
- Réceptionner
- Encaisser

### D. Vue de santé discrète

Quelques indicateurs réellement utiles, jamais décoratifs.

## 5. Vente mobile

Le parcours « Vendre » doit être le benchmark de vitesse du produit.

Objectif : une vente simple en quelques gestes.

Séquence :

1. rechercher/scanner un article ;
2. ajuster quantité ;
3. voir total immédiatement ;
4. client seulement si nécessaire ;
5. choisir paiement ;
6. confirmer ;
7. reçu / partager / imprimer.

Aucun écran intermédiaire inutile.

## 6. Achat / approvisionnement

Pour un petit utilisateur, le mot **Acheter** est plus naturel que « Procurement » ou « Achats fournisseurs ».

L’interface peut progressivement révéler : fournisseur, bon de commande, réception partielle, reliquat, coût, dette fournisseur et document.

## 7. Design language

G-POS doit appartenir à la famille GAMAD mais posséder une identité économique propre.

### Atmosphère

- lumineuse ;
- calme ;
- précise ;
- tactile ;
- chaleureuse ;
- fiable ;
- africaine contemporaine sans folklore décoratif.

### Couleurs de travail

Palette exacte à finaliser visuellement, mais direction :

- fond ivoire très clair ;
- surfaces blanches/chaudes ;
- vert profond ou pétrole pour l’ancrage ;
- cuivre/terre pour les actions commerciales ;
- safran pour attention/attente ;
- rouge réservé aux erreurs, pertes et actions destructives ;
- couleurs financières jamais utilisées pour moraliser (« bon/mauvais humain »).

### Typographie

- titres chaleureux et identitaires ;
- interface sans-serif très lisible ;
- chiffres tabulaires pour monnaie/quantités ;
- références techniques en mono uniquement lorsque nécessaire.

## 8. Composants clés

- ActionCard
- MoneyField
- QuantityStepper
- ProductPicker
- CustomerPicker
- SupplierPicker
- PaymentMethodPicker
- StatusPill
- Timeline métier
- DocumentPreview
- SyncState
- ContextSwitcher
- EmptyState
- ConfirmCriticalAction

Les composants doivent être tactiles et accessibles, pas minuscules.

## 9. États métier visibles

Chaque opération importante doit montrer un état humainement compréhensible.

Exemples :

Commande : Brouillon · À confirmer · Confirmée · En préparation · Partiellement livrée · Terminée · Annulée.

Paiement : En attente · En cours · Confirmé · Échoué · Remboursé.

Livraison : À préparer · Prête · En route · Remise · Partielle · Échec/retour.

Les codes techniques restent internes.

## 10. Offline / synchronisation

Ne jamais masquer l’état réseau.

Un utilisateur doit savoir :

- si l’opération est enregistrée localement ;
- si elle est synchronisée ;
- si elle attend une reprise ;
- si une action nécessite Internet.

Pas de spinner éternel sans explication.

## 11. Permissions dans l’interface

Ne pas afficher une action interdite puis répondre « accès refusé » lorsqu’on peut connaître la permission à l’avance.

L’interface s’adapte au rôle commercial actif.

Exemples :

- vendeur : vendre, client, reçu ;
- stock : réception, mouvement, inventaire ;
- caissier : encaisser, caisse, clôture ;
- responsable : prix, remise, approbations ;
- finance : coûts, créances, rapprochement selon autorisation.

Les rôles finaux seront contractuels et configurables.

## 12. Intégration ZUMRA

Depuis DG Afrique, les entrées doivent être actionnelles :

- « Ouvrir cette activité dans G-POS » ;
- « Préparer l’achat » ;
- « Mettre en vente » ;
- « Gérer la livraison ».

Dans G-POS, le contexte ZUMRA peut être affiché discrètement mais l’interface reste commerciale.

## 13. Ce que G-POS ne doit jamais devenir visuellement

- tableau Excel géant ;
- dashboard avec 20 cartes KPI ;
- logiciel gris d’administration ;
- réseau social bis ;
- écran de finance anxiogène ;
- interface pleine de codes CAP visibles aux utilisateurs ;
- application qui exige un tutoriel avant la première vente.

## 14. Première maquette à construire

Les premiers écrans à prototyper :

1. Accueil / À faire maintenant ;
2. Vente mobile ;
3. Paiement ;
4. Confirmation + reçu ;
5. Stock simplifié ;
6. Fiche produit ;
7. Commande fournisseur ;
8. contexte actif / changement d’activité.

Ces écrans doivent définir le langage visuel avant de multiplier les modules.
