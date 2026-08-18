# G-POS — LOT-003 — Caisse & clôture

**Statut : READY FOR IMPLEMENTATION**  
**Date : 18 août 2026**  
**Base : `main` après LOT-002 (`862a9cbb7f8d4c2f06e96c39012512aa15551a79`)**

## 1. Intention

Donner à G-POS une vérité opérationnelle sur les espèces confiées à une personne pendant une session de caisse :

**Caisse → Ouverture → Fonds initial → Flux CASH des ventes/achats → Mouvements manuels autorisés → Attendu → Comptage réel → Écart motivé → Clôture → Preuve → Audit.**

LOT-003 ne transforme pas G-POS en logiciel comptable. Il relie simplement les paiements CASH déjà réels de LOT-001/LOT-002 à une caisse opérationnelle responsable et clôturable.

Formule produit :

> **La vente dit ce qui a été vendu. Le paiement dit ce qui a été reçu ou payé. La caisse dit où les espèces ont été confiées. La clôture compare ce qui devrait être présent à ce qui est réellement compté.**

## 2. Héritage et doctrine

LOT-003 dérive de :

- `docs/G-POS-DOCTRINE.md` ;
- `docs/architecture/SATELLITE-CONTRACT.md` ;
- `docs/product/DESIGN-DIRECTION.md` ;
- `docs/legacy/ZUMRA-V1-TO-GPOS.md` ;
- `docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md` ;
- `docs/implementation/LOT-002-PURCHASING-SUPPLY.md` ;
- le code réellement fusionné de LOT-001 et LOT-002 ;
- la matière historique `CAP-ZUMRA-012 — Caisse et clôture` du corpus ZUMRA V1.

Le CAP historique est beaucoup plus large que ce lot : transferts, dépôts intermédiaires, caisse mobile, rapprochement non-espèces, clôture aveugle, approbation, offline, multi-site et clôture consolidée sont explicitement différés.

## 3. Problème à résoudre

Aujourd’hui LOT-001 et LOT-002 savent enregistrer un paiement `CASH`, mais G-POS ne sait pas encore répondre de façon opérationnelle à :

- dans quelle caisse physique/logique se trouvent les espèces ;
- qui en est responsable ;
- avec quel fonds la session a commencé ;
- quelles ventes ont fait entrer des espèces ;
- quels achats fournisseurs ont fait sortir des espèces ;
- quels mouvements manuels autorisés ont affecté la caisse ;
- combien devrait être présent ;
- combien a réellement été compté ;
- quel écart existe et pourquoi ;
- quand la session a été clôturée.

LOT-003 doit répondre à ces questions sans confondre chiffre d’affaires, paiement et solde de caisse.

## 4. Invariants inviolables

- G-POS ne crée aucune identité humaine canonique parallèle.
- Toute caisse, session et mouvement est scoppé par `CommercialContext`.
- Une session ouverte possède toujours un responsable Core identifiable.
- Une caisse active ne peut avoir qu’une seule session `OPEN` à la fois.
- Un même acteur ne peut avoir qu’une seule session `OPEN` dans un même contexte LOT-003.
- Le fonds initial n’est ni une vente ni un revenu.
- Un paiement CASH confirmé post-LOT-003 doit produire exactement un mouvement de caisse correspondant.
- Une vente CASH produit une entrée de caisse.
- Un achat fournisseur CASH produit une sortie de caisse.
- Les paiements non-CASH futurs ne doivent jamais modifier une caisse espèces par défaut.
- Aucun nouveau paiement CASH ne peut être confirmé sans session de caisse ouverte et autorisée pour l’acteur.
- Une sortie CASH ne peut pas faire devenir le solde attendu négatif.
- Les montants XOF sont des entiers ; jamais de float.
- Le montant attendu est dérivé des mouvements immuables, pas stocké comme vérité mutable pendant la session.
- Les mouvements ne sont jamais supprimés ou réécrits silencieusement.
- Un retry ne crée jamais un double mouvement.
- Une session clôturée n’accepte plus aucun mouvement.
- Le réel compté est un montant explicitement saisi par un humain autorisé.
- Un écart ne disparaît jamais : s’il est non nul, une justification est obligatoire.
- Une clôture finalisée est immuable.
- Les documents sont des snapshots ; modifier une caisse ou un contexte plus tard ne réécrit pas une clôture historique.
- Aucun faux montant, mouvement, caisse ou clôture ne doit être présenté comme réel.

