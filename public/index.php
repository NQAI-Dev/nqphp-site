<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nqphp\Core\Js\JsModuleServer;
use Nqphp\Core\Kernel\Kernel;
use Nqphp\Core\Security\CsrfTokenManager;
use Symfony\Component\HttpFoundation\Request;

$projectDir = dirname(__DIR__);

// Phase 2 wiring: JS module server + CSRF protection are opt-in via
// the front controller. Features don't have to know about them.
$js = new JsModuleServer(
    frameworkRoots: [$projectDir . '/src/Core/Js/Resources'],
    featureRoots: [$projectDir . '/src/Feature'],
);
$csrf = new CsrfTokenManager(
    secure: ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
);

$kernel = new Kernel($projectDir, $csrf, $js);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
