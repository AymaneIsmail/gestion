<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductPrice;
use App\Form\ProductType;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Service\AiDescriptionGenerator;
use App\Service\ImageUploader;
use App\Service\OrganizationContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/produits')]
class ProductController extends AbstractController
{
    private const PAGE_SIZE = 12;

    #[Route('', name: 'app_product_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $page   = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $products = $productRepository->findByOrganization($organization, null, self::PAGE_SIZE, $offset);
        $total    = $productRepository->countByOrganization($organization, null);
        $hasMore  = ($offset + self::PAGE_SIZE) < $total;

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            $html = '';
            foreach ($products as $product) {
                $html .= $this->renderView('product/_card.html.twig', ['product' => $product]);
            }

            return $this->json(['html' => $html, 'hasMore' => $hasMore, 'total' => $total, 'count' => count($products)]);
        }

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'total'    => $total,
            'hasMore'  => $hasMore,
        ]);
    }

    #[Route('/export-csv', name: 'app_product_export_csv', methods: ['GET'])]
    public function exportCsv(
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
    ): StreamedResponse {
        $organization = $organizationContext->requireActiveOrganization();
        $products = $productRepository->findByOrganization($organization);

        $response = new StreamedResponse(function () use ($products): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Category', 'Sub Category', 'Title', 'Description',
                'Quantity', 'Type', 'Price', 'Shipping Profile',
                'Offerable', 'Hazmat', 'Condition', 'Cost Per Item', 'SKU',
                'Image URL 1', 'Image URL 2', 'Image URL 3', 'Image URL 4',
                'Image URL 5', 'Image URL 6', 'Image URL 7', 'Image URL 8',
            ]);

            foreach ($products as $product) {
                $price = $product->getPrice();
                // Whatnot requires positive integer prices (whole dollars)
                $sellingPrice = $price?->getSellingPriceCents() !== null
                    ? max(1, (int) round($price->getSellingPriceCents() / 100))
                    : '';
                $costPerItem = $price?->getPurchasePriceCents() !== null
                    ? max(1, (int) round($price->getPurchasePriceCents() / 100))
                    : '';

                // Whatnot title max 150 characters
                $title = $product->getName();
                if (mb_strlen($title) > 150) {
                    $title = mb_substr($title, 0, 147) . '...';
                }

                $images = $product->getImages()->toArray();
                $imageUrls = array_fill(0, 8, '');
                foreach (array_slice($images, 0, 8) as $i => $image) {
                    $imageUrls[$i] = $this->generateUrl(
                        'app_image_serve',
                        ['id' => $image->getId()],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    );
                }

                fputcsv($handle, [
                    $product->getCategory()?->getName() ?? 'Beauty',
                    'Fragrances',
                    $title,
                    $product->getDescription() ?? '',
                    $product->getStockQuantity(),
                    'Auction',
                    $sellingPrice,
                    '20 to <100 grams',
                    '',
                    'Not Hazmat',
                    'New',
                    $costPerItem,
                    $product->getReference() ?? '',
                    ...$imageUrls,
                ]);
            }

            fclose($handle);
        });

        $filename = 'produits-' . (new \DateTimeImmutable())->format('Y-m-d') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/nouveau', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
        ImageUploader $imageUploader,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $product = new Product();
        $product->setOrganization($organization);

        $form = $this->createForm(ProductType::class, $product, ['organization' => $organization]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePrices($form, $product);
            $this->handleImages($form, $product, $imageUploader);

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', 'Produit créé avec succès.');

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        return $this->render('product/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/autocomplete', name: 'app_product_autocomplete', methods: ['GET'])]
    public function autocomplete(
        Request $request,
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $query = $request->query->getString('q') ?: null;

        if ($query === null || \strlen($query) < 2) {
            return $this->json([]);
        }

        $products = $productRepository->findByOrganization($organization, $query, 6, 0);

        $results = [];
        foreach ($products as $product) {
            $images   = $product->getImages();
            $firstImg = $images->isEmpty() ? null : $images->first();
            $price    = $product->getPrice();

            $results[] = [
                'id'       => (string) $product->getId(),
                'name'     => $product->getName(),
                'price'    => ($price && $price->getSellingPriceCents())
                    ? number_format($price->getSellingPriceCents() / 100, 2, ',', "\u{202F}") . ' €'
                    : null,
                'imageUrl' => $firstImg
                    ? $this->generateUrl('app_image_serve', ['id' => $firstImg->getId()])
                    : null,
                'url'      => $this->generateUrl('app_product_show', ['id' => $product->getId()]),
            ];
        }

        return $this->json($results);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function show(
        string $id,
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/generer-description', name: 'app_product_generate_description_new', methods: ['POST'])]
    public function generateDescriptionFromForm(
        Request $request,
        AiDescriptionGenerator $generator,
    ): JsonResponse {
        if (!$generator->isEnabled()) {
            return $this->json(['error' => 'La génération IA est désactivée.'], 503);
        }

        $name      = trim($request->request->getString('name'));
        $reference = trim($request->request->getString('reference')) ?: null;
        $category  = trim($request->request->getString('category')) ?: null;

        if ($name === '') {
            return $this->json(['error' => 'Le nom du produit est requis pour générer une description.'], 422);
        }

        try {
            return $this->json(['description' => $generator->generate($name, $reference, $category)]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/generer-description', name: 'app_product_generate_description', methods: ['POST'])]
    public function generateDescription(
        string $id,
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
        AiDescriptionGenerator $generator,
    ): JsonResponse {
        if (!$generator->isEnabled()) {
            return $this->json([
                'error' => 'La génération IA est désactivée. Vérifiez votre clé API dans le fichier .env.',
            ], 503);
        }

        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            return $this->json(['error' => 'Produit introuvable.'], 404);
        }

        try {
            $description = $generator->generate(
                $product->getName(),
                $product->getReference(),
                $product->getCategory()?->getName(),
            );

            return $this->json(['description' => $description]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/modifier', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(
        string $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
        ImageUploader $imageUploader,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $form = $this->createForm(ProductType::class, $product, ['organization' => $organization]);

        // Pre-fill price fields (unmapped)
        $price = $product->getPrice();
        if ($price !== null) {
            $form->get('purchasePriceCents')->setData($price->getPurchasePriceDecimal());
            $form->get('sellingPriceCents')->setData($price->getSellingPriceDecimal());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePrices($form, $product);
            $this->handleImages($form, $product, $imageUploader);

            $em->flush();
            $this->addFlash('success', 'Produit modifié avec succès.');

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_product_delete', methods: ['POST'])]
    public function delete(
        string $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
        ImageUploader $imageUploader,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if ($this->isCsrfTokenValid('delete-product-' . $id, $request->request->get('_token'))) {
            // Delete image files from disk
            foreach ($product->getImages() as $image) {
                $imageUploader->delete($image->getPath());
            }

            $em->remove($product);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        }

        return $this->redirectToRoute('app_product_index');
    }

    #[Route('/{productId}/images/{imageId}/supprimer', name: 'app_product_image_delete', methods: ['POST'])]
    public function deleteImage(
        string $productId,
        string $imageId,
        Request $request,
        ProductRepository $productRepository,
        ProductImageRepository $productImageRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
        ImageUploader $imageUploader,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($productId, $organization);

        if ($product === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $image = $productImageRepository->find($imageId);

        if ($image === null || $image->getProduct() !== $product) {
            throw $this->createNotFoundException('Image introuvable.');
        }

        if ($this->isCsrfTokenValid('delete-image-' . $imageId, $request->request->get('_token'))) {
            $imageUploader->delete($image->getPath());
            $em->remove($image);
            $em->flush();
            $this->addFlash('success', 'Image supprimée.');
        }

        return $this->redirectToRoute('app_product_edit', ['id' => $productId]);
    }

    private function handlePrices(mixed $form, Product $product): void
    {
        $purchaseRaw = $form->get('purchasePriceCents')->getData();
        $sellingRaw = $form->get('sellingPriceCents')->getData();

        $purchaseCents = $purchaseRaw !== null ? (int) round((float) $purchaseRaw * 100) : null;
        $sellingCents = $sellingRaw !== null ? (int) round((float) $sellingRaw * 100) : null;

        $price = $product->getPrice();

        if ($price === null) {
            $price = new ProductPrice();
            $product->setPrice($price);
        }

        $price->setPurchasePriceCents($purchaseCents);
        $price->setSellingPriceCents($sellingCents);
    }

    private function handleImages(mixed $form, Product $product, ImageUploader $imageUploader): void
    {
        /** @var UploadedFile[] $uploadedFiles */
        $uploadedFiles = $form->get('images')->getData() ?? [];

        foreach ($uploadedFiles as $uploadedFile) {
            $path = $imageUploader->upload($uploadedFile);

            $image = new ProductImage();
            $image->setPath($path);
            $image->setOriginalName($uploadedFile->getClientOriginalName());

            $product->addImage($image);
        }
    }
}
