# G-POS — LOT-002 — Achats & Approvisionnement

**Statut : READY FOR IMPLEMENTATION**  
**Date : 18 août 2026**  
**Base : `main` après LOT-001 (`2aae8da6424c0e7cf3bee5c6bd5dd100dc49fdb7`)**

## 1. Intention

Donner à une activité G-POS un cycle d’approvisionnement réel, simple et traçable :

**Fournisseur → Commande d’achat → Réception partielle/totale → Entrée de stock → Paiement comptant simple → Document → Audit.**

LOT-002 doit rendre G-POS utile des deux côtés du comptoir : il sait déjà vendre ; il doit maintenant savoir se réapprovisionner.

## 2. Héritage et doctrine

LOT-002 dérive de :

- `docs/G-POS-DOCTRINE.md` ;
- `docs/architecture/SATELLITE-CONTRACT.md` ;
- `docs/product/DESIGN-DIRECTION.md` ;
- `docs/legacy/ZUMRA-V1-TO-GPOS.md` ;
- `docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md` ;
- le code réellement fusionné de LOT-001.

Le corpus historique ZUMRA V1 préparait explicitement fournisseurs, commandes fournisseurs et réceptions. Cette matière historique est réinterprétée avec l’architecture G-POS actuelle ; elle n’est pas copiée mécaniquement.

## 3. Invariants inviolables

- G-POS ne crée aucune identité humaine canonique parallèle.
- Toute donnée d’achat est scoppée par `CommercialContext`.
- Un fournisseur G-POS est une relation commerciale locale ; il ne devient jamais automatiquement une identité GAMAD/DG Afrique.
- Un fournisseur futur issu de ZUMRA/Core doit être relié par référence explicite, pas dupliqué comme vérité transverse.
- Une relation ZUMRA ne devient jamais automatiquement une commande.
- Montants XOF en entiers ; aucun calcul monétaire par float.
- Quantités via l’arithmétique déterministe `Quantity` de LOT-001.
- Une réception de marchandise est transactionnelle, idempotente et auditée.
- Le stock augmente uniquement par `StockMovement`, jamais par modification silencieuse de `StockBalance`.
- Une réception ne peut jamais dépasser la quantité encore attendue.
- Une commande confirmée conserve ses snapshots même si produit/fournisseur changent ensuite.
- Paiement, stock et documents sont des preuves métier ; pas de suppression silencieuse.
- Aucun faux fournisseur, achat, paiement ou réception présenté comme réel.

## 4. Objectif visible

À la fin de LOT-002, un utilisateur autorisé doit pouvoir :

1. ouvrir **Acheter** depuis le shell ;
2. créer et retrouver un fournisseur ;
3. préparer une commande d’achat ;
4. ajouter des produits/services, quantités et coûts d’achat ;
5. confirmer la commande ;
6. voir clairement ce qui reste à réceptionner ;
7. réceptionner tout ou partie d’une commande ;
8. voir le stock augmenter exactement de la quantité reçue ;
9. terminer une commande en plusieurs réceptions ;
10. marquer une commande totalement reçue comme payée comptant ;
11. consulter le bon de commande et les bons de réception ;
12. retrouver les actions utiles depuis l’accueil sans dashboard KPI.

## 5. Hors périmètre LOT-002

- créances/dettes fournisseurs avancées ;
- paiements partiels ou échéanciers ;
- Mobile Money/Genius Pay fournisseur ;
- retours fournisseur ;
- avoirs fournisseur ;
- factures fiscales/comptabilité générale ;
- taxes complexes ;
- multi-devise ;
- négociation/devis fournisseur ;
- validation hiérarchique multi-niveaux ;
- fournisseur automatiquement découvert dans ZUMRA ;
- B2B inter-G-POS ;
- synchronisation réseau V2 ;
- catalogue fournisseur distant ;
- comparaison automatique de fournisseurs ;
- prévision IA ;
- valorisation moyenne/FIFO du stock ;
- rapprochement bancaire.

Ces sujets restent futurs.

## 6. Permissions commerciales

Étendre `CommercialPermission` avec :

