<?php
namespace Dowilcox\BladeView\View;

use Cake\Core\Configure;
use Cake\View\View;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Event\EventManager;
use Dowilcox\BladeView\Blade\Extensions;
use Dowilcox\BladeView\Blade\ServiceProvider;
use Closure;

class BladeView extends View {

    /**
     * The the file extension to look for.
     * @var string
     */
    protected $_ext = '.blade.php';

    /**
     * @var BladeServiceProvider
     */
    protected $_serviceProvider;

    /**
     * @var BladeServiceProvider
     */
    protected $_blade;

    /**
     * The loaded helpers.
     * @var array
     */
    protected $_loadedHelpers = [];

    public function __construct(Request $request = null, Response $response = null, EventManager $eventManager = null, array $viewOptions = []) {

        parent::__construct($request, $response, $eventManager, $viewOptions);

        $this->_serviceProvider = new ServiceProvider(Configure::read('App.paths.templates'), CACHE.'bladeView');

        $this->loadBlade();

        $this->loadHelpers();

        $this->loadExtensions();

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
        $bladeViewFile = $this->_convertFileNameForBlade($viewFile);

        $blade = $this->getBlade();

        $blade->share('_view', $this);

        // Compile
        return $blade->make($bladeViewFile, $dataForView);
    }

    /**
     * Take the view file and convert it into something blade can read.
     * @param $viewFile
     * @return string
     */
    protected function _convertFileNameForBlade($viewFile) {
        $templatePaths = Configure::read('App.paths.templates');

        // Remove the full path
        foreach($templatePaths as $path) {
            $fileName = str_replace($path, '', $this->_getViewFileName($viewFile));
        }
        // Drop the extension
        $fileName = str_replace($this->_ext, '', $fileName);
        // Convert slashes into periods.
        $fileName = str_replace('/', '.', $fileName);

        return $fileName;
    }

    /**
     * Get the helpers.
     */
    protected function loadHelpers() {
        $registry = $this->helpers();

        $this->_loadedHelpers = $registry->normalizeArray($this->helpers);
    }

    /**
     * Load the custom blade extensions for CakePHP.
     * @return Extensions
     */
    protected function loadExtensions() {
        $compiler = $this->_serviceProvider->getCompiler();
        $helpers = $this->_loadedHelpers;

        $extensions = new Extensions($compiler, $helpers);
        return $extensions;
    }

    /**
     * Load blade from the service provider.
     */
    protected function loadBlade() {
        $this->_blade = $this->_serviceProvider()->getFactory();
    }

    /**
     * Get our Blade factory.
     * @return BladeServiceProvider
     */
    public function getBlade() {
        return $this->_blade;
    }

    /**
     * Extend Blade.
     * @param callable $function
     * @return mixed
     */
    public function extendBlade(Closure $function) {
        // Get the blade compiler
        $compiler = $this->_bladeServiceProvider()->getCompiler();

        return $compiler->extend($function);
    }

}