<?php

declare(strict_types=1);

use GeorgRinger\ImageEditor\Controller\SaveController;

return [
    // Receives the edited image and writes it back via FAL (copy in slice 1).
    'image_editor_save' => [
        'path' => '/image-editor/save',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_management',
        'target' => SaveController::class . '::save',
    ],
];
