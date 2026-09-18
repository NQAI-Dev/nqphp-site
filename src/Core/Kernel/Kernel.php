<?php

declare(strict_types=1);

namespace Nqphp\Core\Kernel;

use Nqphp\Core\Config\ConfigSchemaDiscoverer;
use Nqphp\Core\Config\ConfigStore;
use Nqphp\Core\Config\FeatureConfig;
use Nqphp\Core\Container\FeatureContainer;
use Nqphp\Core\Entity\Driver\InMemoryDriver;
use Nqphp\Core\Entity\Driver\SqliteDriver;
use Nqphp\Core\Entity\EntityDiscoverer;
use Nqphp\Core\Entity\EntityManager;
use Nqphp\Core\Input\RequestData;
use Nqphp\Core\Js\JsModuleServer;
use Nqphp\Core\Middleware\MiddlewareDiscoverer;
use Nqphp\Core\Routing\KernelUrlGenerator;
use Nqphp\Core\Middleware\RouteHookDiscoverer;
use Nqphp\Core\Routing\Router;
use Nqphp\Core\Security\CsrfTokenManager;
use Nqphp\Core\Controller\AbstractController;
use Nqphp\Core\Service\ServiceDiscoverer;
use PDO;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * Minimal HTTP kernel.
 *
 * Phase 1: route resolution + dispatch to controller method.
 * Phase 2: framework-internal `/_nqphp/...` namespace (JS modules,
 *   CSRF cookie + enforcement), per-feature config autoloading.
 *
 * The kernel is intentionally tiny — almost all of the heavy lifting
 * delegates to Symfony components (Routing, HttpFoundation, HttpKernel).
 * The kernel's job is wiring.
 */
final class Kernel implements HttpKernelInterface
{
    /** @var string */
    private $projectDir;

    /** @var \Nqphp\Core\Routing\Router */
    private $router;

    /** @var \Nqphp\Core\Middleware\MiddlewareDiscoverer */
    private readonly MiddlewareDiscoverer $middlewareDiscoverer;

    /** @var \Nqphp\Core\Config\FeatureConfig */
    private readonly FeatureConfig $featureConfig;

    /** @var \Nqphp\Core\Service\ServiceDiscoverer */
    private readonly ServiceDiscoverer $serviceDiscoverer;

    /** @var array<string, object> Singleton cache: name → instantiated service */
    private array $serviceInstances = [];

    /** @var \Nqphp\Core\Entity\EntityDiscoverer */
    private readonly EntityDiscoverer $entityDiscoverer;

    /** @var \Nqphp\Core\Entity\EntityManager */
    private readonly EntityManager $entityManager;

    /** @var \Nqphp\Core\Routing\UrlGenerator */
    private readonly KernelUrlGenerator $urlGenerator;

    /** @var \Nqphp\Core\Container\FeatureContainer */
    private readonly FeatureContainer $featureContainer;

    /** @var \Nqphp\Core\Middleware\RouteHookDiscoverer */
    private readonly RouteHookDiscoverer $routeHookDiscoverer;

    /** @var \Nqphp\Core\Input\RequestData */
    private readonly RequestData $requestData;

    /** @var \Nqphp\Core\Config\ConfigSchemaDiscoverer */
    private readonly ConfigSchemaDiscoverer $configSchemaDiscoverer;

    /** @var \Nqphp\Core\Config\ConfigStore */
    private readonly ConfigStore $configStore;

    private ?CsrfTokenManager $csrf;
    private ?JsModuleServer $js;

