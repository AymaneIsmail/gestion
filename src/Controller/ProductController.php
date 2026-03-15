<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductPrice;
use App\Form\ProductType;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Service\ImageUploader;
use App\Service\OrganizationContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/produits')]
class ProductController extends AbstractController
{
    #[Route('', name: 'app_product_index', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();

        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findByOrganization($organization),
        ]);
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
