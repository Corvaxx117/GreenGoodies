# GreenGoodies

Architecture monorepo Symfony 8 avec deux applications :

- `front/` : application Twig/HTML sans Doctrine, qui consomme l'API REST et conserve le JWT en session Symfony
- `api/` : API REST Symfony 8 avec API Platform, Doctrine, JWT et route commerçant protégée par clé API

Documentation :

- [Architecture et diagrammes](docs/architecture.md)
