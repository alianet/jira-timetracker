# Time Tracker

[English](README.md) | [Polski](README.pl.md) | [Deutsch](README.de.md) | [Čeština](README.cs.md) | [Slovenčina](README.sk.md) | **Français**

## Démarrage

```bash
docker compose up --build -d
```

L’application sera disponible à l’adresse <http://localhost:81>.

## Configuration Atlassian

L’application prend en charge deux modes :

- `individual` utilise un jeton API personnel
- `company` utilise OAuth 2.0 (3LO) et nécessite une application Atlassian enregistrée

### Mode `individual`

Pour des raisons de sécurité, ce mode accepte uniquement les requêtes via `localhost`
ou une adresse IP de bouclage. Les requêtes utilisant un nom de machine, une adresse
du réseau local ou une adresse publique reçoivent une réponse HTTP 403.

Dans ce mode, l’application se connecte directement à Jira avec un jeton API personnel.
Générez le jeton dans les paramètres de sécurité de votre compte Atlassian, puis
complétez le fichier `.env` :

```dotenv
JIRA_URL=https://votre-entreprise.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=individual
ATLASSIAN_EMAIL=votre.email@entreprise.fr
ATLASSIAN_API_TOKEN=...
```

`ATLASSIAN_EMAIL` doit être l’adresse e-mail du compte Atlassian et
`ATLASSIAN_API_TOKEN` le jeton généré pour ce même compte. Dans ce mode, ne configurez
pas `ATLASSIAN_CLIENT_ID`, `ATLASSIAN_CLIENT_SECRET` ni `ATLASSIAN_REDIRECT_URI`.

### Mode `company`

Ce mode nécessite la création d’une application OAuth 2.0 (3LO) dans la console
Atlassian Developer. Définissez le callback sur `http://localhost:81/oauth/callback`,
ajoutez les scopes `read:jira-work`, `write:jira-work`, `read:jira-user` et
`offline_access`, puis complétez `.env` :

```dotenv
JIRA_URL=https://votre-entreprise.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=company
ATLASSIAN_CLIENT_ID=...
ATLASSIAN_CLIENT_SECRET=...
ATLASSIAN_REDIRECT_URI=http://localhost:81/oauth/callback
SESSION_ENCRYPTION_KEY=...
```

Générez `SESSION_ENCRYPTION_KEY` une seule fois et conservez-le entre les déploiements :

```bash
docker compose run --rm php php -r 'echo sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), PHP_EOL;'
```

Cette clé chiffre et authentifie les jetons d’accès et de renouvellement OAuth avant
que PHP n’enregistre la session. La modifier invalide les sessions OAuth existantes.

Après le démarrage, l’application redirige vers la connexion Atlassian et récupère les
données au nom de l’utilisateur connecté. `ATLASSIAN_ACCOUNT_TYPE=company` active le
flux professionnel, tandis que `ATLASSIAN_ACCOUNT_TYPE=individual` utilise directement
le jeton personnel.

### Jeton Atlassian personnel

En mode `individual`, placez le jeton API dans `ATLASSIAN_API_TOKEN`.
En mode `company`, ce jeton n’est pas utilisé.

## Composer

Composer s’exécute dans le conteneur PHP, par exemple :

```bash
docker compose exec php composer --version
docker compose exec php composer install
```

L’application est un monolithe modulaire orienté DDD, avec `Identity`, `Reporting` et
`TimeTracking` comme modules métier. `Shared` constitue un petit noyau de domaine
partagé, tandis que les aspects techniques de l’hébergement se trouvent dans `Kernel`.
Les contrôleurs résident dans la couche `Presentation` de chaque module, les ports dans
`Application` ou `Domain`, et les adaptateurs Jira/Atlassian dans `Infrastructure`.
Consultez [ARCHITECTURE.md](ARCHITECTURE.md) pour la carte des dépendances et les règles
automatiques de séparation. La période du rapport se choisit dans le formulaire de la page.

`Bootstrap` est l’unique composition root de production. Les dépendances vont de
`Presentation` vers `Application`, puis `Domain` ; les adaptateurs d’infrastructure
implémentent les ports appartenant au métier. Le code du domaine ne dépend ni de HTTP,
ni de Twig, Jira ou CSV.

## Tests et contrôles qualité

Exécutez le contrôle complet requis dans le conteneur PHP :

```bash
docker compose exec php composer php:all
```

Pour une boucle de retour plus courte, lancez une suite individuelle :

```bash
docker compose exec php composer php:unit
docker compose exec php composer php:architecture
docker compose exec php composer php:static
docker compose exec php composer php:lint
docker compose exec php composer php:cs
```

Les tests ne doivent utiliser ni identifiants Jira réels ni appels réseau. Les tests
d’infrastructure emploient de faux transports et les tests applicatifs des stubs de ports.

