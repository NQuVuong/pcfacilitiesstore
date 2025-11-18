<?php

namespace App\EventSubscriber;

use App\Entity\Visit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class VisitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (php_sapi_name() === 'cli') {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // bỏ qua profiler, asset, v.v.
        if (str_starts_with($path, '/_wdt')
            || str_starts_with($path, '/_profiler')
            || str_starts_with($path, '/bundles')
        ) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        if ($route === '') {
            return;
        }

        $ua = $request->headers->get('User-Agent', '');
        $browser = $this->detectBrowser($ua);

        $visit = (new Visit())
            ->setPath($path)
            ->setRouteName($route)
            ->setBrowser($browser)
            ->setVisitedAt(new \DateTimeImmutable())
            ->setIp($request->getClientIp());

        $this->em->persist($visit);
        $this->em->flush();
    }

    private function detectBrowser(string $ua): string
    {
        $ua = strtolower($ua);

        if (str_contains($ua, 'edg'))  return 'Edge';
        if (str_contains($ua, 'chrome')) return 'Chrome';
        if (str_contains($ua, 'firefox')) return 'Firefox';
        if (str_contains($ua, 'safari')) return 'Safari';
        if (str_contains($ua, 'opera') || str_contains($ua, 'opr')) return 'Opera';

        return 'Other';
    }
}
