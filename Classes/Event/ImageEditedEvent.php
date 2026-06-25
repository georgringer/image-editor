<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\Event;

use TYPO3\CMS\Core\Resource\File;

/**
 * Dispatched after an edited image has been written back via FAL.
 *
 * Listeners may react to the change, e.g. re-embed EXIF/IPTC from the original
 * into the (canvas-stripped) result, regenerate specific processed files or run
 * custom post-processing. The result file already has updated contents and a
 * refreshed FAL index when this event fires.
 */
final readonly class ImageEditedEvent
{
    /**
     * @param 'copy'|'overwrite' $mode
     */
    public function __construct(
        private File $originalFile,
        private File $resultFile,
        private string $mode,
        private string $binary,
    ) {}

    public function getOriginalFile(): File
    {
        return $this->originalFile;
    }

    public function getResultFile(): File
    {
        return $this->resultFile;
    }

    /**
     * @return 'copy'|'overwrite'
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    public function getBinary(): string
    {
        return $this->binary;
    }
}
