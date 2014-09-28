<?php
namespace BladeView\View;

use Cake\View\View;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Event\EventManager;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Environment;
use Closure;

class BladeView extends View {

    /**
     * The view paths for blade to look for file.
     * @var array
     */
    public $viewPaths = [];

    /**
     * Where the cached views are located.
     * @var string
     */
    public $cachePath;

    /**
     * @var Illuminate\Container\Container
     */
    protected $container;

    /**
     * @var Illuminate\View\Environment
     */
    protected $instance;

    /**
     * The the file extension to look for.
     * @var string
     */
    public $_ext = '.blade.php';

    /**
     * Class startup.
     * @param Request $request
     * @param Response $response
     * @param EventManager $eventManager
     * @param array $viewOptions
     */
    public function __construct(Request $request = null, Response $response = null, EventManager $eventManager = null, array $viewOptions = []) {

        // Call base
        parent::__construct($request, $response, $eventManager, $viewOptions);

        $this->container = new Container;

        $this->viewPaths = [APP.'Template/'];

        $this->cachePath = CACHE.'blade';

        $this->registerFilesystem();

        $this->registerEvents();

        $this->registerEngineResolver();

        $this->registerViewFinder();

        $this->instance = $this->registerEnvironment();

        $this->registerShares();

    }

    /**
     * Sandbox method to evaluate a template / view script in.
     *
     * @param string $viewFile Filename of the view
     * @param array $dataForView Data to include in rendered view.
     *    If empty the current View::$viewVars will be used.
     * @return string Rendered output
     */
    protected function _evaluate($viewFile, $dataForView) {

        // Convert to a blade readable path
        $bladeViewFile = $this->_getViewFileNameBlade($viewFile);

        // Compile
        return $this->renderBlade($bladeViewFile, $dataForView);

    }

    /**
     * Take the file path from cake's templates and work it into what blade can use.
     * @param $viewFile
     * @return mixed
     */
    public function _getViewFileNameBlade($viewFile) {

        // Remove the full path
        foreach($this->viewPaths as $path) {
            $fileName = str_replace($path, '', $this->_getViewFileName($viewFile));
        }
        // Drop the extension
        $fileName = str_replace($this->_ext, '', $fileName);
        // Convert slashes into periods.
        $fileName = str_replace('/', '.', $fileName);

        return $fileName;

    }

    /**
     * Extend Blade.
     * @param callable $function
     * @return mixed
     */
    public function extendBlade(Closure $function) {

        // Get the blade compiler
        $bladeCompiler = $this->container['blade.compiler'];

        return $bladeCompiler->extend($function);

    }

    /**
     * Turn CakePHP template functions into Blade functions.
     */
    public function registerShares() {

        // Share the View with Blade.
        $this->instance->share('view', $this);

        // Load the helpers. Turn $this->Html into $Html
        // Helpers MUST be define in a controller to be used this way.
        $registry = $this->helpers();
        $helpers = $registry->normalizeArray($this->helpers);
        foreach($helpers as $properties) {
            $class = strtolower($properties['class']);
            // Turn $this->Html->css() into @html->css()
            // This only works if the helper is loaded from a controller. Need to see about getting all attached helpers.
            $this->extendBlade(function ($view) use ($class, $properties) {
                $pattern = '/(?<!\w)(\s*)@' . $class . '\-\>((?:[a-z][a-z]+))(\s*\(.*\))/';
                return preg_replace($pattern, '$1<?php echo $view->' . $properties['class'] . '->$2$3; ?>', $view);
            });
        }

        // Turn $this->fetch() into @fetch()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('fetch');
            return preg_replace($pattern, '$1<?php echo $view->fetch$2; ?>', $view);
        });

        // Turn $this->start() into @start()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('start');
            return preg_replace($pattern, '$1<?php echo $view->start$2; ?>', $view);
        });

        // Turn $this->append() into @append()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('append');
            return preg_replace($pattern, '$1<?php echo $view->append$2; ?>', $view);
        });

        // Turn $this->prepend() into @prepend()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('prepend');
            return preg_replace($pattern, '$1<?php echo $view->prepend$2; ?>', $view);
        });

        // Turn $this->assign() into @assign()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('assign');
            return preg_replace($pattern, '$1<?php echo $view->assign$2; ?>', $view);
        });

        // Turn $this->end() into @end()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('end');
            return preg_replace($pattern, '$1<?php echo $view->end(); ?>$2', $view);
        });

        // Turn $this->element() into @element()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('element');
            return preg_replace($pattern, '$1<?php echo $view->element$2; ?>', $view);
        });

        // Turn $this->cell() into @cell()
        $this->extendBlade(function($view, $compiler) {
            $pattern = $compiler->createMatcher('cell');
            return preg_replace($pattern, '$1<?php echo $view->cell$2; ?>', $view);
        });

    }

    /**
     * Wrapper for our instance of blade.
     * @param $file
     * @param $data
     * @return mixed
     */
    public function renderBlade($file, $data) {
        return $this->instance->make($file, $data);
    }

    /**
     * Register the file system.
     */
    public function registerFilesystem() {
        $this->container->bindShared('files', function() {
            return new Filesystem;
        });
    }

    /**
     * Register the events.
     */
    public function registerEvents() {
        $this->container->bindShared('events', function() {
            return new Dispatcher;
        });
    }

    /**
     * Register the engine resolver instance.
     */
    public function registerEngineResolver() {
        $self = $this;

        $this->container->bindShared('view.engine.resolver', function($app) use($self) {

            $resolver = new EngineResolver;

            // Register the engines.
            $self->registerPhpEngine($resolver);
            $self->registerBladeEngine($resolver);

            return $resolver;

        });
    }

    /**
     * Register the PHP engine implementation.
     * @param EngineResolver $resolver
     */
    public function registerPhpEngine(EngineResolver $resolver) {
        $resolver->register('php', function() {
            return new PhpEngine;
        });
    }

    /**
     * Register the Blade engine implementation.
     * @param EngineResolver $resolver
     */
    public function registerBladeEngine(EngineResolver $resolver) {
        $self = $this;
        $app = $this->container;

        $this->container->bindShared('blade.compiler', function($app) use($self) {
            $cache = $self->cachePath;
            return new BladeCompiler($app['files'], $cache);
        });

        $resolver->register('blade', function() use ($app) {
            return new CompilerEngine($app['blade.compiler'], $app['files']);
        });
    }

    /**
     * Register the view finder implementation.
     */
    public function registerViewFinder() {
        $self = $this;

        $this->container->bindShared('view.finder', function($app) use($self) {
            $paths = $self->viewPaths;
            return new FileViewFinder($app['files'], $paths);
        });
    }

    /**
     * Register the view environment.
     * @return Environment
     */
    public function registerEnvironment() {
        // Next we need to grab the engine resolver instance that will be used by the
        // environment. The resolver will be used by an environment to get each of
        // the various engine implementations such as plain PHP or Blade engine.
        $resolver = $this->container['view.engine.resolver'];
        $finder = $this->container['view.finder'];
        $env = new Environment($resolver, $finder, $this->container['events']);

        // We will also set the container instance on this view environment since the
        // view composers may be classes registered in the container, which allows
        // for great testable, flexible composers for the application developer.
        $env->setContainer($this->container);

        return $env;
    }

}