- `VIEW_PURCHASES` — consulter achats et fournisseurs ;
- `MANAGE_PURCHASES` — créer/modifier/confirmer une commande et gérer les fournisseurs locaux ;
- `RECEIVE_PURCHASES` — confirmer une réception ;
- `PAY_PURCHASES` — enregistrer le paiement comptant d’un achat reçu.

`CommercialPermission::all()` doit inclure ces permissions.

Les vues et la navigation doivent masquer les actions impossibles quand la permission est connue.

Aucune de ces permissions n’accorde une autorité ZUMRA/GAMAD.

## 7. Fournisseur

Créer `Supplier` comme objet métier G-POS scoppé au contexte.

Champs minimaux :

- `id` UUID ;
- `context_id` ;
- `display_name` ;
- `contact_name` nullable ;
- `phone` nullable ;
- `email` nullable ;
- `notes` nullable ;
- `external_origin_type` nullable ;
- `external_origin_reference` nullable ;
- `active` bool ;
- timestamps.

### 7.1 Origine externe

Pour LOT-002, l’interface crée uniquement des fournisseurs locaux/manuels, donc `external_origin_*` restent null.

Ils préparent V2 sans prétendre qu’une intégration existe déjà.

Valeurs futures possibles, non activées par LOT-002 :

- `CORE_ORGANIZATION` ;
- `ZUMRA` ;
- `GPOS_CONTEXT`.

Si les deux champs externes sont renseignés, leur paire doit être cohérente et unique dans un même contexte.

### 7.2 Invariants fournisseur

- nom obligatoire ;
- un fournisseur d’un autre contexte n’est jamais lisible/modifiable ;
- aucune mention « vérifié » sans preuve réelle ;
- désactivation n’efface pas l’historique ;
- une commande passée conserve un snapshot du fournisseur.

## 8. Approvisionnement / seuil de réassort

Ajouter au produit un seuil optionnel :

- `reorder_threshold` decimal(14,3) nullable.

Il s’applique uniquement aux produits suivis en stock.

Quand renseigné, G-POS peut signaler factuellement :

> **Stock faible — X restant**

si `StockBalance.quantity <= reorder_threshold`.

Ce signal :

- ne crée jamais automatiquement une commande ;
- n’impose pas un fournisseur ;
- ne doit apparaître qu’aux acteurs autorisés ;
- peut proposer l’action **Préparer un achat**.

## 9. Commande d’achat

Créer `PurchaseOrder`.

Champs minimaux :

- `id` UUID ;
- `context_id` ;
- `supplier_id` ;
- `reference` nullable jusqu’à confirmation ;
- `status` ;
- `currency` = XOF ;
- `supplier_display_name_snapshot` nullable jusqu’à confirmation ;
- `subtotal_xof` entier ;
- `total_xof` entier ;
- `expected_on` nullable ;
- `note` nullable ;
- `created_by_core_reference` ;
- `ordered_by_core_reference` nullable ;
- `ordered_at` nullable ;
- `cancelled_by_core_reference` nullable ;
- `cancelled_at` nullable ;
- `confirmation_idempotency_key` nullable ;
- timestamps.

États LOT-002 :

- `DRAFT` ;
- `ORDERED` ;
- `PARTIALLY_RECEIVED` ;
- `RECEIVED` ;
- `CANCELLED`.

Libellés UI :

- Brouillon ;
- Commandée ;
- Réception partielle ;
- Reçue ;
- Annulée.

## 10. Ligne de commande

Créer `PurchaseOrderLine` :

- `id` UUID ;
- `purchase_order_id` ;
- `product_id` ;
- `product_name_snapshot` ;
- `unit_label_snapshot` ;
- `unit_cost_xof` entier ;
- `ordered_quantity` decimal(14,3) ;
- `received_quantity` decimal(14,3), défaut 0 ;
- `line_total_xof` entier ;
- `track_stock_snapshot` bool ;
- timestamps.

### 10.1 Invariants ligne

