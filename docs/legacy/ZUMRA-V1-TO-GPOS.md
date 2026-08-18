# ZUMRA V1 → G-POS

**Statut : carte de migration métier v0.1**  
**Source : corpus historique ZUMRA V1 (14 capacités)**

## 1. Décision

Le corpus ZUMRA V1 n’est pas abandonné.

Il devient la matière historique du **Commerce Kernel de G-POS**.

Le nom ZUMRA reste désormais réservé au moteur social et communautaire de DG Afrique. Les capacités commerciales historiques doivent donc être renommées et révisées sans perdre leur profondeur métier.

## 2. Règle de migration

Chaque ancienne capacité est classée selon quatre opérations possibles :

- **CONSERVER** : le métier appartient directement à G-POS ;
- **ADAPTER** : le métier reste dans G-POS mais sa frontière avec GAMAD Core / DG Afrique doit être actualisée ;
- **CONNECTER** : le métier G-POS reste autonome mais expose des points d’intégration vers ZUMRA/G-Market/GamaDrive ;
- **DÉPLACER** : la responsabilité ne doit plus vivre dans G-POS.

Aucune fiche historique ne doit être copiée mécaniquement sous un nouveau nom.

## 3. Carte V1

| Ancienne capacité | Cible G-POS | Décision | Note principale |
|---|---|---|---|
| CAP-ZUMRA-001 — Intégration GAMAD Core et contexte organisationnel | GPOS-FOUNDATION-001 | ADAPTER | Conserver la séparation identité/métier ; remplacer l’ancienne notion de « ZUMRA produit » par le contrat satellite G-POS. |
| CAP-ZUMRA-002 — Catalogue produits/services | GPOS-COMMERCE-002 | CONSERVER + CONNECTER | Catalogue privé G-POS ; publication externe volontaire seulement via contrat explicite. |
| CAP-ZUMRA-003 — Unités, variantes, conditionnements | GPOS-COMMERCE-003 | CONSERVER | Noyau quantité/conditionnement indispensable aux ventes, achats et stocks. |
| CAP-ZUMRA-004 — Tarification, remises, règles commerciales | GPOS-COMMERCE-004 | CONSERVER | Les prix transactionnels restent G-POS ; les promotions publiques pourront être exposées ailleurs. |
| CAP-ZUMRA-005 — Vente et panier | GPOS-SALES-005 | CONSERVER | Parcours vertical prioritaire du produit. |
| CAP-ZUMRA-006 — Paiement et encaissement | GPOS-PAYMENTS-006 | CONSERVER | Paiement commercial distinct des adhésions/contributions ZUMRA. |
| CAP-ZUMRA-007 — Créances et versements | GPOS-RECEIVABLES-007 | CONSERVER | Donnée économique sensible ; jamais publication sociale automatique. |
| CAP-ZUMRA-008 — Stock et mouvements | GPOS-INVENTORY-008 | CONSERVER + CONNECTER | Stock privé ; disponibilité publiable seulement volontairement et de façon bornée. |
| CAP-ZUMRA-009 — Inventaire, pertes, corrections | GPOS-INVENTORY-009 | CONSERVER | Contrôle opérationnel et audit. |
| CAP-ZUMRA-010 — Livraison, remise, reliquats | GPOS-FULFILLMENT-010 | CONSERVER + CONNECTER | G-POS garde l’exécution commerciale ; DG Afrique peut garder la Mission humaine liée. |
| CAP-ZUMRA-011 — Retours, échanges, remboursements | GPOS-AFTERSALES-011 | CONSERVER | Workflow commercial complet. |
| CAP-ZUMRA-012 — Caisse et clôture | GPOS-CASH-012 | CONSERVER | Caisse et responsabilités financières strictement internes au contexte autorisé. |
| CAP-ZUMRA-013 — Documents commerciaux | GPOS-DOCUMENTS-013 | CONSERVER + CONNECTER | Documents métier générés par G-POS ; archivage documentaire externe possible sans déplacer la vérité transactionnelle. |
| CAP-ZUMRA-014 — Journal transactionnel et audit métier | GPOS-AUDIT-014 | CONSERVER | Fondation de preuve, synchronisation, litiges, contrôle et futur réseau économique. |

## 4. Ce que le corpus V1 préparait déjà

Les 14 fiches historiques ne décrivent pas seulement une caisse. Elles préparent explicitement des extensions au-delà du POS isolé.

Exemples d’extensions référencées par le corpus :

- publication volontaire du catalogue ;
- clients ;
- commandes fournisseurs ;
- réceptions fournisseurs ;
- comptabilité simplifiée ;
- rapports ;
- tableaux de bord ;
- canaux WhatsApp ;
- B2B ;
- réseau commercial ;
- logistique avancée ;
- intelligence commerciale ;
- détection d’anomalies ;
- réputation et confiance.

Ces références servent de **pistes historiques**. Elles ne sont pas automatiquement des contrats actuels : chacune devra être réévaluée avec la doctrine G-POS et l’architecture moderne de l’écosystème.

## 5. Nouvelle lecture V1 → V2 → V3

### V1 : autonomie commerciale

Une activité doit pouvoir fonctionner seule : vendre, acheter, encaisser, stocker, livrer, corriger, documenter et auditer.

### V2 : interconnexion commerciale

Les activités deviennent des partenaires économiques : fournisseur, client professionnel, distributeur, transporteur, producteur, restaurateur, revendeur, etc.

L’identité et la relation humaine peuvent provenir de DG Afrique/ZUMRA, mais le contrat et l’opération restent G-POS.

### V3 : intelligence économique bornée

Les traces de V1/V2 deviennent exploitables pour assister les décisions : prévision, rapprochement, anomalie, opportunité, résumé et recommandation explicable.

Aucune trace ne devient un score transversal de personne.

## 6. Nouveaux domaines probables après V1

La numérotation définitive n’est pas encore figée. Domaines à instruire après le premier parcours vertical :

- Organisations commerciales et points d’activité ;
- Clients ;
- Fournisseurs ;
- Achats et commandes fournisseurs ;
- Réceptions ;
- B2B inter-organisations ;
- Approvisionnement ;
- Logistique réseau ;
- Publication commerciale volontaire ;
- Intégration G-Market ;
- Comptabilité simplifiée ;
- Rapports ;
- Tableaux de bord ;
- Intelligence commerciale ;
- Anomalies ;
- Confiance économique contextuelle.

## 7. Ce qui ne doit pas être migré vers G-POS

- feed social ;
- adhésion ZUMRA ;
- gouvernance ZUMRA ;
- rôles institutionnels ZUMRA ;
- matching humain général ;
- missions générales hors opération commerciale ;
- transmission/apprentissage ;
- score humain ;
- profil social ;
- identité membre locale.

Ces responsabilités restent à DG Afrique/ZUMRA/GAMAD Core selon leur domaine.

## 8. Premier parcours vertical recommandé

Le premier code applicatif doit démontrer le cœur du produit sans tenter d’implémenter les 14 capacités d’un coup :

**Contexte commercial → Catalogue minimal réel → Vente → Paiement → Mouvement de stock → Document → Journal.**

Ce parcours doit être suffisamment propre pour devenir la colonne vertébrale de V1.

## 9. Règle documentaire

Lorsqu’une ancienne fiche est reprise :

1. lire l’original ;
2. extraire ses invariants métier ;
3. identifier ce qui dépendait de l’ancien sens du mot ZUMRA ;
4. appliquer la doctrine G-POS actuelle ;
5. documenter les conflits ;
6. produire une nouvelle fiche G-POS ;
7. ne jamais modifier silencieusement une règle financière ou transactionnelle importante.
