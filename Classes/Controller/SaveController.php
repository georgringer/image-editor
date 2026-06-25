<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\Controller;

use GeorgRinger\ImageEditor\Exception\AccessDeniedException;
use GeorgRinger\ImageEditor\Service\ImagePersistenceService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Receives the edited image from the browser and writes it back via FAL.
 * Slice 1 supports the "copy" mode only.
 */
#[Autoconfigure(public: true)]
final readonly class SaveController
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private ImagePersistenceService $persistence,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $target = (string)($body['target'] ?? '');
        $mode = ((string)($body['mode'] ?? 'copy')) === 'overwrite' ? 'overwrite' : 'copy';
        $filename = (string)($body['filename'] ?? '');
        $image = (string)($body['image'] ?? '');

        try {
            $file = $this->resourceFactory->retrieveFileOrFolderObject($target);
        } catch (\Throwable) {
            return $this->json(['success' => false, 'message' => 'File not found.'], 404);
        }
        if (!$file instanceof File || !$file->checkActionPermission('read')) {
            return $this->json(['success' => false, 'message' => 'File not accessible.'], 403);
        }

        $binary = $this->decodeDataUrl($image);
        if ($binary === null) {
            return $this->json(['success' => false, 'message' => 'Invalid image data.'], 400);
        }

        try {
            $resultFile = $mode === 'overwrite'
                ? $this->overwriteTarget($file, $filename, $binary)
                : $this->createCopy($file, $filename, $binary);
        } catch (AccessDeniedException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return $this->json([
            'success' => true,
            'file' => [
                'uid' => $resultFile->getUid(),
                'name' => $resultFile->getName(),
            ],
        ]);
    }

    private function createCopy(File $original, string $filename, string $binary): File
    {
        // A new file is created in the original's folder, so folder write is required.
        if (!$original->getParentFolder()->checkActionPermission('write')) {
            throw new AccessDeniedException('No write permission for the target folder.', 1718711000);
        }

        return $this->persistence->saveCopy($original, $binary, $filename);
    }

    private function overwriteTarget(File $original, string $filename, string $binary): File
    {
        $folder = $original->getParentFolder();
        $storage = $original->getStorage();
        $base = pathinfo(trim($filename), PATHINFO_FILENAME);
        if ($base === '') {
            $base = $original->getNameWithoutExtension();
        }
        // Overwrite keeps the original extension.
        $targetName = $storage->sanitizeFileName($base . '.' . strtolower($original->getExtension()), $folder);

        if (!$folder->hasFile($targetName)) {
            throw new \RuntimeException('The file to overwrite no longer exists.', 1718711100);
        }
        $targetFile = $storage->getFileInFolder($targetName, $folder);
        if (!$targetFile instanceof File || !$targetFile->checkActionPermission('write')) {
            throw new AccessDeniedException('No write permission for the target file.', 1718711200);
        }

        return $this->persistence->overwrite($targetFile, $binary);
    }

    private function decodeDataUrl(string $dataUrl): ?string
    {
        if (!str_contains($dataUrl, ',')) {
            return null;
        }
        [, $encoded] = explode(',', $dataUrl, 2);
        $decoded = base64_decode($encoded, true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream(json_encode($data, JSON_THROW_ON_ERROR)));
    }
}
