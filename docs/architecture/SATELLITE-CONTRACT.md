# G-POS — Contrat satellite

**Statut : ARCHITECTURE FOUNDATION v0.1**

## 1. Objectif

Définir les frontières techniques minimales de G-POS avant le premier code applicatif.

## 2. Autorités

### GAMAD Core est autorité pour

- identité canonique ;
- session/fédération ;
- appartenance organisationnelle transverse ;
- rôles/claims transverses lorsque définis par Core ;
- révocation de session ;
- identifiants canoniques partagés.

### G-POS est autorité pour

- contexte commercial local ;
- points d’activité/vente et paramètres métier ;
- rôles commerciaux locaux ;
- catalogue ;
- prix ;
- ventes ;
- achats ;
- paiements commerciaux ;
- créances/dettes métier ;
- stock ;
- inventaire ;
- caisse ;
- livraisons ;
- retours/remboursements ;
- documents commerciaux ;
- journal transactionnel et audit métier.

### DG Afrique / ZUMRA est autorité pour

- communauté ZUMRA ;
- capacités humaines ;
- besoins ;
- projets ;
- missions ;
- transmission ;
- feed social ;
- gouvernance sociale ;
- mise en relation humaine.

## 3. Identité

G-POS ne possède aucun mot de passe membre et aucun compte humain canonique parallèle.

Une représentation locale cache peut exister uniquement pour performance/résilience, identifiée par une référence Core stable et jamais promue source d’identité.

## 4. Contexte commercial

Le contexte commercial est explicite.

Un même humain peut agir dans plusieurs activités avec des permissions différentes.

Toute mutation métier doit être liée à :

- acteur canonique ;
- contexte commercial ;
- permission effective ;
- horodatage ;
- identifiant de requête/idempotence pour les opérations critiques.

## 5. Fédération depuis DG Afrique

Entrée recommandée : continuation/fédération signée ou mécanisme Core canonique équivalent.

Le contexte transmis ne doit pas être cru aveuglément. G-POS revalide les claims nécessaires avant d’ouvrir une surface sensible.

Le lien peut porter un contexte d’intention :

- source_type ;
- source_reference ;
- action souhaitée (préparer achat, vendre, livrer, etc.).

Cette intention n’accorde jamais une permission.

## 6. Contrats d’intégration

Préférer des contrats versionnés et explicites plutôt qu’un accès direct aux bases de données d’un autre produit.

Aucun satellite ne lit directement les tables internes d’un autre satellite comme contrat public.

## 7. Événements

Événements G-POS possibles :

- sale.confirmed ;
- payment.completed ;
- purchase.confirmed ;
- stock.received ;
- delivery.completed ;
- document.issued ;
- cash_session.closed.

Les consommateurs ne doivent recevoir que les données minimales nécessaires.

Un événement social dérivé doit éviter les données financières privées.

## 8. Idempotence

Obligatoire pour :

- confirmation vente ;
- paiement ;
- remboursement ;
- mouvement de stock ;
- réception ;
- clôture caisse ;
- émission de document numéroté ;
- synchronisation offline ;
- traitement webhook/événement externe.

## 9. Audit

Les actions sensibles doivent produire une trace permettant de répondre à :

- qui ;
- quoi ;
- quand ;
- dans quel contexte ;
- depuis quel état ;
- vers quel état ;
- avec quelle raison/autorisation ;
- avec quelle référence externe lorsque pertinente.

Le journal n’est pas une seconde source transactionnelle.

## 10. Offline

Le support offline est une capacité de résilience, pas une autorité parallèle.

Le client peut préparer ou enregistrer localement certaines opérations autorisées, mais la synchronisation doit :

- détecter les doublons ;
- appliquer les règles de conflit prévues ;
- conserver les identifiants client ;
- refuser les mutations devenues interdites lorsque la politique l’exige ;
- rendre le résultat visible à l’utilisateur.

## 11. Multi-tenant

Isolation stricte par contexte commercial/organisation.

Toute query métier sensible doit être scoped explicitement. Aucun identifiant public ne doit permettre de franchir le scope.

## 12. Finance et stock

Les mutations financières et de stock sont traitées comme des opérations de haute intégrité.

Principes :

- transaction DB lorsque nécessaire ;
- invariants vérifiés côté serveur ;
- montants en unités monétaires entières adaptées, jamais float ;
- quantités avec précision explicitement définie ;
- snapshots pour les états historiques ;
- reversals/annulations plutôt que réécriture silencieuse ;
- historique immutable lorsque requis par le domaine.

## 13. Documents

Le document est un rendu d’une vérité métier versionnée/snapshotée.

Modifier un produit, client ou prix futur ne réécrit pas silencieusement un document passé.

## 14. GamaDrive

GamaDrive peut conserver/partager des fichiers liés aux opérations, mais la source transactionnelle et la numérotation métier restent G-POS.

## 15. G-Market

G-Market peut exposer des offres/catalogues publiés volontairement.

Il ne lit pas directement le stock privé et ne devient pas source de vérité prix/stock G-POS.

## 16. Premier choix de stack

À confirmer avant bootstrap, avec préférence de cohérence d’exploitation :

- Laravel/PHP 8.4 ;
- PostgreSQL ;
- Redis ;
- Vite ;
- frontend serveur/Livewire ou couche légère à décider selon le parcours vente/offline ;
- Nginx ;
- tests Feature/Unit ;
- CI dès le début.

La technologie UI offline ne doit pas être choisie avant le prototype du parcours Vente.

## 17. Règle de conflit

Si la doctrine G-POS, le corpus historique ZUMRA V1, le comportement de GAMAD Core ou une contrainte d’intégration se contredisent :

**ne pas trancher silencieusement. Documenter le conflit, proposer l’option la plus sûre et demander une décision métier lorsque l’impact est structurel.**