    public function __construct(
        string $projectDir,
        ?CsrfTokenManager $csrf = null,
        ?JsModuleServer $js = null,
    ) {
        $this->projectDir = $projectDir;
        $this->router = new Router([
            $projectDir . '/src/Feature',
        ]);
        $this->middlewareDiscoverer = new MiddlewareDiscoverer([
            $projectDir . '/src/Feature',
            $projectDir . '/src/Core',
        ]);
        $this->featureConfig = new FeatureConfig([
            $projectDir . '/src/Feature',
        ]);
        $this->serviceDiscoverer = new ServiceDiscoverer([
            $projectDir . '/src/Feature',
            $projectDir . '/src/Core',
        ]);
        $this->entityDiscoverer = new EntityDiscoverer([
            $projectDir . '/src/Feature',
            $projectDir . '/src/Core',
        ]);
        // Default driver: SqliteDriver (in-memory or file-backed).
        // Overridable via env: NQPHP_DRIVER=memory → InMemoryDriver (faster, ephemeral).
        $this->driver = ($_SERVER['NQPHP_DRIVER'] ?? '') === 'memory'
            ? new InMemoryDriver()
            : new SqliteDriver(new PDO(
                $_SERVER['NQPHP_SQLITE_PATH'] ?? 'sqlite::memory:'
            ));
        $this->entityManager = new EntityManager(
            $this->entityDiscoverer,
            $this->driver
        );
        $this->urlGenerator = new KernelUrlGenerator(
            $this->router->discover()  // eager snapshot — RouteCollection is small
        );
        $this->featureContainer = new FeatureContainer([
            $projectDir . '/src/Feature',
        ]);
        $this->routeHookDiscoverer = new RouteHookDiscoverer([
            $projectDir . '/src/Feature',
            $projectDir . '/src/Core',
        ]);
        // Note: RequestData needs a Request — initialised to null,
        // re-bound per request by the handle() method via
        // $this->rebindRequest($request) below.
        $this->requestData = new RequestData(new \Symfony\Component\HttpFoundation\Request());
        $this->configSchemaDiscoverer = new ConfigSchemaDiscoverer([
            $projectDir . '/src/Feature',
            $projectDir . '/src/Core',
        ]);
        $this->configStore = new ConfigStore($this->featureConfig);
        $this->csrf = $csrf;
        $this->js = $js;
    }

    /** Public accessor for tests + future introspection commands
     *  (e.g. `bin/console middleware:list` mirroring `routes:list`). */
    public function middlewareDiscoverer(): MiddlewareDiscoverer
    {
        return $this->middlewareDiscoverer;
    }

    /** Look up a per-feature config value. Returns $default if the
     *  feature has no config or no value for $key. */
    public function config(string $feature, string $key, mixed $default = null): mixed
    {
        return $this->featureConfig->load()->get($feature, $key, $default);
    }

    /** Raw config map for a feature (test/debug helper). */
    public function featureConfig(): FeatureConfig
    {
        return $this->featureConfig;
    }

    /** Lazy service accessor. Looks up by name; instantiates the
     *  service once for `singleton` scope, or every call for `prototype`.
     *  Throws \RuntimeException if the name is unknown. */
    public function service(string $name): object
    {
        $discovered = $this->serviceDiscoverer->discover();
        $desc = $discovered->describe($name);
        if ($desc === null) {
            throw new \RuntimeException("Unknown service: $name");
        }
        if ($desc['scope'] === 'singleton') {
            if (!isset($this->serviceInstances[$name])) {
                $this->serviceInstances[$name] = new $desc['class']();
            }
            return $this->serviceInstances[$name];
        }
        // prototype — new instance each call
        return new $desc['class']();
    }

    /** ServiceDiscoverer accessor for tests / introspection commands
     *  (e.g. a future `bin/console service:list` mirroring `feature:list`). */
    public function serviceDiscoverer(): ServiceDiscoverer
    {
        return $this->serviceDiscoverer;
    }

    /**
     * Primary accessor for the ORM. Returns the EntityManager (which
     * delegates to the configured DriverInterface — in-memory for
     * tests / one-shot CLI, SQLite-backed for production).
     *
     * The legacy entityStore() alias is kept below for one minor
     * release to ease the rename migration; callers should switch
     * to entityManager().
     */
    public function entityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /** RouteHookDiscoverer accessor for tests / introspection. */
    public function routeHookDiscoverer(): RouteHookDiscoverer
    {
        return $this->routeHookDiscoverer;
    }

