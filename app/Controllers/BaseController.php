<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\View;

abstract class BaseController
{
    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $name, array $data = [], string $layout = 'main'): void
    {
        ob_start();
        View::render($name, $data);
        $content = ob_get_clean();
        if ($layout === '') {
            echo $content;
            return;
        }
        View::layout($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $code = 200): never
    {
        json_response($payload, $code);
    }
}