- produit et commande doivent appartenir au même contexte ;
- quantité commandée > 0 ;
- coût unitaire >= 0 ;
- calcul `coût × quantité` par arithmétique déterministe, sans float ;
- un même produit n’apparaît qu’une fois dans une commande LOT-002 ;
- `received_quantity <= ordered_quantity` ;
- lignes modifiables uniquement quand commande `DRAFT` ;
- après `ORDERED`, snapshots et quantités commandées sont immuables.

## 11. Service de brouillon

Créer un service explicite type `PurchaseOrderDraftService`.

Il doit :

- créer une commande DRAFT dans le contexte actif ;
- accepter uniquement un fournisseur du même contexte ;
- ajouter/modifier/supprimer les lignes ;
- recharger/verrouiller la commande avant toute mutation ;
- refuser mutation si statut différent de DRAFT ;
- recalculer les totaux côté serveur ;
- ne jamais accepter un total fourni par le navigateur.

Les protections de contexte doivent vivre aussi dans le service métier, pas seulement dans l’UI.

## 12. Confirmation de commande

Créer un service explicite type `ConfirmPurchaseOrder`.

Pseudo-flux :

1. acteur + contexte + `MANAGE_PURCHASES` ;
2. verrouiller/recharger la commande ;
3. vérifier le contexte actif ;
4. vérifier statut DRAFT ;
5. vérifier idempotence ;
6. verrouiller/recharger fournisseur/lignes nécessaires ;
7. exiger au moins une ligne ;
8. recalculer les totaux ;
9. snapshot du fournisseur ;
10. générer référence lisible via `CommercialContextSequence` ;
11. passer à `ORDERED` ;
12. émettre document `PURCHASE_ORDER` ;
13. écrire audit ;
14. commit transaction ;
15. retry avec même clé retourne le même résultat sans second document/référence.

Préfixe UI recommandé : `ACH-`.

Ajouter type de séquence : `PURCHASE_ORDER`.

## 13. Réception fournisseur

Créer `PurchaseReceipt` :

- `id` UUID ;
- `context_id` ;
- `purchase_order_id` ;
- `reference` ;
- `received_by_core_reference` ;
- `received_at` ;
- `note` nullable ;
- `idempotency_key` ;
- timestamps.

Une `PurchaseReceipt` créée par le service de confirmation est une preuve immutable ; pas de DRAFT caché nécessaire pour LOT-002.

Créer `PurchaseReceiptLine` :

- `id` UUID ;
- `purchase_receipt_id` ;
- `purchase_order_line_id` ;
- `product_id` ;
- `product_name_snapshot` ;
- `unit_label_snapshot` ;
- `quantity` decimal(14,3) ;
- `unit_cost_xof` entier ;
- `line_total_xof` entier ;
- `track_stock_snapshot` bool ;
- timestamps.

Préfixe recommandé pour réception : `BR-`.

Ajouter type de séquence : `GOODS_RECEIPT`.

## 14. Transaction `ReceivePurchaseOrder`

La réception est une opération de haute intégrité.

Pseudo-flux obligatoire :

1. acteur + contexte + `RECEIVE_PURCHASES` ;
2. verrouiller/recharger la commande ;
3. statut autorisé : `ORDERED|PARTIALLY_RECEIVED` ;
4. vérifier idempotence ;
5. verrouiller les lignes de commande concernées ;
6. chaque quantité reçue > 0 ;
7. chaque quantité reçue <= quantité restante ;
8. créer `PurchaseReceipt` ;
9. créer ses lignes snapshotées ;
10. pour chaque ligne suivie en stock : verrouiller/créer `StockBalance` ;
11. créer exactement un `StockMovement IN` par ligne de réception ;
12. augmenter le solde via `Quantity` ;
13. augmenter `received_quantity` de la ligne de commande ;
14. recalculer l’état : `PARTIALLY_RECEIVED` ou `RECEIVED` ;
15. émettre document `GOODS_RECEIPT` ;
16. audit `purchase.receipt_confirmed`, `stock.received`, `document.issued` ;
17. commit ;
18. retry idempotent retourne le même reçu/document sans double stock.

### 14.1 StockMovement

Étendre proprement `stock_movements` avec :