    /** Input extractor accessor. Pass a `#[Input]`-annotated DTO class;
     *  get back an instance hydrated from the current request (JSON
     *  body / form fields / query string — first non-null wins per
     *  field). */
    public function input(string $dtoClass): object
    {
        return $this->requestData->extract($dtoClass);
    }

    /** RequestData accessor for tests / introspection. */
    public function requestData(): RequestData
    {
        return $this->requestData;
    }

    /** EntityDiscoverer accessor for tests / introspection. */
    public function entityDiscoverer(): EntityDiscoverer
    {
        return $this->entityDiscoverer;
    }

    /** Generate a URL for a named route.
     *
     * @param string                       $name     the route name (e.g. "blog:post:show").
     * @param array<string, mixed>         $params   path placeholders + query strings.
     * @param bool                         $absolute true → full URL with scheme + host,
     *                                              false (default) → path only.
     * @param array{scheme?: string, host?: string, https?: bool}|null $override
     *                                              scheme/host override for absolute URLs
     *                                              in different domains / for canonicalisation.
     * @return string the generated URL. */
    public function url(string $name, array $params = [], bool $absolute = false, ?array $override = null): string
    {
        return $this->urlGenerator->generate($name, $params, $absolute, $override);
    }

    /** UrlGenerator accessor for tests / introspection. */
    public function urlGenerator(): KernelUrlGenerator
    {
        return $this->urlGenerator;
    }

    /** Build a Symfony ContainerBuilder with all per-feature services.yaml
     *  loaded. Uncompiled — callers may further mutate or compile
     *  (with a cache pool if they want to avoid re-parsing on every
     *  request). */
    public function featureContainer(): \Symfony\Component\DependencyInjection\ContainerBuilder
    {
        return $this->featureContainer->build();
    }

    /** FeatureContainer accessor for tests / introspection. */
    public function featureContainerBuilder(): FeatureContainer
    {
        return $this->featureContainer;
    }

    /** Typed config accessor. Pass a `#[ConfigKey]` schema class; get
     *  back an instance with its public typed properties filled from
     *  src/Feature/{Feature}/config.php. Missing keys fall back to
     *  the class's own default values. */
    public function typedConfig(string $schemaClass): object
    {
        return $this->configStore->get($schemaClass);
    }

