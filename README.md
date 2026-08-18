# G-POS

**Le réseau commercial local connecté à ZUMRA.**

G-POS est un satellite spécialisé de l’écosystème GAMAD. Il transforme une relation humaine devenue activité économique en commerce réel, traçable et interconnectable, sans recréer l’identité, la communauté ou la gouvernance sociale.

> **ZUMRA organise les humains. G-POS organise leur activité économique.**

## Positionnement

- **GAMAD** gouverne l’écosystème.
- **GAMAD Core** atteste les identités, organisations, sessions et autorisations transversales.
- **DG Afrique / ZUMRA** révèle les capacités, besoins, projets, communautés et mises en relation.
- **G-POS** exécute le commerce : catalogue, achats, ventes, paiements, créances, stock, caisse, livraisons, documents et relations commerciales.

G-POS n’est pas un deuxième réseau social et ne remplace pas DG Afrique. Une ZUMRA peut exister et agir sans jamais utiliser G-POS. G-POS devient disponible lorsqu’une activité économique réelle apparaît.

## Vision par générations

### V1 — Commerce Kernel

Faire fonctionner une activité seule avec rigueur et simplicité :

1. contexte organisationnel et intégration GAMAD Core ;
2. catalogue produits et services ;
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

### V2 — Réseau commercial local

Faire commercer les activités entre elles : fournisseurs, clients professionnels, catalogues interconnectés, commandes B2B, approvisionnement, logistique, canaux commerciaux et relations issues du réseau ZUMRA.

### V3 — Réseau économique intelligent

Utiliser les traces économiques vérifiables pour assister les humains : recommandations explicables, opportunités, prévisions, détection d’anomalies et intelligence commerciale. L’humain reste décisionnaire ; aucune réputation économique ne devient une mesure de valeur humaine.

## Expérience produit

G-POS doit être utilisable par une petite boutique sans formation ERP et suffisamment solide pour une organisation multi-sites.

Principes UX :

- mobile d’abord pour les opérations terrain ;
- une action principale évidente par écran ;
- vocabulaire humain avant le jargon comptable ;
- pas de tableau de bord saturé de chiffres ;
- le premier écran répond à : **« Qu’est-ce que je dois faire maintenant ? »** ;
- les informations sensibles restent dans G-POS et ne remontent vers le social qu’en événements contextualisés et autorisés.

Premiers accès naturels : **Vendre · Acheter · Commandes · Stock · Livraisons · Caisse**.

## Invariants satellite

1. G-POS ne crée jamais une identité membre parallèle à GAMAD Core.
2. G-POS possède son métier et ses données transactionnelles.
3. DG Afrique / ZUMRA ne devient jamais un ERP/POS.
4. Une relation sociale n’est jamais transformée automatiquement en transaction.
5. Une transaction ou un historique économique n’est jamais un score de valeur humaine.
6. Les permissions commerciales n’accordent aucune autorité institutionnelle ZUMRA.
7. Les données financières, créances, marges et stocks privés ne sont jamais publiés automatiquement.
8. Les intégrations sont explicites, auditables et révocables.
9. Les opérations financières et de stock critiques doivent être idempotentes et traçables.
10. Le produit doit rester utile même lorsque certaines intégrations réseau sont temporairement indisponibles.

## Héritage

Le corpus historique **ZUMRA V1** est conservé comme matière métier du Commerce Kernel. Il sera migré vers la nomenclature G-POS sans recopier les anciennes responsabilités sociales du nom ZUMRA.

La migration détaillée sera documentée dans `docs/legacy/ZUMRA-V1-TO-GPOS.md`.

## Statut

**LOT-001 en cours de revue** — fondation applicative Laravel 13 + premier parcours commercial
vertical (Contexte → Catalogue → Vente → Paiement → Stock → Document → Journal), conforme à
`docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md`.

## Développement local

Stack : Laravel 13, PHP 8.4, PostgreSQL, Redis, Vite, Blade + Livewire + Alpine (Alpine est fourni
par Livewire, ne pas en installer une seconde fois).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configurez `.env` — PostgreSQL (`DB_*`) et Redis (`REDIS_*`) doivent pointer vers des instances
réellement démarrées. `GPOS_DEV_CORE_IDENTITY_REFERENCE` définit l'acteur de développement (voir
« Identité » ci-dessous) — laissez la valeur d'exemple ou changez-la.

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\DevBootstrapSeeder
npm run build   # ou `npm run dev` pendant le développement
php artisan serve
```

Ouvrez `http://127.0.0.1:8000/dev/identite`, saisissez la référence configurée dans
`GPOS_DEV_CORE_IDENTITY_REFERENCE` (par défaut `IDN-PER-DEV-00000001`), puis rendez-vous sur `/`.
Le contexte « Boutique de démonstration » et deux produits d'exemple sont prêts à vendre.

### Identité

G-POS ne crée aucun compte humain canonique (`docs/architecture/SATELLITE-CONTRACT.md` §3). Tant
que la fédération GAMAD Core réelle n'est pas branchée, `/dev/identite` permet de « devenir »
n'importe quelle référence Core — **uniquement hors production** : la route n'est même pas
enregistrée quand `APP_ENV=production`, et `DevCoreSessionGateway` refuse de s'exécuter dans cet
environnement même si elle était liée par erreur (double verrou, voir
`docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md` §6.3).

### Tests

```bash
php artisan test
```

Les tests tournent contre PostgreSQL (pas sqlite) — le domaine s'appuie sur des verrous de ligne
(`SELECT ... FOR UPDATE`) pour le stock et la caisse. Créez au préalable la base
`gpos_core_test` avec le même rôle que `DB_USERNAME`.