- `purchase_receipt_line_id` nullable FK.

Créer un index unique partiel lorsque non null afin qu’une ligne de réception ne puisse produire qu’un mouvement de stock.

Les mouvements de vente LOT-001 restent inchangés.

### 14.2 Produit sans suivi de stock

Une ligne de service ou produit `track_stock_snapshot=false` peut être réceptionnée au sens commercial mais ne crée aucun mouvement de stock.

## 15. Réception partielle

LOT-002 doit réellement supporter plusieurs réceptions.

Exemple :

- commandé : 10 sacs ;
- réception 1 : 6 → état `PARTIALLY_RECEIVED`, restant 4 ;
- réception 2 : 4 → état `RECEIVED`, restant 0.

Une tentative ultérieure de réception de 1 doit être refusée atomiquement.

L’UI doit afficher clairement **Reçu / Commandé / Restant**.

## 16. Annulation

Une commande peut être annulée :

- tant qu’elle est DRAFT ;
- ou après `ORDERED` seulement si aucune réception n’existe.

Une commande ayant une réception, même partielle, ne peut pas être annulée dans LOT-002.

L’annulation est un changement d’état audité ; ne pas supprimer la commande.

## 17. Paiement comptant simple

Réutiliser le domaine `Payment` de LOT-001 en le généralisant de manière contrôlée, plutôt que créer un second moteur financier parallèle.

### 17.1 Évolution `payments`

Ajouter :

- `purchase_order_id` nullable FK.

Rendre `sale_id` nullable pour permettre un paiement d’achat.

Invariant DB : exactement une source parmi :

- `sale_id` ;
- `purchase_order_id`.

Conserver :

- méthode `CASH` ;
- statut `CONFIRMED` ;
- montant entier ;
- acteur ;
- horodatage ;
- idempotency key.

Conserver l’unicité d’un paiement par vente LOT-001 et ajouter l’unicité d’un paiement par commande d’achat pour LOT-002.

### 17.2 Règle LOT-002

Le paiement fournisseur simple est volontairement borné :

- commande obligatoirement `RECEIVED` ;
- paiement unique ;
- montant = `PurchaseOrder.total_xof` ;
- méthode CASH ;
- aucun paiement partiel ;
- aucune dette/échéance ;
- aucun paiement avant réception complète dans ce lot.

Créer un service type `RecordCashPurchasePayment` avec `PAY_PURCHASES` et idempotence.

Si `total_xof = 0`, aucun paiement financier n’est nécessaire ; l’UI doit afficher un état neutre du type **Aucun montant à régler**.

## 18. Documents commerciaux

Réutiliser `CommercialDocument` ; ne pas créer une seconde infrastructure documentaire.

### 18.1 Évolution contrôlée

Ajouter sources nullable :

- `purchase_order_id` ;
- `purchase_receipt_id`.

Rendre `sale_id` nullable.

Types LOT-002 :

- `PURCHASE_ORDER` — bon de commande ;
- `GOODS_RECEIPT` — bon de réception.

Conserver `RECEIPT` pour les ventes LOT-001.

Invariant DB : exactement une source métier appropriée par document.

Créer des index uniques partiels afin de garantir structurellement :

- un reçu de vente par vente/type ;
- un bon de commande par commande/type ;
- un bon de réception par réception/type.

### 18.2 Snapshots

Bon de commande :

- contexte ;
- référence commande ;
- fournisseur snapshot ;
- lignes ;
- quantités ;
- coûts ;
- totaux ;
- date attendue ;
- date de commande.

Bon de réception :

- contexte ;
- commande source ;
- fournisseur snapshot ;
- référence réception ;
- lignes effectivement reçues ;
- coûts snapshots ;
- date de réception.

Modifier ensuite un produit ou fournisseur ne réécrit jamais ces documents.

## 19. Audit

Événements minimum :

- `supplier.created` ;
- `supplier.updated` si écran d’édition inclus ;
- `purchase.draft_created` ;
- `purchase.ordered` ;
- `purchase.cancelled` ;
- `purchase.receipt_confirmed` ;
- `stock.received` ;
- `purchase.payment_confirmed` ;
- `document.issued`.