## 5. Objectif visible

À la fin de LOT-003, un utilisateur autorisé doit pouvoir :

1. ouvrir **Caisse** ;
2. créer une caisse si le contexte n’en possède aucune et s’il a `MANAGE_CASH` ;
3. ouvrir sa session avec un fonds initial ;
4. vendre au comptant et voir automatiquement l’entrée CASH dans sa session ;
5. payer un achat fournisseur au comptant et voir automatiquement la sortie CASH ;
6. enregistrer une entrée ou sortie manuelle justifiée ;
7. voir le solde attendu dérivé ;
8. voir les mouvements récents ;
9. cliquer **Clôturer ma caisse** ;
10. saisir le montant réellement compté ;
11. fournir une justification si un écart existe ;
12. confirmer la clôture ;
13. obtenir une preuve de clôture ;
14. retrouver les sessions récemment clôturées ;
15. être empêché d’enregistrer une nouvelle opération CASH tant qu’il n’a pas ouvert une nouvelle session.

## 6. Hors périmètre LOT-003

- comptabilité générale ;
- plan comptable ;
- journal comptable légal ;
- caisse centrale complexe ;
- transferts entre caisses ;
- dépôts intermédiaires / retraits de sécurité ;
- remise de fonds après clôture ;
- caisse mobile livreur ;
- équipe partageant une même session ;
- changement de responsable en cours de session ;
- approbation hiérarchique d’un écart ;
- clôture aveugle ;
- détail des coupures ;
- comptages intermédiaires ;
- remboursements / retours (lot futur) ;
- dépenses structurées (un mouvement manuel OUT reste possible avec motif) ;
- rapprochement Mobile Money ;
- rapprochement bancaire ;
- chèques ;
- multi-devise ;
- offline ;
- clôture consolidée de journée ;
- multi-site avancé ;
- fiscalité ;
- réouverture d’une clôture ;
- correction destructive d’une clôture.

## 7. Permissions commerciales

Étendre `CommercialPermission` avec :

- `VIEW_CASH` — consulter ses caisses/sessions autorisées ;
- `OPERATE_CASH` — ouvrir sa session et enregistrer des mouvements CASH ;
- `CLOSE_CASH` — clôturer sa propre session ;
- `MANAGE_CASH` — créer/suspendre une caisse et agir comme autorité de gestion locale.

`CommercialPermission::all()` doit inclure ces permissions.

### 7.1 Composition avec les permissions existantes

- confirmer une vente CASH exige toujours `SELL` et, via la couche caisse, une session ouverte opérable ;
- payer un achat fournisseur CASH exige toujours `PAY_PURCHASES` et une session ouverte opérable ;
- `MANAGE_CASH` ne donne pas `SELL` ;
- `OPERATE_CASH` ne donne pas `PAY_PURCHASES` ;
- aucune permission G-POS n’accorde une autorité ZUMRA/GAMAD.

## 8. CashRegister — Caisse

Créer `CashRegister`.

Champs minimaux :

- `id` UUID ;
- `context_id` ;
- `name` ;
- `code` nullable ;
- `status` : `ACTIVE | SUSPENDED` ;
- `created_by_core_reference` ;
- timestamps.

### 8.1 Invariants caisse

- appartient à un seul contexte ;
- nom obligatoire ;
- code unique par contexte quand renseigné ;
- une caisse suspendue ne peut pas ouvrir de nouvelle session ;
- suspension ne supprime jamais l’historique ;
- une caisse avec session ouverte ne doit pas être suspendue silencieusement ;
- aucune suppression physique via l’UX LOT-003.

## 9. CashSession — Session de caisse

Créer `CashSession`.

