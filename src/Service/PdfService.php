<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PdfService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $gotenbergUrl,
    ) {}

    public function generateFromHtml(string $html): string
    {
        $boundary = '----GotenbergBoundary' . bin2hex(random_bytes(8));

        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\n"
            . "Content-Type: text/html\r\n\r\n"
            . $html . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"marginTop\"\r\n\r\n1\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"marginBottom\"\r\n\r\n1\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"marginLeft\"\r\n\r\n1\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"marginRight\"\r\n\r\n1\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"paperWidth\"\r\n\r\n8.27\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"paperHeight\"\r\n\r\n11.69\r\n"
            . "--{$boundary}--\r\n";

        $response = $this->httpClient->request('POST', $this->gotenbergUrl . '/forms/chromium/convert/html', [
            'headers' => [
                'Content-Type' => "multipart/form-data; boundary={$boundary}",
            ],
            'body' => $body,
        ]);

        return $response->getContent();
    }
}