Chaque événement sensible porte acteur, contexte, agrégat et référence de requête/idempotence lorsque pertinente.

## 20. UX — navigation

Le shell reste simple.

Navigation primaire LOT-002 recommandée :

- Accueil ;
- Vendre ;
- **Acheter** ;
- Produits ;
- Stock ;
- Documents.

`Acheter` n’apparaît que si l’acteur possède une permission achat pertinente.

Les fournisseurs vivent naturellement dans l’espace **Acheter**, avec accès secondaire **Fournisseurs** ; ne pas créer une navigation ERP surchargée.

## 21. UX — hub Acheter

Route conceptuelle : `/acheter`.

Ordre recommandé :

### A. À réceptionner

Commandes `ORDERED|PARTIALLY_RECEIVED` réellement accessibles.

### B. Action principale

**Nouvel achat**.

### C. Commandes récentes

Référence, fournisseur, statut, total, reçu/commandé si en cours.

### D. Fournisseurs

Lien discret **Gérer mes fournisseurs**.

État vide :

> **Aucun achat pour le moment.**  
> Ajoutez un fournisseur puis préparez votre première commande.

## 22. UX — création d’achat

Parcours court :

1. choisir/créer fournisseur ;
2. ajouter produits/services ;
3. saisir quantité ;
4. saisir coût unitaire ;
5. voir total immédiatement ;
6. date attendue facultative ;
7. confirmer la commande.

Pas de formulaire comptable intimidant.

Un bouton **Préparer un achat** depuis un produit/stock faible peut préremplir le produit, sans choisir automatiquement le fournisseur ni confirmer quoi que ce soit.

## 23. UX — réception

Écran orienté action :

> **Que venez-vous de recevoir ?**

Pour chaque ligne :

- Commandé ;
- Déjà reçu ;
- Restant ;
- Reçu maintenant.

Le défaut peut proposer la quantité restante, mais l’utilisateur confirme explicitement.

Après confirmation :

- message clair ;
- stock mis à jour ;
- statut commande ;
- bon de réception accessible.

## 24. UX — paiement

Quand commande totalement reçue et acteur `PAY_PURCHASES` :

> **Paiement fournisseur**  
> Montant : 25 000 F CFA  
> [Marquer payé comptant]

Confirmation explicite avant écriture financière.

Après paiement : **Payé comptant** + date.

Ne pas afficher « en retard », « dette » ou échéancier dans LOT-002.

## 25. Accueil G-POS

Conserver la philosophie LOT-001 : pas de mur de KPI.

`À faire maintenant` peut désormais inclure, selon permissions :

- commande à réceptionner ;
- commande partiellement reçue ;
- produit sous son seuil de réassort ;
- commande reçue à marquer payée si l’acteur peut payer.

Maximum 3 éléments réellement prioritaires.

Ne jamais afficher à un acteur une action qu’il ne peut pas exécuter.

## 26. Isolation multi-contexte

Toutes les queries de :

- Supplier ;
- PurchaseOrder ;
- PurchaseOrderLine via son ordre ;
- PurchaseReceipt ;
- PurchaseReceiptLine ;
- Payment achat ;
- Document achat ;
- StockMovement achat

doivent être bornées/revalidées par le contexte actif.

Les IDs Livewire/URL sont des entrées non fiables : verrouiller les propriétés sensibles et revalider côté service.

Aucun service métier ne doit dépendre uniquement d’un middleware pour l’isolation.

## 27. Concurrence / idempotence

Obligatoire pour :

- confirmation de commande ;
- réception ;
- mouvement stock issu de réception ;
- paiement achat ;
- émission des documents numérotés.

Ordre de verrou recommandé pour réception :

1. PurchaseOrder ;
2. PurchaseOrderLines ;
3. StockBalances ;
4. séquences/documents dans leur mécanisme existant.

Deux réceptions concurrentes ne doivent jamais pouvoir dépasser la quantité commandée.

## 28. Tests d’acceptation métier

Au minimum :