Champs minimaux :

- `id` UUID ;
- `context_id` ;
- `cash_register_id` ;
- `reference` ;
- `status` : `OPEN | CLOSED | CLOSED_WITH_VARIANCE` ;
- `responsible_core_reference` ;
- `opening_amount_xof` entier ;
- `opened_at` ;
- `opened_by_core_reference` ;
- `counted_amount_xof` nullable ;
- `expected_amount_xof_snapshot` nullable ;
- `variance_xof` signed bigint nullable ;
- `variance_reason` nullable ;
- `closed_at` nullable ;
- `closed_by_core_reference` nullable ;
- `closure_idempotency_key` nullable ;
- timestamps.

### 9.1 Référence

Ajouter une séquence lisible, par exemple :

`CAI-1`, `CAI-2`, ...

Le format exact doit suivre `CommercialContextSequence` et rester unique par contexte.

### 9.2 Garanties structurelles

PostgreSQL doit empêcher :

- deux sessions `OPEN` sur la même caisse ;
- deux sessions `OPEN` pour le même `responsible_core_reference` dans le même contexte ;
- une `closure_idempotency_key` dupliquée quand non nulle.

Utiliser des index uniques partiels appropriés.

## 10. Ouverture de session

Créer un service explicite, par exemple :

`OpenCashSession`

Entrées :

- caisse ;
- acteur ;
- `opening_amount_xof` ;
- `idempotency_key`.

### 10.1 Conditions

- contexte actif ;
- `OPERATE_CASH` ;
- caisse du contexte actif ;
- caisse `ACTIVE` ;
- montant entier >= 0 ;
- aucune session ouverte pour cette caisse ;
- aucune autre session ouverte pour cet acteur dans le contexte.

### 10.2 Transaction

La transaction doit :

1. verrouiller la caisse ;
2. revalider les contraintes ;
3. créer la session `OPEN` ;
4. créer un mouvement `OPENING_FLOAT` IN du montant initial si > 0 ;
5. audit `cash.session_opened` ;
6. commit.

Un fonds initial `0` est valide. Il peut être représenté sans mouvement zéro ; la session conserve néanmoins `opening_amount_xof = 0`.

## 11. CashMovement — Registre immuable de caisse

Créer `CashMovement`.

Champs minimaux :

- `id` UUID ;
- `context_id` ;
- `cash_session_id` ;
- `payment_id` nullable ;
- `direction` : `IN | OUT` ;
- `movement_type` ;
- `amount_xof` entier strictement > 0 ;
- `reason` nullable selon type ;
- `source_type` nullable ;
- `source_reference` nullable ;
- `actor_core_reference` ;
- `occurred_at` ;
- `idempotency_key` ;
- timestamps.

Types LOT-003 :

- `OPENING_FLOAT` ;
- `SALE_PAYMENT` ;
- `PURCHASE_PAYMENT` ;
- `MANUAL_IN` ;
- `MANUAL_OUT`.

### 11.1 Garanties DB

- `idempotency_key` unique ;
- `payment_id` unique quand non null : un paiement CASH confirmé = un seul mouvement de caisse ;
- un seul `OPENING_FLOAT` par session ;
- direction cohérente avec type :
  - opening/sale/manual_in = IN ;
  - purchase/manual_out = OUT.

### 11.2 Immutabilité

Aucune route/service LOT-003 ne modifie ou supprime un mouvement confirmé.

Une future correction devra créer un mouvement compensatoire, jamais réécrire l’original.

## 12. Solde attendu

Créer un calcul central, par exemple `CashBalanceCalculator`.

Formule :

`attendu = somme(IN) - somme(OUT)`

Le fonds initial est déjà représenté par `OPENING_FLOAT` quand il est > 0.

Le calcul :

- utilise uniquement des entiers ;
- ne fait jamais confiance à une valeur venant du navigateur ;
- se fait côté serveur ;
- est recalculable depuis le ledger ;
- ne dépend pas du chiffre d’affaires ;
- ne mélange pas paiement non-CASH.

