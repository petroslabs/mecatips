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
