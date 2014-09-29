BladeView
=========
Laravel's Blade template engine in CakePHP 3.

Install
=======
Composer:
```json
    [
        "require": {
            "dowilcox/blade-view": "0.2.*"
        }
    ]
```

In your bootstrap.php:
```php
Plugin::load('BladeView', ['bootstrap' => false]);
```

In your controller:
```php
public $viewClass = '\Dowilcox\BladeView\View\BladeView';
```

Now change all the template files in src/Template from .ctp to .blade.php

Usage
=====
See Laravel's documenation for Blade: http://laravel.com/docs/4.2/templates.

CakePHP view functions and helpers work a bit different.

###Variables
Before:
```php
<?php echo h($variable); ?>
```
After:
```php
{{{ $variable }}}
```

###View functions:
Before:
```php
<?php echo $this->fetch(); ?>
```
After:
```php
@fetch()
```

###Helpers (if loaded in a controller):
Before:
```php
<?php echo $this->Html->css(); ?>
```
After:
```php
@html->css()
```

More Examples
=============

	{{-- src/Template/Common/view.blade.php --}}
    <h1>@fetch('title')</h1>
    @fetch('content')
    
    <div class="actions">
        <h3>Related actions</h3>
        <ul>
        @fetch('sidebar')
        </ul>
    </div>


    {{-- src/Template/Posts/view.blade.php --}}
    @extend('/Common/view')
    
    @assign('title', $post)
    
    @start('sidebar')
    <li>
        @html->link('edit', [
            'action' => 'edit',
            $post['Post']['id']
        ])
    </li>
    @end;
    
    {{-- The remaining content will be available as the 'content' block --}}
    {{-- In the parent view. --}}
    {{{ $post['Post']['body'] }}}
