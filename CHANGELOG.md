# Changelog

Toutes les évolutions notables de MécaTips sont documentées ici.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/).

## [Non publié]

### Ajouté
- Rédaction du `ROADMAP.md` : vision produit, modèle collaboratif (soumission/vote comité), structuration du contenu, base de véhicules communautaire, ranking, direction artistique.
- Choix de la direction artistique : piste "brut / vécu" (fiche d'atelier, tampon encreur, annotation manuscrite), palette et typographies définies.
- Modèle de données initial : entités Doctrine `User`, `Category`, `Vehicle`, `Tip`, `TipRevision`, `CommitteeVote`, `Tag`, `UsefulVote`, `Report` (+ enums associés) et repositories. `Tip`/`TipRevision` séparent le contenu publié du contenu en cours de validation, pour qu'une modification n'écrase pas la version visible tant que le comité n'a pas voté.
- Migration Doctrine initiale (`Version20260708204249`) créant le schéma correspondant, générée et appliquée sur la base `mecatips`.
- Intégration à l'infrastructure Docker partagée `../symfony_env` (Traefik/PostgreSQL/Redis/Mailpit), sur le même modèle que le projet `portfolio` : `Dockerfile` FrankenPHP multi-stage (prod : code intégré à l'image ; dev : bind-mount), `compose.yaml`/`compose.override.yaml`/`compose.prod.yaml`, `Makefile` (build/up/down/console/db-create/deploy-prod...), `trusted_proxies` pour tourner correctement derrière Traefik. Base `mecatips` et rôle dédié créés sur le PostgreSQL partagé.
- Authentification et rôles : inscription (`/inscription`), connexion (`/connexion`) et déconnexion (`/deconnexion`), avec connexion automatique après inscription. `User` comme provider Doctrine (email), hiérarchie de rôles `ROLE_ADMIN > ROLE_COMMITTEE > ROLE_USER`. Contraintes de validation sur `User` (email, pseudo unique/format) avec messages d'erreur en français. Page d'accueil minimale affichant l'état de connexion, pour vérifier le flux de bout en bout.
- Locale par défaut passée à `fr` (`default_locale`), conformément au choix "site en français uniquement" du ROADMAP — les messages Symfony (ex. erreurs d'authentification) s'affichent désormais en français.
- Seed des 8 catégories système de premier niveau (Moteur, Freinage, Suspension/Direction, Transmission/Boîte, Électrique/Électronique, Climatisation, Carrosserie, Outillage) via migration de données.
- `Vehicle.make`/`Vehicle.model` passent en nullable (migration) : le formulaire de tip ne saisit qu'un texte libre, `label` reste la source de vérité pour la recherche/l'affichage ; un outil d'admin de dédoublonnage complètera `make`/`model` plus tard.
- Formulaire de soumission de tip (`/tips/nouveau`, réservé aux comptes connectés) : titre, contenu, catégorie (select simple parmi les catégories système) et type (astuce/piège/prévention/outillage) obligatoires ; véhicule et tags optionnels, repliés/discrets par défaut conformément au ROADMAP. Le champ véhicule est une recherche libre sur le libellé (autocomplete natif via `<datalist>`, sans cascade marque/modèle/moteur) : un véhicule inconnu est créé automatiquement en statut "pending", dédupliqué de façon insensible à la casse. La soumission crée un `Tip` + sa première `TipRevision`, tous deux en attente de validation par le comité.
