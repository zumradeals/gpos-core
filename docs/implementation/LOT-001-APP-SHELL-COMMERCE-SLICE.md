# G-POS — LOT-001 — Fondation applicative + premier parcours commercial

**Statut : READY FOR IMPLEMENTATION**  
**Date : 18 août 2026**

## 1. Intention

Construire le premier vrai visage de G-POS et prouver le noyau transactionnel sur un parcours vertical cohérent :

**Contexte commercial → Catalogue → Vente → Paiement → Stock → Document → Journal**

Le lot doit rendre G-POS immédiatement reconnaissable comme produit, sans chercher à implémenter toute la V1 historique.

## 2. Doctrine inviolable

- ZUMRA organise les humains. G-POS organise leur activité économique.
- G-POS ne crée aucun compte humain canonique parallèle.
- GAMAD Core reste autorité d’identité/session transverse.
- DG Afrique/ZUMRA reste autorité sociale et communautaire.
- Une relation sociale ne devient jamais automatiquement une transaction.
- Toute mutation commerciale sensible est scoppée par contexte commercial et acteur canonique.
- Montants monétaires : entiers, jamais float.
- Stock et finance : transactionnels, auditables, idempotents lorsque critique.
- Aucun faux contenu métier présenté comme réel.

## 3. Stack retenue

Pour LOT-001 :

- Laravel 13 ;
- PHP 8.4 ;
- PostgreSQL ;
- Redis prêt pour jobs/cache, sans complexifier le premier slice ;
- Vite ;
- Blade + Livewire + Alpine pour les interactions du shell et du parcours vente ;
- CSS/tokens maison ou utilitaires sobres, sans dépendre d’un thème ERP ;
- PHPUnit / tests Feature + Unit ;
- Nginx en cible d’exploitation.

La couche métier ne doit jamais dépendre de Livewire. L’UI pourra évoluer vers une couche offline plus riche sans réécrire le domaine.

## 4. Objectif visible

À la fin du lot, l’application doit permettre de voir et utiliser :

1. un shell G-POS responsive et beau ;
2. un contexte commercial actif ;
3. un accueil centré sur « À faire maintenant » et actions rapides ;
4. un catalogue minimal de produits ;
5. une vente simple mobile/tablette ;
6. un paiement comptant ;
7. une décrémentation de stock transactionnelle ;
8. un reçu commercial snapshoté ;
9. un journal/audit de l’opération ;
10. des états vides propres quand aucune donnée n’existe.

## 5. Hors périmètre LOT-001

- réseau commercial V2 ;
- B2B inter-ZUMRA ;
- Genius Pay / Mobile Money réel ;
- achats fournisseurs complets ;
- livraisons avancées ;
- créances avancées ;
- retours/remboursements ;
- inventaire complet ;
- multi-sites avancé ;
- mode offline complet ;
- G-Market ;
- rapports avancés ;
- IA ;
- comptabilité générale.

Les modèles peuvent anticiper proprement l’extension, mais aucun faux module ne doit être affiché comme terminé.

## 6. Identité et accès

### 6.1 Aucun User local canonique

Ne pas créer de table `users` métier ni de login email/mot de passe G-POS.

### 6.2 Adaptateur d’identité

Créer un contrat applicatif du type :

- `CurrentActor`
- `CoreIdentityReference`
- `CoreSessionGateway` ou équivalent

Le code métier reçoit une référence canonique stable, jamais un `User` Laravel local comme autorité.

### 6.3 Mode développement

Tant que la fédération GAMAD Core n’est pas branchée, un acteur de développement peut être injecté uniquement en `local/testing` via configuration explicite, par exemple :

`GPOS_DEV_CORE_IDENTITY_REFERENCE=IDN-PER-...`

Il doit être impossible de l’activer silencieusement en environnement de production.

## 7. Contexte commercial

Créer `CommercialContext` comme racine de scope métier.

Champs minimaux :

- id UUID/ULID ;
- external_origin_type nullable (`ZUMRA`, `ORGANIZATION`, `STANDALONE` ou registre équivalent fermé) ;
- external_origin_reference nullable ;
- display_name ;
- currency = XOF pour le premier lot ;
- timezone ;
- status ACTIVE/SUSPENDED ;
- created_at / updated_at.

L’app doit avoir un `ActiveCommercialContext` explicite.

Toute query de Produit, Vente, Stock, Paiement, Document et Audit doit être scoppée à ce contexte.

## 8. Rôles commerciaux locaux

LOT-001 peut commencer avec permissions métier minimales, sans sur-concevoir :

- `SELL` ;
- `MANAGE_CATALOG` ;
- `VIEW_STOCK` ;
- `ADJUST_STOCK` réservé aux futurs écrans ;
- `VIEW_DOCUMENTS` ;
- `VIEW_AUDIT`.

La personne reçoit ces capacités dans un contexte commercial ; ces rôles ne deviennent jamais une autorité ZUMRA ou GAMAD.

## 9. Catalogue minimal

Entité `Product` :

