<?php

namespace App\Controller;

use App\Entity\Interaction;
use App\Service\AIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ChatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/api/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request, AIService $aiService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!isset($data['message'])) {
                return $this->json(['error' => 'Message manquant'], 400);
            }

            $userMessage = $data['message'];
            $history = $data['history'] ?? [];

            // Validation et nettoyage de l'historique
            foreach ($history as &$entry) {
                if (!isset($entry['emotion_score'])) {
                    $entry['emotion_score'] = 0;
                }
            }
            unset($entry);

            // Classification du message
            $emotionScore = $aiService->classifyMessage($userMessage) ?? 0;
            $emotionThreshold = 0.6;

            // Enregistrement de l'interaction
            $interaction = new Interaction();
            $interaction->setData([
                'message' => $userMessage,
                'emotion_score' => $emotionScore,
                'history' => $history
            ]);
            $this->entityManager->persist($interaction);

            // Mise à jour de l'historique
            $history[] = [
                'role' => 'user',
                'content' => $userMessage,
                'emotion_score' => $emotionScore,
                'timestamp' => (new \DateTime())->format('c')  // Format ISO 8601
            ];

            // Analyse des émotions récentes
            $recentEmotions = array_slice(array_reverse($history), 0, 5);
            $avgEmotion = !empty($recentEmotions) 
                ? array_sum(array_column($recentEmotions, 'emotion_score')) / count($recentEmotions)
                : 0;

            // Adaptation du ton
            $adaptiveTone = $this->determineAdaptiveTone($avgEmotion);
            $history[] = ['role' => 'system', 'content' => $adaptiveTone];

            // Sélection et appel du modèle AI
            $finalResponse = ($emotionScore > $emotionThreshold || $avgEmotion > 0.6)
                ? $aiService->askAnthropic($history)
                : $aiService->askOpenAI($history);

            // Mise à jour finale de l'historique
            $history[] = [
                'role' => 'assistant',
                'content' => $finalResponse,
                'emotion_score' => 0,
                'timestamp' => (new \DateTime())->format('c')
            ];

            $this->entityManager->flush();

            return $this->json([
                'response' => $finalResponse,
                'emotion_score' => $emotionScore,
                'avg_emotion' => $avgEmotion,
                'history' => $history
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Une erreur est survenue',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function determineAdaptiveTone(float $avgEmotion): string
    {
        if ($avgEmotion > 0.7) {
            return "L'utilisateur semble émotionnellement affecté récemment. Réponds avec empathie et réconfort.";
        }
        if ($avgEmotion > 0.4) {
            return "L'utilisateur montre des émotions légères. Réponds avec une touche d'humanité.";
        }
        return "Réponds normalement, de manière neutre et conversationnelle.";
    }
}
