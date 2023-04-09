<p align="center">
<a href="https://packagist.org/packages/waxframework/database"><img src="https://img.shields.io/packagist/dt/waxframework/database" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/waxframework/database"><img src="https://img.shields.io/packagist/v/waxframework/database" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/waxframework/database"><img src="https://img.shields.io/packagist/l/waxframework/database" alt="License"></a>
</p>

# About WaxFramework Database

WaxFramework Database is a powerful Sql query builder for WordPress plugins that is similar to the popular PHP framework Laravel Eloquent Query Builder.

- [About WaxFramework Database](#about-waxframework-database)
- [Create Eloquent Model](#create-eloquent-model)

# Create Eloquent Model
To get started, let's create an Eloquent model. 
```php
<?php

namespace WaxFramework\App\Models;

use WaxFramework\Database\Eloquent\Model;
use WaxFramework\Database\Resolver;

class User extends Model {

	public static function get_table_name():string {
		return 'users';
	}

	public function resolver():Resolver {
		return new Resolver;
	}
}
```
