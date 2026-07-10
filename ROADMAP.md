# Roadmap — MécaTips

## Vision

MécaTips n'est pas un énième site de tutos mécaniques. Les tutos existent déjà en masse (YouTube, blogs). Ce que MécaTips recense, ce sont les **tips de terrain** : les conseils qui se transmettent à l'oral dans un garage, entre un ancien du métier et un apprenti — le genre d'info qu'on ne trouve jamais dans un tuto structuré, mais qui fait toute la différence sur une intervention.

> Exemple : « Quand tu fais une distrib, pige toujours ton moteur pour ne pas décaler la synchro. » ou « Change la pompe à eau tant que tu y es, ça coûte rien et t'évite de tout redémonter si elle lâche plus tard. »

Le but : documenter, structurer et rendre cherchable ce savoir tacite, avec une modération de qualité assurée par un comité de mécaniciens de confiance.

## Périmètre

- **v1** : véhicules **auto** uniquement.
- **v2** : ouverture aux **motos**.

## Modèle collaboratif

### Lecture
Accès libre et gratuit à tous les tips publiés, sans compte.

### Contribution
- Un compte est nécessaire pour soumettre un tip.
- Un tip soumis passe par un **comité de validation** (mécaniciens de confiance, invités nommément par l'admin — pas de candidature ouverte pour l'instant).
- Chaque membre du comité vote **pour / contre**, avec **commentaire obligatoire en cas de vote contre** (retour constructif à l'auteur, essentiel pour la rétention des contributeurs).
- Un **quorum minimum** (ex. 3 votes) est requis avant de trancher, puis majorité simple.
- Si validé : publication du tip avec le nom de l'auteur.
- Si refusé : le tip n'est pas publié, l'auteur reçoit le motif.

### Modification d'un tip publié
- Toute modification d'un tip déjà validé **repasse en validation** par le comité.
- Le tip **reste visible dans son ancienne version** pendant que la modification est en cours de validation (pas de dépublication le temps du vote).

### Signalement
Possibilité pour tout utilisateur de signaler un tip déjà publié (obsolète, erroné, potentiellement dangereux).

## Structuration du contenu

Deux axes de classification indépendants, pour ne pas forcer un tip générique dans une case trop spécifique :

1. **Système / intervention** — taxonomie fixe gérée par l'équipe : Moteur (distribution, joint de culasse, injection...), Freinage, Suspension/Direction, Transmission/Boîte, Électrique/Électronique, Climatisation, Carrosserie, Outillage/Méthode générale.
2. **Portée véhicule** — optionnelle et à granularité variable : « tous véhicules », ou marque, ou marque+modèle, ou jusqu'au moteur précis (ex. « 1.9 TDI PD »). Pas d'obligation de préciser un véhicule pour un tip universel.

Complément :
- **Type de tip** : Astuce technique / Piège à éviter / Bonne pratique préventive / Astuce outillage.
- **Tags libres** pour les symptômes/mots-clés (bruit, fuite, démarrage à froid...), en plus de la taxonomie fixe.

## Base de véhicules

Pas de base de données ouverte gratuite descendant au niveau moteur (les API gratuites type NHTSA sont US-centrées ; TecDoc/Autodata sont payantes et sous licence commerciale).

Approche retenue : **base communautaire**, cohérente avec l'esprit du projet.
- Bootstrap avec un dataset ouvert existant pour marque/modèle.
- Si un contributeur ne trouve pas son véhicule (notamment la précision moteur/génération), il peut proposer un ajout, validé légèrement (auto-approuvé ou modéré selon confiance).
- Prévoir un outil d'admin pour dédoublonner/fusionner les entrées (ex. « Golf 4 » / « Golf IV » / « VW Golf 4 »).

## Formulaire de contribution

Priorité à la simplicité pour maximiser le taux de contribution :

- **Obligatoire** : titre + texte du tip, catégorie système (select simple), type de tip (boutons/icônes).
- **Optionnel, mis en avant mais pas bloquant** : véhicule concerné (champ recherche unique, pas de cascade marque→modèle→moteur), avec un raccourci « valable pour tous véhicules ».
- **Replié par défaut** : tags libres, détails supplémentaires. Photos/schémas repoussés en v2 (voir "Hors périmètre pour l'instant").

## Ranking & gamification

Deux mécanismes de vote bien distincts :
- **Vote du comité** = porte de publication (avant mise en ligne, binaire pour/contre).
- **Vote du public "utile / pas utile"** = sur les tips déjà publiés, façon Stack Overflow, fait remonter les meilleurs contenus.

Le classement des contributeurs se base sur un mix **nombre de tips validés + score d'utilité cumulé** (pas la seule quantité, pour ne pas inciter au spam de tips médiocres).

Badges/niveaux simples pour démarrer (ex. Apprenti / Compagnon / Expert selon le nombre de tips validés), à enrichir plus tard.

## Fonctionnalités par profil

### Visiteur (accès libre)
- Recherche / filtres par catégorie système, véhicule, type de tip, tags
- Page détail d'un tip
- Page véhicule regroupant tous les tips liés
- Tri : récents / mieux notés (utilité) / plus consultés

### Contributeur (compte)
- Soumission de tip
- Suivi de ses soumissions (statut en attente / validé / refusé + motif)
- Vote "utile / pas utile" sur les tips publiés
- Proposition d'ajout de véhicule manquant
- Profil public : tips validés, score, badges
- Favoris / tips sauvegardés

### Comité (modération)
- File d'attente des tips à voter, avec historique des votes
- Interface de gestion de la base véhicules (dédoublonnage/fusion)
- Gestion des membres du comité (côté admin)

### Transverse
- Signalement d'un tip publié
- Notifications (email/in-app) sur le statut de soumission
- SEO — le contenu doit être trouvable via Google, pas uniquement depuis le site
- Anti-spam / rate limiting sur les soumissions

## Hors périmètre pour l'instant

- Commentaires/discussion sous un tip publié (on reste simple, on ne pollue pas la page du tip).
- API publique / export de données.
- Motos (repoussé en v2).
- Photos/schémas joints à un tip (repoussé en v2).

## Direction artistique

- Thème clair par défaut, bascule sombre disponible. Site en français uniquement.
- Ton retenu : **brut / vécu** — fiche d'atelier légèrement inclinée, papier grainé, tampon encreur pour les badges de progression, annotation manuscrite au marqueur pour mettre en avant un point clé.
- Contrainte prioritaire : la lisibilité prime sur la patine. Rotation des cartes très légère (< 1°), grain de fond à faible opacité et jamais superposé au texte, annotation manuscrite réservée à de courts fragments (jamais un paragraphe entier), contraste texte/fond toujours vérifié.
- Palette : `#1E2225` graphite, `#F1EDE4` papier atelier, `#4B5359` acier, `#C8863A` laiton/huile, `#E3B23C` jaune signalétique (usage rare), `#8A9A8E` vert d'établi (validation).
- Typographies : Big Shoulders (titres, display), IBM Plex Sans (texte courant), IBM Plex Mono (données/specs, métadonnées), touche manuscrite en accent uniquement (annotations, jamais le corps de texte).
- Logo : à définir, placeholder en attendant.

## Stack

- Backend : Symfony (PHP)
- Frontend : Asset Mapper + Stimulus (Symfony UX)

## Prochaines étapes

1. Modèle de données (entités : Tip, Vehicle, User, CommitteeVote, Tag, Report...).
2. Mise en place de l'authentification et des rôles (visiteur / contributeur / comité / admin).
3. Déclinaison des tokens de la direction artistique en styles Symfony UX (CSS + composants Stimulus).
4. Premiers écrans : soumission de tip, file de validation comité, page tip publique.
