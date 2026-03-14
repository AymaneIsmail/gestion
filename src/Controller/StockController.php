<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\TagRepository;
use App\Service\OrganizationContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stock')]
class StockController extends AbstractController
{
    #[Route('', name: 'app_stock_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        TagRepository $tagRepository,
        OrganizationContext $organizationContext,
    ): Response {
        $organization = $organizationContext->requireActiveOrganization();
        $query      = $request->query->get('q', '');
        $categoryId = $request->query->get('category', '');
        $tagIds     = array_values(array_filter((array) $request->query->all('tag')));

        $products   = $productRepository->searchForStock($organization, $query ?: null, $categoryId ?: null, $tagIds);
        $categories = $categoryRepository->findByOrganization($organization);
        $tags       = $tagRepository->findByOrganization($organization);

        return $this->render('stock/index.html.twig', [
            'products'           => $products,
            'query'              => $query,
            'categories'         => $categories,
            'tags'               => $tags,
            'selectedCategoryId' => $categoryId,
            'selectedTagIds'     => $tagIds,
        ]);
    }

    #[Route('/{id}/increment', name: 'app_stock_increment', methods: ['POST'])]
    public function increment(
        string $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('stock-api', $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['error' => 'Token invalide.'], Response::HTTP_FORBIDDEN);
        }

        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            return $this->json(['error' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $by = max(1, (int) $request->request->get('by', 1));
        $product->incrementStock($by);
        $em->flush();

        return $this->json([
            'quantity' => $product->getStockQuantity(),
            'inStock'  => $product->isInStock(),
        ]);
    }

    #[Route('/{id}/decrement', name: 'app_stock_decrement', methods: ['POST'])]
    public function decrement(
        string $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('stock-api', $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['error' => 'Token invalide.'], Response::HTTP_FORBIDDEN);
        }

        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            return $this->json(['error' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $by = max(1, (int) $request->request->get('by', 1));
        $product->decrementStock($by);
        $em->flush();

        return $this->json([
            'quantity' => $product->getStockQuantity(),
            'inStock'  => $product->isInStock(),
        ]);
    }

    #[Route('/{id}/set', name: 'app_stock_set', methods: ['POST'])]
    public function set(
        string $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        OrganizationContext $organizationContext,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('stock-api', $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['error' => 'Token invalide.'], Response::HTTP_FORBIDDEN);
        }

        $organization = $organizationContext->requireActiveOrganization();
        $product = $productRepository->findOneByOrganization($id, $organization);

        if ($product === null) {
            return $this->json(['error' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $quantity = max(0, (int) $request->request->get('quantity', 0));
        $product->setStockQuantity($quantity);
        $em->flush();

        return $this->json([
            'quantity' => $product->getStockQuantity(),
            'inStock'  => $product->isInStock(),
        ]);
    }
}