Le montant attendu ne doit jamais devenir négatif.

## 13. Rattachement des paiements CASH existants

LOT-003 ne crée pas un nouveau moteur `Payment`.

Il réutilise `Payment` de LOT-001/LOT-002 et relie chaque nouveau paiement CASH confirmé à `CashMovement`.

### 13.1 Vente CASH

Modifier de façon bornée `ConfirmCashSale` :

- conserver toute l’intégrité LOT-001 ;
- après/avec la création du `Payment` CASH, dans la même transaction, appeler la couche caisse ;
- trouver la session `OPEN` du même acteur dans le même contexte ;
- exiger `OPERATE_CASH` ;
- créer exactement un `SALE_PAYMENT` IN pour `payment.amount_xof` ;
- lier `payment_id` ;
- si aucune session ouverte : refuser la confirmation avec une erreur métier claire et rollback complet de la confirmation de vente.

Aucune vente ne doit être partiellement confirmée si l’écriture caisse échoue.

### 13.2 Achat fournisseur CASH

Modifier de façon bornée `RecordCashPurchasePayment` :

- conserver toute l’intégrité LOT-002 ;
- exiger la session `OPEN` du même acteur/contexte ;
- exiger `OPERATE_CASH` ;
- vérifier que l’attendu disponible est >= au paiement ;
- créer exactement un `PURCHASE_PAYMENT` OUT ;
- lier `payment_id` ;
- si solde insuffisant : refuser et rollback complet du paiement fournisseur.

### 13.3 Pas de backfill historique

Les paiements CASH créés avant LOT-003 ne sont pas rétroactivement affectés à une session future.

Ils restent des paiements historiques valides, mais ne doivent pas être inventés comme mouvements d’une caisse qui n’existait pas encore.

## 14. Résolution de la session active

Créer une abstraction explicite, par exemple :

`CurrentCashSession`

ou

`CashSessionResolver`.

Pour LOT-003, la règle est simple :

> le paiement CASH est rattaché à l’unique session `OPEN` dont `responsible_core_reference` correspond à l’acteur courant et `context_id` au contexte courant.

Pas de choix automatique d’une caisse appartenant à quelqu’un d’autre.

Pas de session ouverte = pas de paiement CASH confirmé.

## 15. Mouvements manuels

Créer une action/service explicite :

`RecordManualCashMovement`

L’UX propose :

- **Entrée manuelle** ;
- **Sortie manuelle**.

Conditions :

- `OPERATE_CASH` ;
- session `OPEN` de l’acteur ;
- montant entier > 0 ;
- motif obligatoire, non vide ;
- idempotence ;
- contexte revalidé côté service.

Pour une sortie :

- solde attendu après sortie >= 0.

Le mouvement manuel ne doit jamais être présenté comme une vente ou un achat fournisseur.

## 16. Concurrence et ordre de verrouillage

Les flux CASH et la clôture doivent être sérialisés.

Règle recommandée :

- les services vente/achat verrouillent leur agrégat métier existant, puis verrouillent la `CashSession` avant le mouvement ;
- les mouvements manuels verrouillent directement la `CashSession` ;
- `CloseCashSession` verrouille la `CashSession` avant de calculer/finaliser la clôture.

Si une clôture gagne la course :

- le paiement concurrent ne peut plus écrire dans la session fermée ;
- la transaction métier du paiement doit échouer/rollback, pas devenir orpheline.

Aucun double mouvement, aucune écriture après fermeture.

## 17. Clôture

Créer un service explicite :

`CloseCashSession`

Entrées :

- session ;
- acteur ;
- `counted_amount_xof` ;
- `variance_reason` nullable ;
- `idempotency_key`.

### 17.1 Autorité

- `CLOSE_CASH` requis ;
- par défaut, l’acteur clôture sa propre session ;
- un acteur `MANAGE_CASH` peut être autorisé à clôturer une session du même contexte, avec son identité enregistrée comme `closed_by_core_reference` ;
- aucune clôture cross-context.

### 17.2 Calcul

Sous verrou de session :

