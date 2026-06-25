<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\Tests\Functional\Service;

use GeorgRinger\ImageEditor\Service\ImagePersistenceService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ImagePersistenceServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['georgringer/image-editor'];

    private ResourceStorage $storage;
    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage('Test', 'fileadmin', 'relative', '', true);
        $storage = $storageRepository->findByUid($storageUid);
        self::assertInstanceOf(ResourceStorage::class, $storage);
        $this->storage = $storage;
        $this->folder = $this->storage->getRootLevelFolder();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saveCopyCreatesNewFileWithDesiredNameAndOriginalExtension(): void
    {
        $original = $this->createOriginalFile('photo.png');

        $new = $this->service()->saveCopy($original, 'edited-binary', 'my-edit');

        self::assertSame('my-edit.png', $new->getName());
        self::assertTrue($this->folder->hasFile('my-edit.png'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saveCopyAutoIncrementsOnNameCollision(): void
    {
        $original = $this->createOriginalFile('photo.png');

        // Desired name equals an existing file -> RENAME must auto-increment.
        $new = $this->service()->saveCopy($original, 'edited-binary', 'photo');

        self::assertSame('photo_01.png', $new->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saveCopyIgnoresUserSuppliedExtension(): void
    {
        $original = $this->createOriginalFile('photo.png');

        $new = $this->service()->saveCopy($original, 'edited-binary', 'name.webp');

        self::assertSame('name.png', $new->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saveCopyReencodesBytesToMatchTargetExtension(): void
    {
        // An elliptical/free crop makes Filerobot emit PNG even for a WebP source;
        // the saved copy must keep the .webp extension AND contain WebP bytes.
        $original = $this->createRealOriginalFile('photo.webp', $this->imageBytes('webp'));

        $new = $this->service()->saveCopy($original, $this->imageBytes('png'), 'edited');

        self::assertSame('edited.webp', $new->getName());
        self::assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer($new->getContents()));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function overwriteReencodesBytesToMatchTargetExtension(): void
    {
        $original = $this->createRealOriginalFile('photo.webp', $this->imageBytes('webp'));

        $result = $this->service()->overwrite($original, $this->imageBytes('png'));

        self::assertSame('photo.webp', $result->getName());
        self::assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer($result->getContents()));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function overwriteReplacesContentsKeepingUidAndName(): void
    {
        $original = $this->createOriginalFile('photo.png');
        $uid = $original->getUid();

        $result = $this->service()->overwrite($original, 'new-binary');

        self::assertSame($uid, $result->getUid());
        self::assertSame('photo.png', $result->getName());
        self::assertSame('new-binary', $result->getContents());
    }

    private function service(): ImagePersistenceService
    {
        $dispatcher = new class() implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };

        return new ImagePersistenceService($dispatcher, GeneralUtility::makeInstance(ProcessedFileRepository::class));
    }

    private function createOriginalFile(string $name): File
    {
        $tmp = GeneralUtility::tempnam('fixture_', '.png');
        // Minimal valid 1x1 PNG.
        GeneralUtility::writeFile(
            $tmp,
            (string)base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
                true
            ),
            true
        );

        return $this->storage->addFile($tmp, $this->folder, $name, DuplicationBehavior::REPLACE);
    }

    private function createRealOriginalFile(string $name, string $bytes): File
    {
        $tmp = GeneralUtility::tempnam('fixture_', '.' . pathinfo($name, PATHINFO_EXTENSION));
        GeneralUtility::writeFile($tmp, $bytes, true);

        return $this->storage->addFile($tmp, $this->folder, $name, DuplicationBehavior::REPLACE);
    }

    /**
     * Builds real 2x2 image bytes of the given type via GD.
     */
    private function imageBytes(string $type): string
    {
        $image = imagecreatetruecolor(2, 2);
        try {
            ob_start();
            match ($type) {
                'png' => imagepng($image),
                'webp' => imagewebp($image),
                default => imagejpeg($image),
            };

            return (string)ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }
}
