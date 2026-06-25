<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\ContextMenu;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adds the "Edit image" entry to the file list context menu for supported,
 * writable raster images when enabled via TSconfig.
 */
final class ItemProvider extends AbstractProvider
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected ?File $record = null;

    public function canHandle(): bool
    {
        return $this->table === 'sys_file';
    }

    public function getPriority(): int
    {
        return 50;
    }

    protected function initialize(): void
    {
        parent::initialize();
        $this->itemsConfiguration = [
            'editImage' => [
                'label' => 'LLL:EXT:image_editor/Resources/Private/Language/locallang.xlf:contextMenu.editImage',
                'iconIdentifier' => 'actions-image',
                'callbackAction' => 'editImage',
            ],
        ];
        try {
            $record = GeneralUtility::makeInstance(ResourceFactory::class)
                ->retrieveFileOrFolderObject($this->identifier);
            $this->record = $record instanceof File ? $record : null;
        } catch (ResourceDoesNotExistException) {
            $this->record = null;
        }
    }

    protected function canRender(string $itemName, string $type): bool
    {
        if (in_array($itemName, $this->disabledItems, true)) {
            return false;
        }

        return $itemName === 'editImage' && $this->canEditImage();
    }

    /**
     * @return array<string, string>
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        return [
            'data-callback-module' => '@georgringer/image-editor/context-menu-actions',
            'data-action-url' => (string)$uriBuilder->buildUriFromRoute('image_editor_edit'),
        ];
    }

    private function canEditImage(): bool
    {
        if (!$this->record instanceof File) {
            return false;
        }
        if (!in_array(strtolower($this->record->getExtension()), self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }
        if (!$this->record->checkActionPermission('write')) {
            return false;
        }

        return $this->isEnabledViaTsConfig();
    }

    private function isEnabledViaTsConfig(): bool
    {
        $tsConfig = $this->backendUser->getTSConfig();

        return (bool)($tsConfig['options.']['imageEditor.']['enable'] ?? true);
    }
}