1. exiger `OPEN` ;
2. calculer `expected_amount_xof` depuis `CashMovement` ;
3. valider `counted_amount_xof >= 0` ;
4. calculer `variance_xof = counted - expected` en entier signé ;
5. si variance != 0, exiger une justification réelle ;
6. écrire les snapshots de clôture ;
7. passer à `CLOSED` si variance = 0, sinon `CLOSED_WITH_VARIANCE` ;
8. créer le document de clôture ;
9. audit ;
10. commit.

### 17.3 Idempotence

- même clé + même session déjà clôturée = retourner la même clôture/document ;
- même clé appartenant à une autre session/contexte = fail closed ;
- session déjà clôturée avec autre clé = conflit explicite ;
- jamais deux documents de clôture pour une session.

## 18. Document de clôture

Réutiliser `CommercialDocument`.

NE PAS créer une seconde infrastructure documentaire.

Étendre progressivement :

- `cash_session_id` nullable ;
- type `CASH_CLOSURE` ;
- contrainte de source métier unique mise à jour ;
- index unique `(cash_session_id, document_type)` quand source caisse présente.

Ajouter une séquence de document lisible, par exemple :

`CLT-1`, `CLT-2`, ...

### 18.1 Snapshot minimal

Le document conserve :

- contexte ;
- caisse nom/code ;
- référence session ;
- responsable ;
- ouverture ;
- clôture ;
- fonds initial ;
- total entrées ;
- total sorties ;
- attendu ;
- réel compté ;
- écart ;
- justification éventuelle ;
- identité de clôture.

Ce snapshot reste immuable.

## 19. Audit

Événements minimaux :

- `cash.register_created` ;
- `cash.register_suspended` ;
- `cash.session_opened` ;
- `cash.movement_recorded` ;
- `cash.payment_linked` ;
- `cash.session_closed` ;
- `cash.variance_recorded` quand écart non nul ;
- `document.issued`.

Chaque événement garde au minimum :

- `context_id` ;
- acteur Core ;
- agrégat/référence ;
- avant/après utiles ;
- request/idempotency reference quand applicable ;
- date.

## 20. UX — principe

G-POS doit rester calme et évident.

Le produit ne doit pas afficher une comptabilité à l’utilisateur.

Le vocabulaire principal :

- **Ma caisse** ;
- **Ouvrir ma caisse** ;
- **Fonds de départ** ;
- **Espèces attendues** ;
- **Entrée** ;
- **Sortie** ;
- **Mouvements** ;
- **Clôturer ma caisse** ;
- **Montant compté** ;
- **Écart**.

Éviter dans l’écran principal : débit/crédit, journal général, compte comptable, grand livre comptable.

## 21. Navigation

Ajouter **Caisse** à la navigation quand l’acteur possède une permission caisse pertinente.

Ordre desktop recommandé :

**Accueil / Vendre / Acheter / Caisse / Produits / Stock / Documents**

Mobile : préserver `Vendre` comme action centrale évidente.

Si 7 éléments dégradent réellement la lisibilité au viewport téléphone, adapter la tabbar de façon bornée ; ne pas introduire un redesign complet ou un menu complexe sans nécessité.

Le résultat doit être vérifié visuellement sur mobile.

## 22. Hub Caisse

Route suggérée :

`/caisse`

### 22.1 Si aucune caisse configurée

Pour `MANAGE_CASH` :

> **Créez votre première caisse**  
> Donnez un nom simple à l’endroit où vous contrôlez vos espèces.
>
> **Créer une caisse**

Pour un acteur non gestionnaire : état vide honnête, sans faux bouton impossible.

### 22.2 Si caisse existe mais aucune session ouverte pour l’acteur

Afficher :

> **Votre caisse est fermée**  
> Ouvrez une session avant d’encaisser en espèces.
>
> Fonds de départ : `[ ... ] F`
>
> **Ouvrir ma caisse**

Si plusieurs caisses existent, demander explicitement laquelle ouvrir ; ne jamais en choisir une silencieusement.

### 22.3 Session ouverte

Afficher en premier :

