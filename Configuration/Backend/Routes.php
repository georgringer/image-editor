<?php

declare(strict_types=1);

use GeorgRinger\ImageEditor\Controller\EditorController;
use GeorgRinger\ImageEditor\Controller\ImageController;

return [
    // Full-page editor view, opened from the file list context menu (same tab).
    'image_editor_edit' => [
        'path' => '/image-editor/edit',
        'packageName' => 'georgringer/image-editor',
        'inheritAccessFromModule' => 'media_management',
        'target' => EditorController::class . '::edit',
    ],
    // Same-origin streaming of the source image so the editor canvas is never tainted.
    'image_editor_source' => [
        'path' => '/image-editor/source',
        'packageName' => 'georgringer/image-editor',
        'inheritAccessFromModule' => 'media_management',
        'target' => ImageController::class . '::source',
    ],
];
