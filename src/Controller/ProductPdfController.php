<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\ImageUploader;
use App\Service\OrganizationContext;
use App\Service\PdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductPdfController extends AbstractController
{
    #[Route('/produits/export-pdf', name: 'app_product_export_all_pdf', methods: ['GET'])]
    public function exportAll(
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
        PdfService $pdfService,
        ImageUploader $imageUploader,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $products = $productRepository->findByOrganization($organization);

        $productsData = [];
        foreach ($products as $product) {
            $imageBase64 = null;
            $imageMime   = null;
            if ($product->getImages()->count() > 0) {
                $firstImage = $product->getImages()->first();
                $path = $imageUploader->getAbsolutePath($firstImage->getPath());
                if (file_exists($path)) {
                    $imageBase64 = base64_encode(file_get_contents($path));
                    $imageMime   = mime_content_type($path) ?: 'image/jpeg';
                }
            }
            $productsData[] = [
                'product'     => $product,
                'imageBase64' => $imageBase64,
                'imageMime'   => $imageMime,
            ];
        }

        $html = $this->renderView('product/pdf_all.html.twig', [
            'productsData' => $productsData,
            'organization' => $organization,
            'generatedAt'  => new \DateTimeImmutable(),
        ]);

        $pdfContent = $pdfService->generateFromHtml($html);
        $filename = 'catalogue-produits-' . date('Y-m-d') . '.pdf';

        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    #[Route('/produits/{id}/export-pdf', name: 'app_product_export_pdf', methods: ['GET'])]
    public function export(
        string $id,
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
        PdfService $pdfService,
        ImageUploader $imageUploader,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        // Encode la première image en base64 pour l'intégrer dans le HTML
        $imageBase64 = null;
        $imageMime   = null;
        if ($product->getImages()->count() > 0) {
            $firstImage = $product->getImages()->first();
            $path = $imageUploader->getAbsolutePath($firstImage->getPath());
            if (file_exists($path)) {
                $imageBase64 = base64_encode(file_get_contents($path));
                $imageMime   = mime_content_type($path) ?: 'image/jpeg';
            }
        }

        $html = $this->renderView('product/pdf.html.twig', [
            'product'      => $product,
            'imageBase64'  => $imageBase64,
            'imageMime'    => $imageMime,
            'organization' => $organization,
            'generatedAt'  => new \DateTimeImmutable(),
        ]);

        $pdfContent = $pdfService->generateFromHtml($html);

        $filename = 'fiche-produit-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($product->getName())) . '.pdf';

        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
