<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Renders PHP view templates under app/Views.
 */
final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $name, array $data = []): void
    {
        $path = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $name);
        }
        extract($data, EXTR_SKIP);
        include $path;
    }

    /**
     * Wraps captured content in main layout.
     *
     * @param array<string, mixed> $data Must include title, content, optional extra_scripts
     */
    public static function layout(string $layoutName, array $data): void
    {
        $layoutPath = dirname(__DIR__) . '/Views/layouts/' . $layoutName . '.php';
        if (!is_file($layoutPath)) {
            throw new \RuntimeException('Layout not found: ' . $layoutName);
        }
        extract($data, EXTR_SKIP);
        include $layoutPath;
    }
}
