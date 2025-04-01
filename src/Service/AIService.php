<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AIService
{
    private HttpClientInterface $httpClient;
    private string $openAiApiKey;
    private string $anthropicApiKey;
    private array $conversationHistory = [];
    private int $maxHistory = 15;
    private $session;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $parameterBag, RequestStack $requestStack)
    {
        $this->httpClient = $httpClient;
        $this->openAiApiKey = $parameterBag->get('openai_api_key');
        $this->anthropicApiKey = $parameterBag->get('anthropic_api_key');
        $this->session = $requestStack->getSession();

        // Charger l'historique depuis la session
        $this->conversationHistory = $this->session->get('conversation_history', []);
    }

    private function saveHistory()
    {
        // Limiter la taille de l'historique
        if (count($this->conversationHistory) > $this->maxHistory * 2) {
            $this->conversationHistory = array_slice($this->conversationHistory, -$this->maxHistory * 2);
        }

        // Sauvegarder en session
        $this->session->set('conversation_history', $this->conversationHistory);
    }

    public function askOpenAI(array $history): string
    {
        // Retirer les scores d’émotion (OpenAI n'en a pas besoin pour répondre)
        $formattedHistory = array_map(function ($entry) {
            return ['role' => $entry['role'], 'content' => $entry['content']];
        }, $history);
    
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4.5-preview',
                'messages' => $formattedHistory,
            ],
        ]);
    
        $data = $response->toArray();
        return $data['choices'][0]['message']['content'] ?? 'Erreur OpenAI';
    }
    



    public function askAnthropic(array $history): string
    {
        // Transformer l'historique en un texte adapté pour Claude
        $claudePrompt = "\n\nHuman:";
        foreach ($history as $entry) {
            // 🔥 Vérifier que 'emotion_score' existe avant d'y accéder
            $scoreText = (isset($entry['emotion_score']) && $entry['emotion_score'] > 0.6) 
                ? " (Ce message exprime une émotion forte)" 
                : "";

            $claudePrompt .= ($entry['role'] === 'user' ? " " : "\n\nAssistant: ") . $entry['content'] . $scoreText;
        }
        $claudePrompt .= "\n\nAssistant:";

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->anthropicApiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => 'claude-3-7-sonnet-20250219',
                'max_tokens' => 1500,
                'temperature' => 0.7,
                'messages' => [['role' => 'user', 'content' => $claudePrompt]],
            ],
        ]);

        $data = $response->toArray();
        return $data['content'][0]['text'] ?? 'Erreur Anthropic';
    }

        

    public function classifyMessage(string $message): float
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un classificateur de messages. Analyse le message et donne un score d’émotion entre 0 et 1 (0 = conversation purement informative, 1 = message fortement émotionnel). Réponds uniquement avec un nombre.'],
                    ['role' => 'user', 'content' => "Message : \"$message\". Quel est son score d’émotion (entre 0 et 1) ?"]
                ],
                'max_tokens' => 5,
            ],
        ]);

        $data = $response->toArray();
        $score = isset($data['choices'][0]['message']['content']) 
            ? floatval(trim($data['choices'][0]['message']['content'])) 
            : 0; // 🔥 Si la réponse est vide ou invalide, on met 0

        return max(0, min($score, 1));
    }
}