1. fournisseur isolé par contexte ;
2. acteur sans `MANAGE_PURCHASES` ne crée/confirme pas ;
3. fournisseur d’un autre contexte refusé ;
4. produit d’un autre contexte refusé ;
5. coût XOF entier et calcul sans float ;
6. DRAFT modifiable ;
7. commande confirmée immutable ;
8. confirmation idempotente : une référence/un document ;
9. réception partielle crée exactement les mouvements IN attendus ;
10. quantité restante correcte ;
11. réception finale passe la commande à RECEIVED ;
12. sur-réception refusée atomiquement ;
13. retry réception ne double jamais le stock ;
14. deux réceptions concurrentes ne dépassent pas le commandé ;
15. service/non-stock ne crée pas de mouvement ;
16. paiement avant réception complète refusé ;
17. paiement après réception complète = total exact, une fois ;
18. retry paiement ne double pas l’écriture ;
19. documents conservent snapshots après modification fournisseur/produit ;
20. commande avec réception ne peut être annulée ;
21. contexte suspendu refuse commande/réception/paiement ;
22. audit contient acteur/contexte/agrégat ;
23. seuil de réassort est contextuel et ne crée aucune commande automatique ;
24. ventes LOT-001 continuent de fonctionner sans régression.

## 29. Tests UX/HTTP

Au minimum :

- `Acheter` masqué si aucune permission pertinente ;
- hub Acheter état vide ;
- création fournisseur ;
- création commande ;
- ajout produit + coût + quantité ;
- confirmation commande ;
- affichage bon de commande ;
- réception partielle ;
- réception finale ;
- affichage bon de réception ;
- paiement comptant autorisé au bon moment ;
- erreurs de sur-réception lisibles ;
- cross-context inaccessible ;
- accueil affiche une vraie réception à faire seulement pour acteur autorisé ;
- build responsive sans erreur JS.

## 30. Migrations et compatibilité LOT-001

LOT-002 doit utiliser des migrations additives/évolutives. Ne réécrire aucune ancienne migration déjà fusionnée.

Les évolutions de `payments`, `commercial_documents`, `stock_movements`, `products` doivent conserver toutes les données et relations LOT-001.

Avant PR, tester explicitement :

- migration depuis un schéma LOT-001 existant ;
- `migrate:fresh` ;
- anciens tests ventes ;
- nouveaux tests achats.

## 31. CI / qualité

La CI actuelle doit rester verte et exécuter réellement :

- build frontend avant tests de page si requis par le manifest ;
- migrations PostgreSQL ;
- suite complète PHP ;
- build frontend.

Ne pas refaire la CI au-delà de ce qui est nécessaire.

Validation locale :

```bash
php8.4 artisan test
./vendor/bin/pint --test
npm run build
git status --short
```

## 32. Définition de terminé

LOT-002 est CLOSED lorsque :

- fournisseur local fonctionnel ;
- commande d’achat réelle ;
- réception partielle et totale réelle ;
- stock IN cohérent et idempotent ;
- paiement CASH simple cohérent ;
- documents achat snapshotés ;
- audit présent ;
- UX Acheter belle, tactile et simple ;
- accueil montre les vraies actions d’approvisionnement ;
- isolation multi-contexte testée ;
- régression LOT-001 absente ;
- CI verte ;
- fusion sur `main` après une revue principale unique.

## 33. Règle de vitesse

**Product breadth first, hardening later.**

Bloquer LOT-002 uniquement pour :

- perte/corruption de données ;
- isolation contexte cassée ;
- identité parallèle ;
- calcul financier non déterministe ;
- sur-réception possible ;
- double stock/paiement/document ;
- migration cassée ;
- parcours Acheter/Réception inutilisable.

Le reste va en HARDENING / POST-BETA.

## 34. Contrat de conflit

La fiche d’implémentation est un contrat, pas une source d’inspiration.

Si le code LOT-001, la doctrine G-POS, le corpus historique ou une contrainte réelle contredisent cette fiche, ne pas trancher silencieusement : documenter le conflit et arrêter uniquement la partie structurellement concernée pour revue.
