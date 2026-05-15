# GreenGoodies

Architecture monorepo Symfony 8 avec deux applications :

- `front/` : application Twig/HTML sans Doctrine, qui consomme l'API REST et conserve le JWT en session Symfony
- `api/` : API REST Symfony 8 avec API Platform, Doctrine, JWT et route commerçant protégée par clé API

Ports locaux utilisés avec Symfony CLI :

- `https://127.0.0.1:8000` pour le front
- `https://127.0.0.1:8001` pour l'API

Comptes de fixtures API :

- `merchant@greengoodies.test` / `Password123!`
- `customer@greengoodies.test` / `Password123!`

Documentation :

- [Architecture et diagrammes](docs/architecture.md)
