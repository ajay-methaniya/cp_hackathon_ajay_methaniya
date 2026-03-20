<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Upload orchestration is handled by {@see CallController::store()} and {@see \App\Services\FileStorageService}.
 * This class exists to match the project layout; extend here if you split upload endpoints later.
 */
final class UploadController extends BaseController
{
}