> **Ma caisse**  
> Ouverte depuis 08:15
>
> **Espèces attendues : 47 500 F**

Puis :

- Entrées ;
- Sorties ;
- mouvements récents ;
- **Ajouter un mouvement** ;
- **Clôturer ma caisse**.

Pas de 12 KPI.

## 23. Parcours vente/paiement quand caisse fermée

L’utilisateur ne doit pas recevoir une erreur technique.

Sur `Vendre`, si le paiement CASH ne peut pas être confirmé faute de session :

> **Ouvrez votre caisse avant d’encaisser en espèces.**
>
> **Ouvrir ma caisse →**

Même principe pour un achat fournisseur payé comptant.

L’application ne doit pas auto-ouvrir une caisse au premier paiement.

## 24. Parcours clôture

Écran principal :

> **Clôturer ma caisse**
>
> Espèces attendues : **47 500 F**
>
> **Combien avez-vous réellement en caisse ?**
>
> `[ ______ ] F`

Après saisie, côté serveur :

- si égal : confirmation simple ;
- si différent : afficher l’écart et exiger une justification.

Exemple :

> **Écart : -500 F**
>
> Expliquez cet écart avant de clôturer.

Le motif n’efface jamais l’écart ; il l’explique.

## 25. Accueil

`À faire maintenant` peut désormais afficher, selon permissions et vraies données :

- **Ouvrez votre caisse avant d’encaisser** ;
- **Votre caisse est ouverte depuis …** si une clôture semble nécessaire ;
- **Une session présente un écart clôturé** uniquement si cela conduit à une action autorisée future ; sinon ne pas créer de faux workflow.

Toujours maximum 3 actions sur l’accueil.

Ne pas remplacer l’accueil par un tableau financier.

## 26. États vides / erreurs

Couvrir explicitement :

- aucune caisse ;
- caisse suspendue ;
- aucune session ouverte ;
- session déjà ouverte ;
- session ouverte sur une autre caisse par le même acteur ;
- montant initial invalide ;
- aucun mouvement ;
- sortie supérieure au solde ;
- session clôturée ;
- session étrangère ;
- absence de permission ;
- tentative de paiement CASH caisse fermée ;
- écart sans motif ;
- retry idempotent ;
- conflit de clé d’idempotence ;
- contexte suspendu selon les middleware existants.

Messages humains, pas d’erreur SQL exposée.

## 27. Migrations

Ne modifier aucune migration LOT-001/LOT-002 déjà fusionnée.

Créer uniquement des migrations évolutives.

Tables nouvelles :

- `cash_registers` ;
- `cash_sessions` ;
- `cash_movements`.

Évolutions probables :

- `commercial_documents` : source `cash_session_id` + type `CASH_CLOSURE` ;
- `commercial_context_sequences` seulement si son domaine/contrainte nécessite l’ajout des nouveaux types de séquences.

`payments` n’a pas besoin d’un `cash_session_id` si le lien canonique est `cash_movements.payment_id` ; ne dupliquer cette relation que si une raison structurelle démontrée l’exige.

Tester :

- migration depuis le schéma LOT-002 existant ;
- `migrate:fresh`.

## 28. Compatibilité LOT-001/LOT-002

Les données et fonctionnalités déjà fusionnées restent valides.

### 28.1 Important : nouveau comportement CASH

Après LOT-003, une **nouvelle** confirmation CASH nécessite une session ouverte.

Les tests LOT-001/LOT-002 qui confirmaient CASH sans caisse devront être adaptés en créant une vraie session de test, sans contourner la nouvelle règle.

Ne pas ajouter un fallback caché qui permettrait encore le CASH sans caisse uniquement pour faire passer les anciens tests.

### 28.2 Paiements historiques

Aucun backfill fictif.

Les anciens paiements restent consultables mais ne participent pas à une nouvelle session de caisse.

## 29. Services recommandés

Noms indicatifs, architecture équivalente acceptée :

