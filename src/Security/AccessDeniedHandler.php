<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

final class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private Environment $twig) {}

    public function handle(Request $request, \Symfony\Component\Security\Core\Exception\AccessDeniedException $e): ?Response
    {
        try {
            $html = $this->twig->render('bundles/TwigBundle/Exception/error403.html.twig', [
                'path' => $request->getPathInfo(),
            ]);
            return new Response($html, 403);
        } catch (\Throwable $t) {
            return new Response('403 Forbidden', 403);
        }
    }
}
