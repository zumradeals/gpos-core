# G-POS — Doctrine produit

**Statut : CANON DE CONCEPTION v0.1**  
**Date : 18 août 2026**

## 1. Définition

G-POS est le moteur économique spécialisé du réseau d’action ZUMRA.

Il permet à une personne, une activité ou une organisation autorisée de transformer une coopération humaine devenue activité économique en opérations commerciales réelles, traçables et interconnectables.

> **ZUMRA organise les humains. G-POS organise leur activité économique.**

G-POS n’est pas un réseau social, un système d’identité, une autorité communautaire ni un simple terminal de caisse.

## 2. Rôle dans l’écosystème

### GAMAD

Gouvernance, doctrine et cohérence de l’écosystème.

### GAMAD Core

Source canonique d’identité, de contexte organisationnel, de session et d’autorisations transversales. G-POS ne recrée jamais ces objets comme source de vérité parallèle.

### DG Afrique / ZUMRA

Portail humain : personnes, capacités, besoins, projets, communautés, missions, transmission, coopération et mise en relation.

### G-POS

Métier commercial spécialisé : catalogue, tarification, vente, achat, paiement, créance, stock, inventaire, livraison, retour, caisse, documents, audit et relations commerciales.

## 3. Principe de passage ZUMRA → G-POS

Une relation sociale ne devient jamais automatiquement une transaction.

Chaîne canonique :

**Capacité / besoin / projet / relation → accord humain → contexte commercial explicite → opération G-POS.**

Exemples :

- un besoin d’approvisionnement peut proposer « Préparer un achat dans G-POS » ;
- une capacité logistique peut devenir une prestation commerciale après accord ;
- une production disponible peut être préparée pour mise en vente ;
- une mission DG Afrique peut pointer vers une livraison G-POS sans dupliquer la livraison dans le social.

## 4. Une ZUMRA n’a jamais besoin de G-POS pour exister

G-POS est contextuel.

Une ZUMRA éducative, sociale, spirituelle, culturelle ou communautaire peut fonctionner sans aucune activité commerciale.

La présence de G-POS ne doit jamais transformer la vocation d’une ZUMRA ni mesurer sa valeur.

## 5. Le Commerce Kernel V1

Le noyau hérité de ZUMRA V1 couvre :

1. intégration GAMAD Core et contexte organisationnel ;
2. catalogue produits/services ;
3. unités, variantes et conditionnements ;
4. tarification, remises et règles commerciales ;
5. vente et panier ;
6. paiement et encaissement ;
7. créances et versements ;
8. stock et mouvements ;
9. inventaire, pertes et corrections ;
10. livraison, remise et reliquats ;
11. retours, échanges et remboursements ;
12. caisse et clôture ;
13. documents commerciaux ;
14. journal transactionnel et audit métier.

Ces capacités sont la fondation opérationnelle, pas la limite du produit.

## 6. V2 — Réseau commercial local

V2 connecte les activités qui fonctionnent déjà individuellement.

Domaines visés :

- fournisseurs ;
- clients professionnels ;
- commandes d’achat et de vente inter-structures ;
- catalogues fournisseurs reliés aux catalogues internes ;
- approvisionnement ;
- relations commerciales récurrentes ;
- canaux B2B ;
- logistique et transport ;
- publication volontaire d’offres commerciales ;
- intégration avec G-Market lorsque le marché public devient pertinent ;
- continuité entre découverte dans ZUMRA et exécution dans G-POS.

G-POS devient alors un réseau commercial local, sans devenir un réseau social.

## 7. V3 — Réseau économique intelligent

V3 exploite les traces économiques vérifiables pour assister les décisions.

L’intelligence peut :

- suggérer un réapprovisionnement ;
- signaler une rupture probable ;
- rapprocher une demande commerciale d’une capacité d’offre ;
- détecter une anomalie transactionnelle ;
- proposer un fournisseur connu compatible ;
- résumer l’historique d’une relation commerciale ;
- faire émerger des opportunités locales ;
- expliquer les raisons d’une recommandation.

Elle ne peut jamais :

- attribuer automatiquement un fournisseur ;
- décider un achat, une vente ou un prix engageant ;
- publier automatiquement une donnée privée ;
- créer un score public de valeur humaine ;
- déduire une autorité institutionnelle à partir d’un chiffre d’affaires ;
- sanctionner une personne ou une ZUMRA.

## 8. Dignité et confiance économique

