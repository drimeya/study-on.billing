<?php

namespace App\Service;

use Twig\Environment;

/**
 * Thin wrapper around Twig\Environment that can be injected into Console Commands.
 * Commands cannot receive Twig\Environment directly because kernel.debug flag
 * makes Twig a "request-scoped" service in some configurations;
 * using this dedicated service avoids scope-related issues.
 */
class Twig
{
    public function __construct(private Environment $twig)
    {
    }

    /**
     * Renders a Twig template and returns the resulting HTML string.
     *
     * @param string               $template  Template path, e.g. 'email/some.html.twig'
     * @param array<string, mixed> $context   Variables passed to the template
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }
}