## Ajouter un cas d’utilisation ou un adaptateur

Pour ajouter un cas d’utilisation, définissez sa commande ou requête d’entrée et son
handler dans la couche `Application` du module concerné. Placez la validation métier
dans les value objects du domaine, dépendez d’une interface appartenant à `Application`
ou `Domain/Port`, puis testez le handler avec un stub de ce port. Le contrôleur HTTP doit
uniquement mapper la requête, appeler le handler et créer la réponse.

Pour ajouter un adaptateur, implémentez ce port dans le répertoire `Infrastructure` du
module et conservez-y les payloads et noms de champs du fournisseur. Ajoutez des tests
de mapping ou de contrat, puis branchez l’adaptateur concret uniquement dans
`src/Bootstrap.php`. Mettez à jour `tests/Architecture/LayerDependenciesTest.php`
lorsqu’une nouvelle paire port/adaptateur obligatoire doit être contrôlée, puis terminez
avec `composer php:all`.

## Langues de l’interface

L’interface comprend des traductions polonaise, anglaise, allemande, tchèque, slovaque et française :

```dotenv
APP_LOCALES=pl,en,de,cs,sk,fr
APP_DEFAULT_LOCALE=pl
```

La langue peut être changée dans la navigation de la page. L’application mémorise le
choix dans un cookie et utilise sinon la préférence `Accept-Language` du navigateur.

## Variante de l’interface

La variable `TEMPLATE` de `.env` sélectionne la mise en page :

```dotenv
TEMPLATE=default
```

La valeur `default` conserve l’affichage standard. Définissez `compact` pour réduire
les espacements et limiter le défilement à la table du rapport afin que l’interface
s’adapte aux écrans plus petits.

## Configuration de l’export CSV

L’export mensuel peut être ajusté avec les variables de `.env` :

```dotenv
REPORT_EXPORT_ENABLED=true
REPORT_EXPORT_COLUMNS=issue,project,summary,date,minutes,comment
REPORT_EXPORT_FILENAME={username}_{year}_{month}.csv
REPORT_EXPORT_BOM=true
REPORT_EXPORT_SUMMARY_ROW='["RÉSUMÉ","","Temps de travail total :","","{total_minutes}","{total_hours}h {total_remaining_minutes}m"]'
REPORT_EXPORT_SUMMARY_SPACER=true
DAILY_HOURS_LIMIT=7.5
APP_TIMEZONE=Europe/Warsaw
LOG_LEVEL=error
```

`REPORT_EXPORT_ENABLED=false` masque le bouton de téléchargement et bloque l’endpoint
d’export. Si cette variable est absente ou vaut `false`, l’export reste désactivé et
les autres variables ne sont pas obligatoires. Seul `REPORT_EXPORT_ENABLED=true` active
la validation de `REPORT_EXPORT_COLUMNS`, `REPORT_EXPORT_FILENAME`, `REPORT_EXPORT_BOM`,
`REPORT_EXPORT_SUMMARY_ROW` et `REPORT_EXPORT_SUMMARY_SPACER`.

L’application lit la configuration directement dans `.env`. Un fichier `.env.local`
facultatif est ensuite chargé et remplace les valeurs portant le même nom. Les deux
fichiers sont montés dans le conteneur avec le code ; leur modification ne nécessite
donc ni reconstruction du conteneur ni variables d’environnement système.

Les exceptions non gérées, y compris les erreurs survenant au démarrage, sont écrites
avec leur stack trace dans `var/log/app.log`. Les erreurs fatales natives de PHP sont
également écrites dans `var/log/php-error.log`. Les détails des erreurs ne sont jamais
affichés dans les réponses HTTP : l’utilisateur reçoit une page HTML générique ou une
erreur JSON. `LOG_LEVEL` définit le niveau minimal de journalisation de `debug` à
`emergency` ; sa valeur par défaut est `error`.

Les colonnes disponibles sont `issue`, `project`, `summary`, `date`, `minutes`, `hours`,
`time_spent`, `comment`, `url` et `worklog_id`. Le masque de nom de fichier prend en
charge `{username}`, `{year}` et `{month}`. Les cellules de la ligne de résumé peuvent
utiliser `{total_minutes}`, `{total_hours}`, `{total_remaining_minutes}` et
`{total_decimal_hours}`. Cette ligne est un tableau JSON ; un tableau vide `[]` la désactive.

Le champ de recherche au-dessus de la table recherche des tickets par clé ou par titre
sans recharger la page. Cliquer sur un résultat ou une cellule de jour ouvre le formulaire,
enregistre la saisie directement dans Jira et actualise le rapport. Si une cellule contient
déjà du temps, le formulaire permet de sélectionner une saisie précise et d’en modifier
le temps ou le commentaire.

## Licence

Ce projet est disponible sous la [licence MIT](LICENSE).

## Arrêt

```bash
docker compose down
```
