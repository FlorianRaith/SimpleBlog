<?php


namespace Core\Http;


use Core\AbstractApplication;
use Core\Exception\NotFoundException;
use Core\Http\Response\RedirectResponse;
use Core\Http\Response\RenderResponse;
use Core\Http\Response\Response;
use Exception;
use ReflectionException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
 * Class Kernel
 * @package Core\Http
 */
class Kernel
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var array
     */
    private $controllers = [];

    /**
     * @var AbstractApplication
     */
    private $app;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * Kernel constructor.
     */
    public function __construct()
    {
        $this->router = new Router();
    }

    /**
     * @param Request $request
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function handleRequest(Request $request): void
    {
        $route = $this->findRouteAndEditRequestParameters($request);

        if($route == null) throw new NotFoundException('A suitable route could not be found');

        $response = $this->callController($route->getControllerFunction(), $request);

        if($response == null) throw new NotFoundException('The controller function ' . $route->getControllerFunction() . ' was not found');

        $this->handleResponse($response, $request);
    }

    /**
     * @param Exception $exception
     * @return Response
     */
    public function handleException(Exception $exception): Response
    {
        $response = new Response($exception->getCode(), Response::PLAIN_CONTENT_TYPE, $exception->getMessage());

        $this->handleResponse($response, null);

        return $response;
    }

    /**
     * @param Request $request
     * @return Route|null
     */
    private function findRouteAndEditRequestParameters(Request $request): ?Route
    {
        $routes = array_reverse($this->router->getRegisteredRoutes());

        /* @var $route Route */
        foreach ($routes as $route) {

            if(preg_match($route->getRegex(), $request->getUri(), $matches)) {

                // set parameters in request object
                for($i = 0; $i < sizeof($route->getParameters()); $i++) {
                    $request->setParameter($route->getParameters()[$i], $matches[$i + 1]);
                }

                return $route;
            }

        }

        return null;
    }

    /**
     * @param ControllerFunction $controllerFunction
     * @param Request $request
     * @return Response
     * @throws ReflectionException
     */
    private function callController(ControllerFunction $controllerFunction, Request $request): ?Response
    {
        $controllerClass = $controllerFunction->getClass($this->app);
        if($controllerClass == null) return null;

        if(in_array($controllerClass, $this->controllers)) {
            $controllerInstance = $this->controllers[$controllerClass];
        } else {
            /** @var AbstractController $controllerInstance */
            $controllerInstance = new $controllerClass;
            $this->controllers[$controllerClass] = $controllerInstance;
            if(method_exists($controllerClass, 'setApp')) $controllerInstance->setApp($this->app);
        }

        return $controllerFunction->invoke($this->app, $controllerInstance, $request);
    }

    /**
     * @param Response $response
     * @param Request $request
     */
    private function handleResponse(Response $response, ?Request $request): void
    {
        http_response_code($response->getStatusCode());

        if($response instanceof RedirectResponse) {
            $this->handleRedirect($response, $request);
            return;
        }

        header($response->getContentType());
        foreach ($response->getAdditionalHeaders() as $header) {
            header($header);
        }

        if($response instanceof RenderResponse) {
            $this->handleRenderView($response);
            return;
        }

        echo $response->getContent();
    }

    /**
     * @param RedirectResponse $response
     * @param Request $request
     */
    private function handleRedirect(RedirectResponse $response, Request $request): void
    {
        $route = $this->getRouter()->getRouteByName($response->getRoute());

        // construct url
        $path = $route->getPath();
        foreach ($response->getParameters() as $parameter => $value) {
            $path = str_replace('{' . $parameter . '}', $value, $path);
        }

        $url = $request->getRootUrl() . $path;

        // set location header
        header('Location: ' . $url);
        exit();
    }

    /**
     * @param RenderResponse $response
     */
    private function handleRenderView(RenderResponse $response): void
    {
        try {
            echo $this->twig->render($response->getViewName() . '.twig', $response->getData());
        } catch (LoaderError $e) {
        } catch (RuntimeError $e) {
        } catch (SyntaxError $e) {
        }
    }

    /**
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * @param AbstractApplication $app
     */
    public function setApp(AbstractApplication $app): void
    {
        $this->app = $app;
    }

    /**
     *
     */
    public function initViewEngine(): void
    {
        $loader = new FilesystemLoader($this->app->getViewsPath());
        $this->twig = new Environment($loader, [
            'cache' => $this->app->getViewsCachePath()
        ]);
    }
}