- `CashRegisterManager` ;
- `OpenCashSession` ;
- `CashSessionResolver` ;
- `CashBalanceCalculator` ;
- `CashLedger` ;
- `RecordManualCashMovement` ;
- `CloseCashSession`.

Éviter un énorme service monolithique `CashService`.

## 30. Sécurité multi-contexte

Les IDs URL/Livewire sont non fiables.

Les services revalident systématiquement :

- `CashRegister.context_id` ;
- `CashSession.context_id` ;
- `CashMovement.context_id` ;
- session responsable/autorité ;
- `Payment.context_id` lors du rattachement ;
- `CommercialDocument.context_id`.

Les propriétés Livewire sensibles sont `#[Locked]` lorsque pertinentes, mais cela ne remplace jamais la validation métier côté service.

Fail closed sur conflit.

## 31. Idempotence

Actions obligatoirement idempotentes :

- ouverture de session ;
- rattachement paiement → mouvement ;
- mouvement manuel ;
- clôture.

Une clé rejouée ne doit jamais :

- ouvrir deux sessions ;
- doubler le fonds initial ;
- doubler un paiement ;
- doubler un mouvement manuel ;
- produire deux clôtures/documents.

Une clé réutilisée pour un autre agrégat/contexte doit être refusée, pas redirigée vers une preuve étrangère.

## 32. Intégrité financière

### 32.1 Entrées

- montant strictement positif ;
- aucune entrée SALE_PAYMENT sans Payment CASH confirmé correspondant ;
- aucun Payment ne peut générer deux mouvements.

### 32.2 Sorties

- montant strictement positif ;
- `PURCHASE_PAYMENT` correspond exactement au Payment fournisseur ;
- solde disponible suffisant ;
- aucune sortie ne rend l’attendu négatif.

### 32.3 Clôture

- `expected` calculé ;
- `counted` humain ;
- `variance = counted - expected` ;
- variance signée ;
- justification requise si variance != 0 ;
- aucun float.

## 33. Document / journal

Le reçu de vente et les documents d’achat ne changent pas de sens.

Le document `CASH_CLOSURE` est une preuve supplémentaire de la session, pas une facture et pas un état comptable légal.

Le journal d’audit reste append-only.

## 34. Données dev / démonstration

Le `DevBootstrapSeeder` peut, uniquement en environnement local/testing explicitement autorisé :

- donner les permissions caisse à l’acteur de démonstration ;
- créer une caisse de démonstration réelle de seed si cela simplifie le smoke test.

Cette donnée de développement ne doit jamais être créée silencieusement en production.

## 35. Tests d’acceptation métier

Ajouter au minimum :

1. une caisse appartient à un seul contexte ;
2. acteur sans `MANAGE_CASH` ne crée pas une caisse ;
3. acteur sans `OPERATE_CASH` n’ouvre pas de session ;
4. ouverture avec fonds 0 fonctionne ;
5. ouverture avec fonds 10 000 crée une vérité attendue de 10 000 ;
6. montant d’ouverture invalide/négatif refusé ;
7. deux sessions ouvertes sur la même caisse impossibles ;
8. un acteur ne peut avoir deux sessions ouvertes dans le même contexte ;
9. session étrangère/cross-context refusée ;
10. vente CASH sans session ouverte refusée atomiquement ;
11. vente CASH avec session ouverte crée exactement un mouvement IN ;
12. retry vente CASH ne double jamais le mouvement ;
13. achat CASH sans session ouverte refusé atomiquement ;
14. achat CASH avec session ouverte crée exactement un mouvement OUT ;
15. achat CASH supérieur au disponible refusé et Payment non créé ;
16. mouvement manuel IN exige motif et montant valide ;
17. mouvement manuel OUT exige motif et solde suffisant ;
18. mouvement manuel retry ne double pas ;
19. attendu = opening + entrées - sorties ;
20. mouvements d’une autre session/contexte n’affectent jamais l’attendu ;
21. clôture équilibrée produit `CLOSED` ;
22. clôture avec écart produit `CLOSED_WITH_VARIANCE` ;
23. écart sans motif refusé ;
24. variance positive correcte ;
25. variance négative correcte ;
26. session clôturée n’accepte plus de mouvement ;
27. paiement concurrent à la clôture ne crée pas d’écriture après fermeture ;
28. clôture retry même clé renvoie la même preuve ;
29. même clé de clôture sur autre session/contexte est refusée ;
30. exactement un document `CASH_CLOSURE` par session ;
31. snapshot de clôture ne change pas après renommage de la caisse ;
32. audit garde acteur/contexte/référence ;
33. paiements CASH historiques sans mouvement ne sont pas backfillés ;
34. les ventes LOT-001 continuent de fonctionner avec session réelle ;
35. les achats LOT-002 continuent de fonctionner avec session réelle ;
36. les paiements non-CASH futurs ne sont pas assimilés à l’espèce (test structurel si méthode future simulée possible sans élargir le domaine).

