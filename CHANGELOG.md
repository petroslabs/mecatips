# Changelog

Toutes les évolutions notables de MécaTips sont documentées ici.

Le format s'inspire de [Keep a Changelog](https://keepachangelog.com/fr/).

## [Non publié]

### Ajouté
- Rédaction du `ROADMAP.md` : vision produit, modèle collaboratif (soumission/vote comité), structuration du contenu, base de véhicules communautaire, ranking, direction artistique.
- Choix de la direction artistique : piste "brut / vécu" (fiche d'atelier, tampon encreur, annotation manuscrite), palette et typographies définies.
- Modèle de données initial : entités Doctrine `User`, `Category`, `Vehicle`, `Tip`, `TipRevision`, `CommitteeVote`, `Tag`, `UsefulVote`, `Report` (+ enums associés) et repositories. `Tip`/`TipRevision` séparent le contenu publié du contenu en cours de validation, pour qu'une modification n'écrase pas la version visible tant que le comité n'a pas voté.
- Migration Doctrine initiale (`Version20260708204249`) créant le schéma correspondant, générée et appliquée sur la base `mecatips`.
