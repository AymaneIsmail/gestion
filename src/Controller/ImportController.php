<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ProductImportDTO;
use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\OrganizationContext;
use App\Service\PdfProductExtractor;
use App\Service\ProductMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/import')]
class ImportController extends AbstractController
{
    #[Route('/pdf', name: 'app_import_pdf', methods: ['GET'])]
    public function upload(): Response
    {
        return $this->render('import/upload.html.twig');
    }

    #[Route('/pdf', name: 'app_import_pdf_process', methods: ['POST'])]
    public function process(
        Request $request,
        PdfProductExtractor $extractor,
        OrganizationContext $organizationContext,
    ): Response {
        $organizationContext->requireActiveOrganization();

        if (!$this->isCsrfTokenValid('import-pdf', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_import_pdf');
        }

        $file = $request->files->get('pdf');

        if ($file === null) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier PDF.');
            return $this->redirectToRoute('app_import_pdf');
        }

        if (!in_array($file->getMimeType(), ['application/pdf', 'application/x-pdf'], true)) {
            $this->addFlash('error', 'Le fichier doit être un PDF.');
            return $this->redirectToRoute('app_import_pdf');
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            $this->addFlash('error', 'Le fichier ne doit pas dépasser 10 Mo.');
            return $this->redirectToRoute('app_import_pdf');
        }

        try {
            $pdfContent = file_get_contents($file->getPathname());
            $dtos       = $extractor->extract($pdfContent);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'extraction : ' . $e->getMessage());
            return $this->redirectToRoute('app_import_pdf');
        }

        if (empty($dtos)) {
            $this->addFlash('error', 'Aucun produit trouvé dans ce PDF.');
            return $this->redirectToRoute('app_import_pdf');
        }

        $request->getSession()->set('import_products', array_map(
            static fn(ProductImportDTO $dto) => [
                'name'          => $dto->name,
                'reference'     => $dto->reference,
                'description'   => $dto->description,
                'purchasePrice' => $dto->purchasePrice,
                'sellingPrice'  => $dto->sellingPrice,
                'stockQuantity' => $dto->stockQuantity,
            ],
            $dtos,
        ));

        return $this->redirectToRoute('app_import_pdf_review');
    }

    #[Route('/pdf/revue', name: 'app_import_pdf_review', methods: ['GET'])]
    public function review(
        Request $request,
        OrganizationContext $organizationContext,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        ProductMatcher $matcher,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $imported     = $request->getSession()->get('import_products', []);

        if (empty($imported)) {
            return $this->redirectToRoute('app_import_pdf');
        }

        $existing   = $productRepository->findByOrganization($organization);
        $categories = $categoryRepository->findByOrganization($organization);

        // Build rows with match results
        $rows = [];
        foreach ($imported as $index => $product) {
            $rows[] = [
                'index'   => $index,
                'product' => $product,
                'match'   => $matcher->findMatch($product, $existing),
            ];
        }

        // Build existing products catalog for JS (keyed by UUID string)
        $catalog = [];
        foreach ($existing as $p) {
            $price      = $p->getPrice();
            $images     = $p->getImages();
            $firstImage = $images->isEmpty() ? null : $images->first();

            $catalog[(string) $p->getId()] = [
                'name'          => $p->getName(),
                'reference'     => $p->getReference(),
                'stock'         => $p->getStockQuantity(),
                'sellingPrice'  => $price?->getSellingPriceDecimal(),
                'purchasePrice' => $price?->getPurchasePriceDecimal(),
                'imageUrl'      => $firstImage
                    ? $this->generateUrl('app_image_serve', ['id' => $firstImage->getId()])
                    : null,
            ];
        }

        return $this->render('import/review.html.twig', [
            'rows'       => $rows,
            'existing'   => $existing,
            'categories' => $categories,
            'catalog'    => $catalog,
        ]);
    }

    #[Route('/pdf/confirmer', name: 'app_import_pdf_confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();

        if (!$this->isCsrfTokenValid('import-confirm', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_import_pdf');
        }

        $session        = $request->getSession();
        $storedProducts = $session->get('import_products', []);

        if (empty($storedProducts)) {
            $this->addFlash('error', 'Session expirée, veuillez recommencer l\'import.');
            return $this->redirectToRoute('app_import_pdf');
        }

        $productsData = $request->request->all('products') ?: [];
        $included     = $request->request->all('include') ?: [];
        $actions      = $request->request->all('action') ?: [];
        $matchedIds   = $request->request->all('matched_id') ?: [];
        $created      = 0;
        $updated      = 0;

        foreach ($storedProducts as $index => $stored) {
            if (!array_key_exists($index, $included)) {
                continue;
            }

            $action = $actions[$index] ?? 'create';
            $edited = $productsData[$index] ?? [];
            $qty    = max(0, (int) ($edited['stockQuantity'] ?? $stored['stockQuantity'] ?? 0));

            // ── UPDATE existing product stock ──
            if ($action === 'update') {
                $matchedId = $matchedIds[$index] ?? null;
                if ($matchedId) {
                    $product = $productRepository->findOneByOrganization($matchedId, $organization);
                    if ($product !== null) {
                        $product->incrementStock($qty);

                        // Optionally update purchase price if provided
                        $purchaseRaw = $edited['purchasePrice'] ?? '';
                        if ($purchaseRaw !== '') {
                            $cents = (int) round((float) $purchaseRaw * 100);
                            $price = $product->getPrice() ?? new ProductPrice();
                            $price->setPurchasePriceCents($cents);
                            if ($product->getPrice() === null) {
                                $product->setPrice($price);
                            }
                        }

                        $updated++;
                        continue;
                    }
                }
            }

            // ── CREATE new product ──
            $name = trim($edited['name'] ?? $stored['name']);
            if ($name === '') {
                continue;
            }

            $product = new Product();
            $product->setOrganization($organization);
            $product->setName($name);

            $reference = trim($edited['reference'] ?? '');
            $product->setReference($reference !== '' ? $reference : null);

            $description = trim($edited['description'] ?? '');
            $product->setDescription($description !== '' ? $description : null);

            $product->setStockQuantity($qty);

            $categoryId = $edited['categoryId'] ?? '';
            if ($categoryId !== '') {
                $category = $categoryRepository->findOneByOrganization($categoryId, $organization);
                if ($category !== null) {
                    $product->setCategory($category);
                }
            }

            $purchaseRaw   = $edited['purchasePrice'] ?? '';
            $sellingRaw    = $edited['sellingPrice'] ?? '';
            $purchaseCents = $purchaseRaw !== '' ? (int) round((float) $purchaseRaw * 100) : null;
            $sellingCents  = $sellingRaw !== '' ? (int) round((float) $sellingRaw * 100) : null;

            if ($purchaseCents !== null || $sellingCents !== null) {
                $price = new ProductPrice();
                $price->setPurchasePriceCents($purchaseCents);
                $price->setSellingPriceCents($sellingCents);
                $product->setPrice($price);
            }

            $em->persist($product);
            $created++;
        }

        $em->flush();
        $session->remove('import_products');

        $parts = [];
        if ($created > 0) $parts[] = "$created créé(s)";
        if ($updated > 0) $parts[] = "$updated mis à jour";
        $this->addFlash('success', 'Import terminé : ' . implode(', ', $parts) . '.');

        return $this->redirectToRoute('app_product_index');
    }
}
