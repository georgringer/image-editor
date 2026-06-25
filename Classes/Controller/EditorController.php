<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Renders the full-page Filerobot editor for a single file inside the backend.
 */
#[Autoconfigure(public: true)]
final readonly class EditorController
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageRenderer $pageRenderer,
        private ResourceFactory $resourceFactory,
        private UriBuilder $uriBuilder,
    ) {}

    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $target = (string)($queryParams['target'] ?? '');
        $returnUrl = GeneralUtility::sanitizeLocalUrl((string)($queryParams['returnUrl'] ?? ''), $request);

        $file = $this->resourceFactory->retrieveFileOrFolderObject($target);
        if (!$file instanceof File
            || !in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true)
            || !$file->checkActionPermission('write')
        ) {
            throw new InsufficientFileAccessPermissionsException(
                'The file cannot be edited with the image editor.',
                1718710000
            );
        }

        $this->pageRenderer->addCssFile(
            'EXT:image_editor/Resources/Public/JavaScript/Vendor/filerobot-image-editor.bundle.css'
        );
        $this->pageRenderer->addCssFile('EXT:image_editor/Resources/Public/Css/editor.css');
        // The vendored Filerobot build is a classic IIFE that sets window.FilerobotImageEditor;
        // it must execute before the (deferred) ES module editor.js consumes it.
        $this->pageRenderer->addJsFile(
            'EXT:image_editor/Resources/Public/JavaScript/Vendor/filerobot-image-editor.bundle.js'
        );
        $this->pageRenderer->loadJavaScriptModule('@georgringer/image-editor/editor.js');

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle('Image Editor', $file->getName());
        $view->assignMultiple([
            'target' => $target,
            'folder' => $file->getParentFolder()->getCombinedIdentifier(),
            'sourceUrl' => (string)$this->uriBuilder->buildUriFromRoute('image_editor_source', ['target' => $target]),
            'returnUrl' => $returnUrl,
            'fileName' => $file->getName(),
            'extension' => strtolower($file->getExtension()),
            'config' => json_encode($this->buildEditorConfig(), JSON_THROW_ON_ERROR),
        ]);

        return $view->renderResponse('Editor/Show');
    }

    /**
     * Assembles the full editor configuration handed to the client. Tabs and crop
     * presets come from user/page TSconfig (`options.imageEditor.*`); the Filerobot
     * UI translations and our own dialog labels are resolved from XLF for the
     * backend user's language so the editor and its dialogs appear localized.
     *
     * @return array{
     *     tabs: list<string>,
     *     cropPresets: list<string>,
     *     language: string,
     *     translations: array<string, string>,
     *     labels: array<string, string>
     * }
     */
    private function buildEditorConfig(): array
    {
        $tsConfig = $this->getBackendUser()->getTSConfig()['options.']['imageEditor.'] ?? [];
        $languageService = $this->getLanguageService();

        return [
            'tabs' => GeneralUtility::trimExplode(
                ',',
                (string)($tsConfig['tabs'] ?? 'adjust,finetune,filters,annotate,resize'),
                true
            ),
            'cropPresets' => GeneralUtility::trimExplode(
                ',',
                (string)($tsConfig['cropPresets'] ?? ''),
                true
            ),
            // Filerobot only uses `language` together with its (disabled) backend
            // translation service; the actual UI strings come from `translations`.
            'language' => $this->getEditorLanguage(),
            'translations' => $languageService->getLabelsFromResource(
                'EXT:image_editor/Resources/Private/Language/editor.xlf'
            ),
            'labels' => $languageService->getLabelsFromResource(
                'EXT:image_editor/Resources/Private/Language/locallang.xlf'
            ),
        ];
    }

    /**
     * The two-letter language code Filerobot uses for its `language` option,
     * derived from the backend user's locale (e.g. "de-AT" → "de").
     */
    private function getEditorLanguage(): string
    {
        $locale = (string)($this->getLanguageService()->getLocale() ?? '');
        $code = strtolower(substr($locale, 0, 2));

        return $code === '' ? 'en' : $code;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
