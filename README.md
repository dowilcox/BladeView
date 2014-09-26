BladeView
=========
Laravel's Blade template engine in CakePHP 3.

Install
=======
Composer:
```json
[
	"require": {
	    "dowilcox/bladeview": "dev-master"
	}
]
```

In your bootstrap.php:
```php
Plugin::load('BladeView', ['bootstrap' => false]);
```

In your controller:
```php
public $viewClass = 'BladeView.Blade';
```

Now change all the template files in src/Template from .ctp to .blade.php

Usage
=====
See Laravel's documenation for Blade: http://laravel.com/docs/4.1/templates.

CakePHP view functions and helpers work a bit different.

###Variables
Before:
```php
<?php echo $variable; ?>
```
After:
```php
{{ $variable }}
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
{{ $Html->css() }}
```

###Helpers (if NOT loaded in a controller):
Before:
```php
<?php echo $this->Html->css(); ?>
```
After:
```php
{{ $view->Html->css() }}
```