## 36. Tests UX / HTTP / Livewire

Couvrir au minimum :

1. `Caisse` masqué sans permission ;
2. hub caisse affiche un état vide honnête ;
3. création de caisse depuis l’UI autorisée ;
4. ouverture de session avec fonds initial ;
5. vente caisse fermée affiche une instruction humaine vers Caisse ;
6. session ouverte affiche attendu et mouvements ;
7. mouvement manuel fonctionne par HTTP/Livewire réel ;
8. clôture équilibrée fonctionne ;
9. clôture avec écart demande un motif ;
10. session cross-context retourne 404/403 sans fuite ;
11. document de clôture accessible seulement avec permission/contexte ;
12. mobile conserve `Vendre` comme action centrale et `Caisse` reste accessible/lisible.

Faire au moins un smoke test navigateur réel du parcours :

**ouvrir caisse → vendre CASH → achat CASH → mouvement manuel → clôturer → voir document.**

## 37. CI / validation

La CI existante doit rester simple et verte.

Avant PR :

```bash
php8.4 artisan test
./vendor/bin/pint --test
npm run build
git status --short
```

Vérifier PostgreSQL :

- migration depuis LOT-002 ;
- `migrate:fresh`.

Faire un smoke test navigateur réel sur desktop + téléphone.

## 38. Définition de terminé

LOT-003 est terminé quand :

- caisse/session/mouvements existent réellement ;
- aucun paiement CASH post-LOT-003 n’existe sans rattachement caisse ;
- ventes et achats CASH alimentent le même ledger caisse ;
- le solde attendu est dérivé sans float ;
- les sorties ne rendent jamais le solde négatif ;
- comptage réel et variance sont conservés ;
- clôture est idempotente et immuable ;
- document de clôture est généré ;
- audit est complet ;
- isolation multi-contexte est testée ;
- LOT-001/LOT-002 restent verts avec la nouvelle règle de session ;
- UX reste belle et simple ;
- CI verte ;
- une seule revue principale est nécessaire.

## 39. Hors blocage / HARDENING après beta

Ne pas bloquer LOT-003 pour :

- billets/pièces détaillés ;
- aveuglement du montant attendu ;
- approbation d’écarts ;
- transfert entre caisses ;
- dépôt intermédiaire ;
- remise de fonds ;
- caissier partagé ;
- relève de responsable ;
- dépenses structurées ;
- remboursements ;
- Mobile Money ;
- caisse livreur ;
- offline ;
- réouverture ;
- rapports avancés ;
- clôture de journée multi-caisses ;
- comptabilité simplifiée.

Ces sujets dérivent du CAP historique mais ne sont pas nécessaires pour valider le noyau LOT-003.

## 40. Règle de revue

Une seule revue principale.

Bloqueurs uniquement :

- identité parallèle ;
- fuite cross-context ;
- paiement CASH pouvant exister sans mouvement caisse après activation LOT-003 ;
- double mouvement ;
- mouvement sur session clôturée ;
- solde négatif ;
- calcul monétaire float ;
- clôture mutable/non idempotente ;
- écart effaçable ;
- migration cassée ;
- régression vente/achat CASH ;
- parcours caisse inutilisable.

Le reste va en HARDENING / POST-BETA.
