<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\Exception;

/**
 * Thrown when the current backend user lacks the required FAL permission for a
 * save operation. Mapped to an HTTP 403 response by the controller.
 */
final class AccessDeniedException extends \RuntimeException {}