- context_id ;
- name ;
- sku nullable ;
- barcode nullable ;
- kind `PRODUCT|SERVICE` ;
- sale_price_xof entier ;
- track_stock bool ;
- active bool ;
- unit_label ;
- timestamps.

Contraintes :

- prix >= 0 ;
- SKU/barcode uniques dans le contexte lorsqu’ils sont présents ;
- un service peut ne pas suivre le stock.

UX :

- liste visuelle simple ;
- recherche immédiate ;
- ajout produit sans formulaire intimidant ;
- empty state : « Aucun produit pour le moment » + action claire.

## 10. Stock minimal

Deux niveaux :

### `StockBalance`

Projection courante par contexte/produit.

### `StockMovement`

Journal métier immutable :

- direction `IN|OUT|ADJUSTMENT` ;
- quantity decimal avec précision définie ;
- reason ;
- source_type/source_reference ;
- actor_core_reference ;
- occurred_at ;
- idempotency_key nullable/unique selon opération.

Une vente confirmée d’un produit suivi génère un mouvement OUT.

Ne jamais simplement modifier un nombre sans mouvement source.

LOT-001 peut initialiser du stock via une action de bootstrap/admin de développement clairement séparée de la vente, ou via un écran minimal « Ajuster le stock » si temps raisonnable.

## 11. Vente

### 11.1 Entités

`Sale`

- context_id ;
- reference lisible ;
- status `DRAFT|CONFIRMED|CANCELLED` pour LOT-001 ;
- subtotal_xof ;
- discount_xof ;
- total_xof ;
- currency XOF ;
- created_by_core_reference ;
- confirmed_by_core_reference nullable ;
- confirmed_at nullable ;
- client_reference nullable ;
- idempotency_key de confirmation.

`SaleLine`

- sale_id ;
- product_id nullable si snapshot futur nécessaire ;
- product_name_snapshot ;
- unit_label_snapshot ;
- unit_price_xof ;
- quantity decimal ;
- line_total_xof ;
- track_stock_snapshot.

### 11.2 Invariants

- vente DRAFT modifiable ;
- confirmation atomique ;
- confirmation calcule les totaux côté serveur ;
- prix et nom sont snapshotés ;
- une vente confirmée ne se réécrit pas silencieusement ;
- double clic / retry ne crée pas deux ventes confirmées ni deux mouvements stock ;
- quantité positive ;
- stock insuffisant : règle LOT-001 = refuser la confirmation pour produit suivi, avec message humain clair ;
- service non stocké ne crée aucun mouvement.

## 12. Paiement LOT-001

Support initial : `CASH` uniquement, mais modèle extensible.

`Payment` :

- context_id ;
- sale_id ;
- method `CASH` ;
- amount_xof ;
- status `CONFIRMED` ;
- actor_core_reference ;
- paid_at ;
- idempotency_key.

Pour le premier parcours :

- une vente comptant simple doit être entièrement couverte ;
- pas de paiement partiel dans LOT-001 ;
- total payé = total vente ;
- paiement + vente + stock doivent être cohérents transactionnellement.

## 13. Document / reçu

Créer `CommercialDocument` ou `Receipt` :

- context_id ;
- sale_id ;
- document_type `RECEIPT` ;
- number/reference ;
- snapshot JSON minimal de la vérité commerciale au moment de l’émission ;
- issued_at ;
- issued_by_core_reference.

Le rendu doit permettre :

- affichage mobile ;
- impression navigateur ;
- partage futur ;
- aucune dépendance au produit courant pour reconstruire le passé.

## 14. Audit

Créer `CommercialAuditEvent` append-only :

- context_id ;
- actor_core_reference ;
- event_type ;
- aggregate_type ;
- aggregate_reference ;
- before_state minimal nullable ;
- after_state minimal nullable ;
- metadata filtrée ;
- occurred_at ;
- request/idempotency reference lorsque pertinent.

Événements LOT-001 minimum :

- product.created ;
- stock.adjusted ou stock.initialized ;
- sale.draft_created ;
- sale.confirmed ;
- payment.confirmed ;
- document.issued.

Ne jamais stocker de secret dans metadata.

## 15. Transaction de confirmation

Créer un service applicatif explicite, ex. `ConfirmCashSale`.

Pseudo-flux :

1. vérifier acteur + contexte + permission SELL ;
2. verrouiller/recharger la vente ;
3. vérifier idempotence ;
4. recalculer totaux ;
5. verrouiller balances de stock concernées ;
6. vérifier disponibilité ;
7. confirmer la vente ;
8. créer mouvements stock ;
9. créer paiement CASH ;
10. créer reçu snapshoté ;
11. créer audit events ;
12. commit transaction ;
13. retourner résultat stable si retry avec même idempotency key.

## 16. Shell UX

### Navigation primaire desktop/tablette

- Accueil
- Vendre
- Achats (visible comme « bientôt » seulement si le design le justifie ; sinon ne pas afficher)
- Commandes (idem)
- Stock
- Livraisons (idem)
- Caisse (surface minimale ou future)

Pour LOT-001, ne pas créer de faux écrans vides juste pour remplir la navigation. Préférer une navigation progressive :

