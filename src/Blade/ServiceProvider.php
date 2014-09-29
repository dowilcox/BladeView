<?php
namespace Dowilcox\BladeView\Blade;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Factory;

class ServiceProvider {

    /**
     * The paths for blade to look for files.
     * @var array
     */
    protected $viewPaths = [];

    /**
     * The path for the cache files.
     * @var string
     */
    protected $cachePath;

    /**
     * The container.
     * @var Container
     */
    protected $app;


    protected $factory;

    public function __construct($viewPaths = [], $cachePath) {

        $this->viewPaths = $viewPaths;

        $this->cachePath = $cachePath;

        $this->app = new Container();

        $this->registerFileSystem();

        $this->registerEvents();

        $this->registerEngineResolver();

        $this->registerViewFinder();

        $this->registyFactory();

    }

    /**
     * Register the file system.
     */
    protected function registerFileSystem() {
        $this->app->bindShared('files', function() {
            return new Filesystem;
        });
    }

    /**
     * Register the events system.
     */
    protected function registerEvents() {
        $this->app->bindShared('events', function() {
            return new Dispatcher;
        });
    }

    /**
     * Register the engine resolver instance.
     *
     * @return void
     */
    protected function registerEngineResolver() {
        $this->app->bindShared('view.engine.resolver', function() {
            $resolver = new EngineResolver;

            // Next we will register the various engines with the resolver so that the
            // environment can resolve the engines it needs for various views based
            // on the extension of view files. We call a method for each engines.
            foreach (array('php', 'blade') as $engine)
            {
                $this->{'register'.ucfirst($engine).'Engine'}($resolver);
            }

            return $resolver;
        });
    }

    /**
     * Register the PHP engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    protected function registerPhpEngine($resolver) {
        $resolver->register('php', function() { return new PhpEngine; });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    protected function registerBladeEngine($resolver) {
        $app = $this->app;
        $self = $this;

        // The Compiler engine requires an instance of the CompilerInterface, which in
        // this case will be the Blade compiler, so we'll first create the compiler
        // instance to pass into the engine so it can compile the views properly.
        $app->bindShared('blade.compiler', function($app) use($self) {
            return new BladeCompiler($app['files'], $self->cachePath);
        });

        $resolver->register('blade', function() use ($app) {
            return new CompilerEngine($app['blade.compiler'], $app['files']);
        });
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    protected function registerViewFinder() {
        $self = $this;

        $this->app->bindShared('view.finder', function($app) use($self) {
            return new FileViewFinder($app['files'], $self->viewPaths);
        });
    }

    /**
     * Register the view environment.
     *
     * @return void
     */
    protected function registerFactory() {
        // Next we need to grab the engine resolver instance that will be used by the
        // environment. The resolver will be used by an environment to get each of
        // the various engine implementations such as plain PHP or Blade engine.
        $resolver = $this->app['view.engine.resolver'];

        $finder = $this->app['view.finder'];

        $env = new Factory($resolver, $finder, $this->app['events']);

        // We will also set the container instance on this view environment since the
        // view composers may be classes registered in the container, which allows
        // for great testable, flexible composers for the application developer.
        $env->setContainer($this->app);

        $env->share('app', $this->app);

        $this->factory = $env;
    }

    /**
     * Get the factory.
     * @return mixed
     */
    public function getFactory() {
        return $this->factory;
    }

    /**
     * Get the blade compiler.
     * @return mixed
     */
    public function getCompiler() {
        return $this->app['blade.compiler'];
    }

}