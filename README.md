# ChatbotOS - Système de Chat Multi-IA

ChatbotOS est une application Symfony qui achemine intelligemment les messages des utilisateurs entre différents modèles d'IA en fonction de leur contenu émotionnel.

## Fonctionnalités clés

- **Analyse émotionnelle** : Classification du contenu émotionnel des messages utilisateurs via OpenAI (GPT-4o)
- **Routage intelligent** : 
  - Anthropic Claude pour les messages émotionnellement chargés
  - OpenAI GPT pour les messages neutres/informatifs
- **Historique de conversation** : Stockage des interactions et des scores d'émotion
- **Adaptation du ton** : Ajustement du style de réponse en fonction du contexte émotionnel

## Architecture

- **ChatController** : Gère les requêtes API et le flux de conversation
- **AIService** : Interagit avec les fournisseurs d'IA (OpenAI, Anthropic)
- **Interaction** : Entité de stockage pour l'historique de conversation et scores émotionnels

## Prérequis

- PHP 8.2+
- Composer
- Base de données (MySQL, PostgreSQL)
- Clés API pour OpenAI et Anthropic

## Installation

1. Cloner le projet
   ```bash
   git clone https://github.com/username/chatbotos.git
   cd chatbotos
   ```

2. Installer les dépendances
   ```bash
   composer install
   ```

3. Configurer les variables d'environnement
   ```
   # .env.local
   DATABASE_URL=mysql://user:password@localhost:3306/chatbotos
   OPENAI_API_KEY=votre-clé-api-openai
   ANTHROPIC_API_KEY=votre-clé-api-anthropic
   ```

4. Créer la base de données
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. Lancer le serveur de développement
   ```bash
   symfony serve
   ```

## Utilisation de l'API

### Envoyer un message

**Endpoint** : `POST /api/chat`

**Corps de la requête** :
```json
{
  "message": "Votre message ici",
  "history": [] // Optionnel, utilisé pour conserver l'historique entre les appels
}
```

**Réponse** :
```json
{
  "response": "Réponse de l'IA",
  "emotion_score": 0.75,
  "avg_emotion": 0.65,
  "history": [
    {
      "role": "user",
      "content": "Votre message ici",
      "emotion_score": 0.75,
      "timestamp": "2025-01-01T12:00:00+00:00"
    },
    {
      "role": "assistant",
      "content": "Réponse de l'IA",
      "emotion_score": 0,
      "timestamp": "2025-01-01T12:00:01+00:00"
    }
  ]
}
```

## Logique de routage

Le système sélectionne le modèle d'IA en fonction de l'intensité émotionnelle :

- **Score > 0.6** : Message acheminé vers Anthropic Claude (meilleur pour les réponses empathiques)
- **Score ≤ 0.6** : Message acheminé vers OpenAI GPT (optimal pour les réponses informatives)

## Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

## Licence

Ce projet est sous licence MIT.