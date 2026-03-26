<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiDescriptionGenerator
{
    // ─── Modifiez ce prompt selon vos besoins ────────────────────────────────
    private const PROMPT_TEMPLATE = <<<'PROMPT'
Tu es un expert en parfumerie et en copywriting e-commerce.

Ta mission : générer une fiche produit claire, élégante et réaliste, sans inventer d’informations.

Nom : {name}
Référence : {reference}
Catégorie : {category}

ÉTAPE 1 — Données

Identifie les notes olfactives (tête, cœur, fond) à partir de sources fiables (Fragrantica, site officiel, boutiques reconnues).
Si une information n’est pas certaine ou non trouvée → écrire "non renseigné".
Ne jamais inventer de notes.
Identifie le fabricant réel (marque / maison de parfumerie).
Pour le fabricant :
Ne pas se limiter au nom marketing de la marque si une entité industrielle est identifiable.
Rechercher en priorité les mentions : "Manufactured by", "Made by", "Perfumes Ind. LLC", ou toute société liée à la production.
Si une société officielle liée à la marque est connue et cohérente, l’utiliser comme fabricant.
Si aucune entité industrielle précise n’est trouvée, utiliser la société officielle de la marque comme fabricant.
Ne pas considérer cela comme une invention si cela correspond aux informations publiques de la marque.
Adresse :
Si une adresse officielle du fabricant est trouvée → l’indiquer sur une seule ligne.
Si doute ou information incomplète → "adresse du fabricant à ajouter".
Contact :
Rechercher téléphone et email professionnels du fabricant.
Si non trouvés → utiliser les placeholders.

ÉTAPE 2 — Rédaction

Respecte STRICTEMENT ce format. Copie-le tel quel en remplaçant uniquement les crochets.
Aucun texte en dehors du format.

FORMAT À RESPECTER

[Nom du parfum + volume + marque] est un [type de parfum] au caractère olfactif [caractère olfactif].
[Description sensorielle fluide et naturelle du parfum, sans exagération]
Genre et personnalité du parfum : [genre], [adjectif], [adjectif].
Occasion et type de personne : [moment], [profil].

Note de tête : [notes ou "non renseigné"]
Note de coeur : [notes ou "non renseigné"]
Note de fond : [notes ou "non renseigné"]

Fabricant

[Nom du fabricant]
[Adresse complète sur une seule ligne, ou "adresse du fabricant à ajouter"]
Contact : [voir règle ci-dessous]

Règle contact :

téléphone ET email trouvés → "[Téléphone] | [Email]"
seulement l’un des deux → afficher uniquement celui disponible
aucun → "[numéro de téléphone à ajouter] | [adresse email à ajouter]"

RÈGLES ABSOLUES

Ne jamais inventer d’informations factuelles
Ne jamais inclure de lien, d’URL ou de source
Ne jamais ajouter de texte en dehors du format
Commencer directement par le premier tiret
PROMPT;
    // ─────────────────────────────────────────────────────────────────────────

    // ─── Valeurs acceptées pour AI_PROVIDER ──────────────────────────────────
    private const PROVIDER_OPENAI    = 'openai';
    private const PROVIDER_ANTHROPIC = 'anthropic';
    // ─────────────────────────────────────────────────────────────────────────

    private const MAX_ANTHROPIC_TURNS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $anthropicApiKey,
        private readonly string $openaiApiKey,
        private readonly string $provider = self::PROVIDER_OPENAI,
        private readonly bool $enabled = true,
    ) {}

    public function isEnabled(): bool
    {
        $key = $this->provider === self::PROVIDER_ANTHROPIC
            ? $this->anthropicApiKey
            : $this->openaiApiKey;

        return $this->enabled && $key !== '';
    }

    public function generate(string $name, ?string $reference, ?string $category): string
    {
        $prompt = str_replace(
            ['{name}', '{reference}', '{category}'],
            [$name, $reference ?? 'Non renseignée', $category ?? 'Non renseignée'],
            self::PROMPT_TEMPLATE,
        );

        $text = $this->provider === self::PROVIDER_ANTHROPIC
            ? $this->generateWithAnthropic($prompt)
            : $this->generateWithOpenAi($prompt);

        return $this->cleanup($text);
    }

    private function generateWithOpenAi(string $prompt, int $attempt = 1): string
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 120,
            'json' => [
                'model' => 'gpt-4o-mini',
                'tools' => [['type' => 'web_search_preview']],
                'input' => $prompt,
            ],
        ]);

        $data = $response->toArray(false);

        if (isset($data['error'])) {
            // Erreur serveur OpenAI (5xx) : on retente jusqu'à 3 fois
            if ($attempt < 3 && str_contains($data['error']['message'] ?? '', 'server')) {
                sleep(2);
                return $this->generateWithOpenAi($prompt, $attempt + 1);
            }

            throw new \RuntimeException('[OpenAI] ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        foreach (array_reverse($data['output'] ?? []) as $item) {
            if ($item['type'] !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $block) {
                if ($block['type'] === 'output_text') {
                    return trim($block['text']);
                }
            }
        }

        throw new \RuntimeException('Aucun texte trouvé dans la réponse OpenAI.');
    }

    private function generateWithAnthropic(string $prompt): string
    {
        $messages = [['role' => 'user', 'content' => $prompt]];

        for ($turn = 0; $turn < self::MAX_ANTHROPIC_TURNS; $turn++) {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => $this->anthropicApiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta'    => 'web-search-2025-03-05',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 1024,
                    'tools'      => [['type' => 'web_search_20250305', 'name' => 'web_search']],
                    'messages'   => $messages,
                ],
            ]);

            $data = $response->toArray();

            if ($data['stop_reason'] === 'end_turn') {
                return $this->extractAnthropicText($data['content']);
            }

            if ($data['stop_reason'] === 'tool_use') {
                $messages[] = ['role' => 'assistant', 'content' => $data['content']];
                $toolResults = [];
                foreach ($data['content'] as $block) {
                    if ($block['type'] === 'tool_use') {
                        $toolResults[] = ['type' => 'tool_result', 'tool_use_id' => $block['id'], 'content' => ''];
                    }
                }
                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            return $this->extractAnthropicText($data['content']);
        }

        throw new \RuntimeException('La génération Anthropic a dépassé le nombre maximum de tours.');
    }

    /**
     * @param array<int, array<string, mixed>> $content
     */
    private function extractAnthropicText(array $content): string
    {
        $text = '';
        foreach ($content as $block) {
            if ($block['type'] === 'text') {
                $text = $block['text'];
            }
        }

        return trim($text);
    }

    private function cleanup(string $text): string
    {
        // Supprime les citations OpenAI : ([site.com](https://...)) ou ([texte](url))
        $text = preg_replace('/\s*\(\[[^\]]*\]\([^)]*\)\)/', '', $text) ?? $text;

        $lines = explode("\n", $text);

        foreach ($lines as &$line) {
            if (!str_contains($line, '|')) {
                continue;
            }

            [$left, $right] = array_map('trim', explode('|', $line, 2));

            $leftEmpty  = $left  === '' || str_contains(strtolower($left),  'à ajouter');
            $rightEmpty = $right === '' || str_contains(strtolower($right), 'à ajouter');

            if ($leftEmpty && $rightEmpty) {
                $line = '[numéro de téléphone à ajouter] | [adresse email à ajouter]';
            } elseif ($leftEmpty) {
                $line = $right;
            } elseif ($rightEmpty) {
                $line = $left;
            }
        }

        return implode("\n", $lines);
    }
}
