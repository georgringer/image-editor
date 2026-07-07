<?php

declare(strict_types=1);

return [
    'dependencies' => [
        'backend',
        'core',
    ],
    // Required so the specifier is added to the import map on pages that render
    // the file-list context menu; without it the lazily imported callback module
    // "@georgringer/image-editor/context-menu-actions.js" is an unmapped bare specifier.
    'tags' => [
        'backend.contextmenu',
    ],
    'imports' => [
        '@georgringer/image-editor/' => 'EXT:image_editor/Resources/Public/JavaScript/',
    ],
];
