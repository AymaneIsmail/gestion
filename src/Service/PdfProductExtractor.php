<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ProductImportDTO;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PdfProductExtractor
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $anthropicApiKey,
    ) {}

    /**
     * @param string $pdfContent Raw PDF binary content
     * @return ProductImportDTO[]
     */
    public function extract(string $pdfContent): array
    {
        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->anthropicApiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta'    => 'pdfs-2024-09-25',
                'content-type'      => 'application/json',
            ],
            'json' => [
                'model'      => 'claude-opus-4-6',
                'max_tokens' => 4096,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'   => 'document',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => 'application/pdf',
                                    'data'       => base64_encode($pdfContent),
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => <<<'PROMPT'
Extrait tous les produits de ce document et retourne uniquement un tableau JSON valide (sans markdown, sans explication).
Chaque objet du tableau doit avoir ces champs :
- name (string, obligatoire) : nom du produit
- reference (string|null) : référence ou code produit
- description (string|null) : description courte
- purchasePrice (number|null) : prix d'achat en euros (ex: 19.99)
- sellingPrice (number|null) : prix de vente en euros (ex: 29.99)
- stockQuantity (integer) : quantité en stock, défaut 0

Retourne uniquement le JSON brut, commençant par "[" et finissant par "]".
PROMPT,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $data = $response->toArray();
        $text = $data['content'][0]['text'] ?? '[]';

        // Strip potential markdown code fences
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $items = json_decode(trim($text), true);

        if (!is_array($items)) {
            return [];
        }

        $dtos = [];
        foreach ($items as $item) {
            if (empty($item['name'])) {
                continue;
            }

            $dto                = new ProductImportDTO();
            $dto->name          = trim((string) $item['name']);
            $dto->reference     = isset($item['reference']) && $item['reference'] !== '' ? (string) $item['reference'] : null;
            $dto->description   = isset($item['description']) && $item['description'] !== '' ? (string) $item['description'] : null;
            $dto->purchasePrice = isset($item['purchasePrice']) ? (float) $item['purchasePrice'] : null;
            $dto->sellingPrice  = isset($item['sellingPrice']) ? (float) $item['sellingPrice'] : null;
            $dto->stockQuantity = isset($item['stockQuantity']) ? max(0, (int) $item['stockQuantity']) : 0;

            $dtos[] = $dto;
        }

        return $dtos;
    }
}