- Accueil
- Vendre
- Produits
- Stock
- Documents

Puis élargir dans les lots suivants.

### Mobile

Barre inférieure simple, action `Vendre` extrêmement visible.

## 17. Accueil

Pas de dashboard KPI.

Structure :

1. contexte actif ;
2. salutation sobre ;
3. « À faire maintenant » maximum 3 éléments réels ;
4. actions rapides : Vendre, Ajouter un produit, Stock ;
5. activité récente utile ;
6. états vides élégants si aucune donnée.

Aucune donnée fictive de chiffre d’affaires.

## 18. Vente mobile — benchmark

Écran principal :

- recherche produit autofocus ;
- cartes/lignes produits tactiles ;
- panier visible sans navigation lourde ;
- QuantityStepper ;
- total toujours visible ;
- bouton principal `Encaisser` ;
- paiement CASH ;
- confirmation ;
- reçu.

Objectif : vente simple en quelques gestes.

Les contrôles tactiles doivent être confortables et accessibles.

## 19. Design language LOT-001

Conserver la direction déjà canonisée :

- fond ivoire très clair ;
- surfaces blanc chaud ;
- ancrage vert profond / pétrole ;
- cuivre/terre pour action commerciale ;
- safran pour attente ;
- rouge seulement erreur/destructif ;
- typographie chaude pour titres, sans-serif très lisible pour interface ;
- chiffres tabulaires pour monnaie.

Créer des tokens CSS explicites plutôt que disperser des valeurs arbitraires.

Composants prioritaires :

- AppShell ;
- ContextSwitcher ;
- ActionCard ;
- ProductPicker ;
- QuantityStepper ;
- MoneyDisplay/MoneyField ;
- StatusPill ;
- EmptyState ;
- ReceiptView ;
- SyncState placeholder non trompeur.

## 20. États UX obligatoires

Chaque écran principal doit prévoir :

- loading ;
- empty ;
- error ;
- permission denied ;
- contexte suspendu ;
- stock insuffisant ;
- retry idempotent ;
- absence de contexte actif.

Pas de spinner éternel.

## 21. Responsive

Tester explicitement :

- mobile ~360/390 px ;
- tablette ~768/1024 ;
- desktop ≥ 1280.

La vente doit être mobile-first ; le reste doit rester excellent sur tablette/desktop.

## 22. Tests d’acceptation métier

Au minimum :

1. impossible d’accéder aux produits d’un autre contexte ;
2. acteur sans SELL ne confirme pas une vente ;
3. création produit respecte le contexte ;
4. prix monétaire est entier ;
5. vente DRAFT calcule ses lignes côté serveur ;
6. confirmation cash crée exactement une vente confirmée ;
7. paiement créé une fois ;
8. stock décrémenté une fois ;
9. retry même idempotency key n’effectue pas un second mouvement ;
10. stock insuffisant refuse toute la transaction ;
11. service ne touche pas au stock ;
12. reçu conserve les snapshots même si produit modifié ensuite ;
13. audit contient acteur/contexte/agrégat ;
14. contexte suspendu refuse mutation ;
15. dev identity impossible en production.

## 23. Tests UX/HTTP

- accueil accessible avec contexte ;
- absence contexte = écran explicite ;
- catalogue empty state ;
- création produit ;
- parcours vendre → encaisser → reçu ;
- erreurs validation lisibles ;
- permission adaptée ;
- responsive build sans erreur JS.

## 24. Qualité

Avant PR :

- `php artisan test` vert ;
- format/lint PHP selon tooling installé ;
- `npm run build` vert ;
- migrations fraîches PostgreSQL vertes ;
- aucun secret ;
- `.env.example` documenté ;
- README avec bootstrap local ;
- CI GitHub minimale pour test + build si raisonnable dès ce lot.

## 25. Définition de terminé

LOT-001 est CLOSED lorsque :

- l’application est réellement bootstrapée ;
- le shell est visuellement cohérent ;
- un contexte commercial peut être activé ;
- un produit peut être créé ;
- un stock existe ;
- une vente CASH peut être confirmée de bout en bout ;
- paiement, stock, reçu et audit sont cohérents ;
- les tests critiques sont verts ;
- aucun compte humain parallèle n’a été créé ;
- le code est fusionné sur main après une revue principale unique.

## 26. Règle d’exécution

**Product breadth first, hardening later.**

Ne bloquer LOT-001 que pour : perte de données, isolation multi-contexte cassée, identité parallèle, mutation finance/stock non transactionnelle, migration cassée, idempotence critique absente, parcours Vente inutilisable.

Les raffinements secondaires, optimisation, offline avancé, accessibilité fine et observabilité avancée vont en HARDENING / POST-BETA.

## 27. Contrat de conflit

La fiche d’implémentation est un contrat, pas une source d’inspiration.

Si le code, la doctrine, le corpus historique ZUMRA V1 ou le comportement réel de GAMAD Core contredisent cette fiche, ne pas trancher silencieusement : documenter le conflit et arrêter la partie concernée pour revue.
