<p align="center">
<a href="https://packagist.org/packages/waxframework/database"><img src="https://img.shields.io/packagist/dt/waxframework/database" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/waxframework/database"><img src="https://img.shields.io/packagist/v/waxframework/database" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/waxframework/database"><img src="https://img.shields.io/packagist/l/waxframework/database" alt="License"></a>
</p>

# About WaxFramework Database

WaxFramework Database is a robust and versatile SQL query builder designed specifically for WordPress plugins. It provides a similar experience to Laravel's Eloquent Query Builder, a well-known and widely-used PHP framework.

- [About WaxFramework Database](#about-waxframework-database)
- [Installation](#installation)
- [Create Eloquent Model](#create-eloquent-model)
- [Insert Data](#insert-data)
- [Update Data](#update-data)

# Installation
To install the WaxFramework Routing package, simply run the following command via Composer:
```
composer require waxframework/routing
```

# Create Eloquent Model
To create an Eloquent model, you can use the following code snippet.
```php
<?php

namespace WaxFramework\App\Models;

use WaxFramework\Database\Eloquent\Model;
use WaxFramework\Database\Resolver;

class Post extends Model {

	public static function get_table_name():string {
		return 'posts';
	}

	public function resolver():Resolver {
		return new Resolver;
	}
}
```
# Insert Data
You can insert data into the `posts` table using the query builder provided by Eloquent. Here's an example of how to insert a single item:
```php
Post::query()->insert([
	'post_author' => wp_get_current_user()->ID,
	'post_title' => "Test Post"
	...
]);
		
```
To insert multiple items at once, simply pass an array of arrays:

```php
$post_author = wp_get_current_user()->ID;

Post::query()->insert([
	[
		'post_author' => $post_author,
		'post_title' => "Test Post 1"
		...
	],
	[
		'post_author' => $post_author,
		'post_title' => "Test Post 2"
		...
	]
]);
```

You can also insert an item and retrieve its ID in one step using the `insert_get_id` method:

```php
$post_id = Post::query()->insert_get_id([
	'post_author' => wp_get_current_user()->ID,
	'post_title' => "Test Post"
	// ...
]);
```
# Update Data

```php
Post::query()->where('post_id', 100)->update([
	'post_title' => "Test Post"
]);
```