Une trace économique peut prouver qu’une opération a eu lieu. Elle ne prouve jamais la valeur d’une personne.

Distinctions obligatoires :

- **preuve d’activité** ≠ réputation humaine ;
- **fiabilité d’une transaction** ≠ dignité ;
- **historique commercial** ≠ classement public ;
- **capacité de paiement** ≠ droit à participer au réseau social.

Toute future notion de confiance doit être contextuelle, explicable, limitée à un usage économique précis et protégée contre le classement humain transversal.

## 9. Données et frontières

G-POS possède les données transactionnelles de son métier.

Données typiquement privées à G-POS :

- coûts ;
- marges ;
- créances ;
- encaissements détaillés ;
- fonds de caisse ;
- quantités de stock privées ;
- prix d’achat ;
- conditions contractuelles ;
- historiques de paiement ;
- informations sensibles clients/fournisseurs.

Une remontée vers DG Afrique/ZUMRA doit être volontaire, contextualisée et minimale.

Exemple acceptable : « La livraison liée au projet X est terminée. »

Exemple interdit par défaut : « Marge : 27,4 %, caisse : 2 485 000 FCFA, client débiteur : 375 000 FCFA. »

## 10. Permissions

Les permissions commerciales appartiennent au contexte commercial.

Exemples :

- vendre ;
- acheter ;
- gérer les prix ;
- encaisser ;
- ouvrir/fermer une caisse ;
- gérer le stock ;
- approuver une remise ;
- enregistrer une réception ;
- gérer une livraison ;
- rembourser ;
- consulter les coûts.

Aucune de ces permissions n’accorde automatiquement une autorité ZUMRA.

Inversement, être responsable d’une ZUMRA ne donne pas automatiquement accès aux marges, caisses ou créances d’une activité G-POS.

## 11. Fonctionnement local et résilience

G-POS doit pouvoir rester utile dans des contextes de connectivité imparfaite.

Les opérations critiques doivent être conçues autour de :

- idempotence ;
- identifiants stables ;
- horodatage ;
- journal transactionnel ;
- reprise après interruption ;
- synchronisation contrôlée ;
- prévention des doubles ventes/paiements/mouvements ;
- visibilité claire de l’état synchronisé ou non.

La résilience ne doit jamais contourner l’autorité ou l’identité canonique.

## 12. UX canonique

G-POS doit ressembler davantage à un assistant opérationnel qu’à un ERP.

Principes :

1. montrer d’abord ce qui demande une action ;
2. utiliser des verbes : Vendre, Acheter, Livrer, Encaisser, Compter ;
3. révéler la complexité progressivement ;
4. mobile d’abord pour le terrain ;
5. actions critiques explicites et confirmées ;
6. montants, quantités et états toujours lisibles ;
7. écrans vides utiles ;
8. erreurs compréhensibles ;
9. pas de statistiques décoratives ;
10. pas de jargon comptable lorsque le vocabulaire humain suffit.

## 13. Relation avec Mon espace DG Afrique

Mon espace peut afficher des entrées contextualisées vers G-POS lorsque l’utilisateur est autorisé et qu’une action économique réelle le concerne.

Exemples :

- « Une livraison de ZUMRA Transport Abidjan attend votre intervention » ;
- « Une commande fournisseur doit être confirmée » ;
- « La caisse de votre point de vente doit être clôturée ».

Mon espace ne devient pas le back-office G-POS.

## 14. Relation avec G-Market

G-POS gère la vérité commerciale interne et transactionnelle.

G-Market, lorsqu’il intervient, gère l’exposition, la découverte et le marché public ou semi-public.

Un produit peut exister dans G-POS sans être publié dans G-Market.

Une publication G-Market ne doit jamais devenir la source de vérité du stock, des coûts ou des transactions.

## 15. Principe d’IA

> **L’humain décide. Le réseau relie. G-POS exécute. L’IA éclaire et accélère.**

Toute automatisation engageante doit être explicable, révocable et bornée.

## 16. Critère de réussite

G-POS réussit si :

- un petit commerçant peut vendre sans formation ERP ;
- une organisation structurée peut contrôler ses opérations ;
- deux acteurs du réseau ZUMRA peuvent passer d’une mise en relation à une opération commerciale sans double saisie inutile ;
- les données sensibles restent protégées ;
- l’historique économique devient une preuve utile sans devenir un classement humain ;
- V1 peut croître vers V2/V3 sans être réécrite.
