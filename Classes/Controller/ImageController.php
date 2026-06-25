<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Streams the source image same-origin (FAL read, permission-checked) so the
 * editor canvas is never tainted, regardless of the storage driver.
 */
#[Autoconfigure(public: true)]
final readonly class ImageController
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function source(ServerRequestInterface $request): ResponseInterface
    {
        $target = (string)($request->getQueryParams()['target'] ?? '');

        try {
            $file = $this->resourceFactory->retrieveFileOrFolderObject($target);
        } catch (\Throwable) {
            return $this->responseFactory->createResponse(404);
        }

        if (!$file instanceof File || !$file->checkActionPermission('read')) {
            return $this->responseFactory->createResponse(403);
        }

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', $file->getMimeType())
            ->withHeader('Cache-Control', 'private, no-cache')
            ->withBody($this->streamFactory->createStream($file->getContents()));
    }
}