    /** ConfigSchemaDiscoverer accessor for tests / introspection. */
    public function configSchemaDiscoverer(): ConfigSchemaDiscoverer
    {
        return $this->configSchemaDiscoverer;
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        // Framework-internal namespace: JS modules + future framework
        // endpoints. Resolved before user routes so feature code never
        // shadows the runtime.
        if ($this->js !== null && str_starts_with($request->getPathInfo(), JsModuleServer::urlPrefix())) {
            $relative = substr($request->getPathInfo(), strlen(JsModuleServer::urlPrefix()));
            $response = $this->js->serve($relative);
            if ($response !== null) {
                return $this->withCsrfCookie($request, $response);
            }
            return new Response('Not Found', 404);
        }

        // Enforce CSRF on state-changing user requests before dispatch.
        if ($this->csrf !== null && !$this->csrf->isValid($request)) {
            return $this->withCsrfCookie(
                $request,
                new Response('CSRF token missing or invalid', 403, ['content-type' => 'text/plain']),
            );
        }

        // Run discovered middlewares in `order` ascending. Each
        // middleware either returns a Response (terminal — short-
        // circuits everything below) or null (continue). CSRF + auth
        // belong at low numbers (so they can deny), logging /
        // headers / metrics at high numbers (so they run last).
        $middlewares = $this->middlewareDiscoverer->discover()->all();
        foreach ($middlewares as $mw) {
            [$instance, $method] = $mw['callable'];
            $result = $instance->$method($request);
            if ($result instanceof Response) {
                return $this->withCsrfCookie($request, $result);
            }
            // null → continue to the next middleware.
        }

        $routes = $this->router->discover();
        $context = (new RequestContext())->fromRequest($request);
        $matcher = new UrlMatcher($routes, $context);

        try {
            $params = $matcher->match($request->getPathInfo());
        } catch (ResourceNotFoundException $e) {
            return $this->withCsrfCookie($request, new Response('Not Found', 404));
        }

        // BeforeRoute hooks: invoked after route match, before
        // controller dispatch. Walk the discovered handlers, filter
        // by glob pattern (path) + optional HTTP method, invoke in
        // declaration order. Any handler returning a Response
        // short-circuits the rest of the pipeline (terminal).
        foreach ($this->routeHookDiscoverer->discover()->before() as $hook) {
            if (!fnmatch($hook['pattern'], $request->getPathInfo())) {
                continue;
            }
            if ($hook['httpMethod'] !== null && $hook['httpMethod'] !== $request->getMethod()) {
                continue;
            }
            [$cls, $method] = $hook['callable'];
            $result = $cls::$method($request);
            if ($result instanceof Response) {
                return $this->withCsrfCookie($request, $result);
            }
        }

        [$class, $method] = explode('::', $params['_controller'], 2);
        if (!class_exists($class)) {
            return $this->withCsrfCookie($request, new Response(sprintf('Class %s not found', $class), 500));
        }

        // Controllers extending AbstractController need the Kernel
        // for $this->kernel->entityManager() etc. We use the kernel
        // accessor to build the instance, which also lets non-
        // AbstractController controllers (plain classes) be
        // instantiated without args — newInstanceArgs([]) handles both.
        $instance = new $class();
        if ($instance instanceof AbstractController) {
            // Re-bind the kernel property so subclasses see a non-null
            // reference. Simpler than reflection injection — works for
            // any AbstractController subclass regardless of inheritance.
            $ref = new ReflectionClass($instance);
            if ($ref->hasProperty('kernel')) {
                $kernelProp = $ref->getProperty('kernel');
                $kernelProp->setAccessible(true);
                $kernelProp->setValue($instance, $this);
            }
        }
        if (!method_exists($instance, $method)) {
            return $this->withCsrfCookie($request, new Response(sprintf('Method %s::%s not found', $class, $method), 500));
        }

        // Strip our private _controller / _method entries before invoking.
        unset($params['_controller'], $params['_method']);

        $reflection = new \ReflectionMethod($instance, $method);
        $args = $this->resolveArgs($reflection, $params, $request);
        $result = $reflection->invokeArgs($instance, $args);

        if ($result instanceof Response) {
            return $this->withCsrfCookie($request, $result);
        }
        return $this->withCsrfCookie($request, new Response((string) ($result ?? ''), 200, ['content-type' => 'text/plain']));
    }

    /**
     * Attach the CSRF cookie to a response if the framework has a
     * CsrfTokenManager wired up. Idempotent: if the cookie is already
     * present we don't issue a new one — the existing token stays put.
     */
    private function withCsrfCookie(Request $request, Response $response): Response
    {
        if ($this->csrf === null) {
            return $response;
        }
        $existing = $request->cookies->get(CsrfTokenManager::COOKIE_NAME);
        if (is_string($existing) && $existing !== '') {
            return $response;
        }
        $response->headers->setCookie($this->csrf->buildCookie($request));
        return $response;
    }

    /**
     * Resolve route parameters + Request into method argument order.
     *
     * Phase 1: positional binding of route placeholders. The Request
     * is injected if the parameter is typed Request.
     *
     * @param \ReflectionMethod $method
     * @param array<string,mixed> $params
     */
    private function resolveArgs(\ReflectionMethod $method, array $params, Request $request): array
    {
        $args = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (isset($params[$name])) {
                $args[] = $params[$name];
                continue;
            }
            if ($param->getType() && $param->getType()->getName() === Request::class) {
                $args[] = $request;
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            $args[] = null;
        }
        return $args;
    }

    public function getRouteCollection(): \Symfony\Component\Routing\RouteCollection
    {
        return $this->router->discover();
    }

    public function getCsrf(): ?CsrfTokenManager
    {
        return $this->csrf;
    }

    public function getJs(): ?JsModuleServer
    {
        return $this->js;
    }
}
