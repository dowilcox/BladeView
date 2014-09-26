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

        $this->cachePath = CACHE;

        $this->registerFilesystem();

        $this->registerEvents();

        $this->registerEngineResolver();

        $this->registerViewFinder();

        $this->instance = $this->registerEnvironment();

        $this->extendBlade();

    }

    /**
     * Renders and returns output for given view filename with its
     * array of data. Handles parent/extended views.
     *
     * @param string $viewFile Filename of the view
     * @param array $data Data to include in rendered view. If empty the current
     *   View::$viewVars will be used.
     * @return string Rendered output
     * @throws \LogicException When a block is left open.
     */
    protected function _render($viewFile, $data = array()) {
        if (empty($data)) {
            $data = $this->viewVars;
        }
        $this->_current = $viewFile;
        $initialBlocks = count($this->Blocks->unclosed());

        $this->dispatchEvent('View.beforeRenderFile', [$viewFile]);

        // Make the file path and name blade friendly.
        $bladeViewFile = $this->_getViewFileNameBlade($viewFile);

        // Compile the file.
        $content = $this->instance->make($bladeViewFile, $data);

        $afterEvent = $this->dispatchEvent('View.afterRenderFile', [$viewFile, $content]);
        if (isset($afterEvent->result)) {
            $content = $afterEvent->result;
        }

        if (isset($this->_parents[$viewFile])) {
            $this->_stack[] = $this->fetch('content');
            $this->assign('content', $content);

            $content = $this->_render($this->_parents[$viewFile]);
            $this->assign('content', array_pop($this->_stack));
        }

        $remainingBlocks = count($this->Blocks->unclosed());

        if ($initialBlocks !== $remainingBlocks) {
            throw new LogicException(sprintf(
                'The "%s" block was left open. Blocks are not allowed to cross files.',
                $this->Blocks->active()
            ));
        }
        return $content;
    }

    /**
     * Take the file path from cake's templates and work it into what blade can use.
     * @param $view
     * @return mixed
     */
    public function _getViewFileNameBlade($view) {

        // Remove the full path
        $fileName = str_replace($this->viewPaths[0], '', $this->_getViewFileName($view));
        $fileName = str_replace($this->_ext, '', $fileName);
        $fileName = str_replace('/', '.', $fileName);

        return $fileName;

    }

    /**
     * Extend blade by adding cake functions to the compiler.
     */
    public function extendBlade() {

        $self = $this;

        $this->instance->share('view', $this);

        // Get the blade compiler
        $bladeCompiler = $this->container['blade.compiler'];

        // Add $this->fetch to blade.
        /*$bladeCompiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('fetch');
            debug($pattern);
            $pattern = $compiler->createMatcher('datetime');

            return preg_replace($pattern, '$1<?php echo $2->format(\'m/d/Y H:i\'); ?>', $view);
        });*/

        // Testing
        /*$bladeCompiler->extend(function($view, $compiler) {

            $pattern = $compiler->createMatch('fetch');

            debug($this->fetch(''))

            return preg_replace($pattern, '$1<?php echo "blade extension test."; ?>', $view);

        });*/

    }

    public function registerFilesystem() {
        $this->container->bindShared('files', function() {
            return new Filesystem;
        });
    }

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