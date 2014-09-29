<?php
namespace Dowilcox\BladeView\Blade;

use Illuminate\View\Compilers\BladeCompiler;

class Extensions {

    /**
     * @var BladeCompiler
     */
    protected $compiler;

    /**
     * The loaded view helpers
     * @var array
     */
    protected $helpers = [];

    public function __construct(BladeCompiler $compiler, $helpers = []) {

        $this->compiler = $compiler;

        $this->helpers = $helpers;

        $this->helpers();

        $this->fetch();

        $this->start();

        $this->append();

        $this->prepend();

        $this->assign();

        $this->end();

        $this->element();

        $this->cell();

    }

    /**
     * Turn $this->Html->css() into @html->css().
     * This only works if the helper is loaded from a controller. Need to see about getting all attached helpers.
     */
    protected function helpers() {
        foreach($this->helpers as $properties) {
            $this->compiler->extend(function($view) use($properties) {
                $pattern = '/(?<!\w)(\s*)@' . strtolower($properties['class']) . '\-\>((?:[a-z][a-z]+))(\s*\(.*\))/';
                return preg_replace($pattern, '$1<?php echo $_view->' . $properties['class'] . '->$2$3; ?>', $view);
            });
        }
    }

    /**
     * Turn $this->fetch() into @fetch().
     */
    protected function fetch() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('fetch');
            return preg_replace($pattern, '$1<?php echo $_view->fetch$2; ?>', $view);
        });
    }

    /**
     * Turn $this->start() into @start().
     */
    protected function start() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('start');
            return preg_replace($pattern, '$1<?php echo $_view->start$2; ?>', $view);
        });
    }

    /**
     * Turn $this->append() into @append().
     */
    protected function append() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('append');
            return preg_replace($pattern, '$1<?php echo $_view->append$2; ?>', $view);
        });
    }

    /**
     * Turn $this->prepend() into @prepend().
     */
    protected function prepend() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('prepend');
            return preg_replace($pattern, '$1<?php echo $_view->prepend$2; ?>', $view);
        });
    }

    /**
     * Turn $this->assign() into @assign().
     */
    protected function assign() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('assign');
            return preg_replace($pattern, '$1<?php echo $_view->assign$2; ?>', $view);
        });
    }

    /**
     * Turn $this->end() into @end().
     */
    protected function end() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createPlainMatcher('end');
            return preg_replace($pattern, '$1<?php echo $_view->end(); ?>$2', $view);
        });
    }

    /**
     * Turn $this->element() into @element().
     */
    protected function element() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('element');
            return preg_replace($pattern, '$1<?php echo $_view->element$2; ?>', $view);
        });
    }

    /**
     * Turn $this->cell() into @cell().
     */
    protected function cell() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('cell');
            return preg_replace($pattern, '$1<?php echo $_view->cell$2; ?>', $view);
        });
    }

    /**
     * Turn $this->extend() into @extend().
     */
    protected function extend() {
        $this->compiler->extend(function($view, $compiler) {
            $pattern = $compiler->createMatcher('extend');
            return preg_replace($pattern, '$1<?php echo $_view->extend$2; ?>', $view);
        });
    }

}