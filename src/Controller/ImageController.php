<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductImageRepository;
use App\Service\OrganizationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/image')]
class ImageController extends AbstractController
{
    public function __construct(
        private readonly string $uploadDirectory,
    ) {
    }

    #[Route('/{id}', name: 'app_image_serve', methods: ['GET'])]
    public function serve(
        string $id,
        ProductImageRepository $imageRepository,
        OrganizationContext $organizationContext,
    ): BinaryFileResponse {
        $organization = $organizationContext->requireActiveOrganization();
        $image = $imageRepository->find($id);

        if ($image === null || $image->getProduct()->getOrganization() !== $organization) {
            throw new NotFoundHttpException();
        }

        $fullPath = $this->uploadDirectory . '/' . $image->getPath();

        if (!is_file($fullPath)) {
            throw new NotFoundHttpException();
        }

        return (new BinaryFileResponse($fullPath))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE)
            ->deleteFileAfterSend(false);
    }

    #[Route('/{id}/telecharger', name: 'app_image_download', methods: ['GET'])]
    public function download(
        string $id,
        ProductImageRepository $imageRepository,
        OrganizationContext $organizationContext,
    ): BinaryFileResponse {
        $organization = $organizationContext->requireActiveOrganization();
        $image = $imageRepository->find($id);

        if ($image === null || $image->getProduct()->getOrganization() !== $organization) {
            throw new NotFoundHttpException();
        }

        $fullPath = $this->uploadDirectory . '/' . $image->getPath();

        if (!is_file($fullPath)) {
            throw new NotFoundHttpException();
        }

        $filename = $image->getOriginalName() ?? basename($image->getPath());

        return (new BinaryFileResponse($fullPath))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
            ->deleteFileAfterSend(false);
    }
}
