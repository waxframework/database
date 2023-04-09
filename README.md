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
- [Delete Data](#delete-data)
- [Read Data](#read-data)
	- [get()](#get)
	- [where()](#where)
	- [where\_in()](#where_in)
	- [first()](#first)
	- [order\_by()](#order_by)
	- [order\_by\_desc()](#order_by_desc)
	- [group\_by()](#group_by)
	- [where\_between()](#where_between)
	- [where\_exists() and where\_column()](#where_exists-and-where_column)
	- [the group\_by and having Methods](#the-group_by-and-having-methods)
	- [The limit \& offset Methods](#the-limit--offset-methods)

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
To update a post where the post id is 100, use the following code:
```php
Post::query()->where('post_id', 100)->update([
	'post_title' => "Test Post"
]);
```
# Delete Data
To delete a post where the post id is 100, use the following code:
```php
Post::query()->where('post_id', 100)->delete();
```

# Read Data
To retrieve data, the WaxFramework Database offers a variety of methods:
Get all posts

## get()
To get all the posts, use the `get` method as shown below:
```php
$posts = Post::query()->get();
```
## where()
To get only published posts, use the `where` method as shown below:

```php
$posts = Post::query()->where('post_status', 'publish')->get();
```
## where_in()
To get posts by given ids, use the `where_in` method as shown below:

```php
$posts = Post::query()->where_in('ID', [100, 105])->get();
```
## first()
To retrieve a single record from the database, use the `first` method as shown below:
```php
$posts = Post::query()->where('id', 100)->first();
```
## order_by()
Order posts ascending order by post_id column using `order_by`

```php
$posts = Post::query()->order_by('post_id')->get();
```
## order_by_desc()
Order posts descending order by post_id column using `order_by_desc`

```php
$posts = Post::query()->order_by_desc('post_id')->get();
```
## group_by()
Group the posts by post_author column using `group_by` method 

```php
$posts = Post::query()->group_by('post_author')->get();

```
## where_between()
The `where_between` method verifies that a column's value is between two values:
```php
$posts = Post::query()->where_between('ID', [1, 100])->get();
```
## where_exists() and where_column()
The `where_exists` and `where_column` methods are useful when you need to retrieve data from two different tables that have a common column.

To get all posts if the post has meta data, you can use either of the following two processes:

1. Process One: In this process, we use a closure function to define a subquery that selects `1` from the `postmeta` table where the `post_id` column in `postmeta` table is equal to the `ID` column in the `posts` table. The closure function is passed as an argument to the `where_exists` method to filter the posts.
	```php
	$posts = Post::query()->(function(Builder $query) {
		$query->select(1)->from('postmeta')->where_column('postmeta.post_id', 'posts.id')->limit(1);
	})->get();
	```

2. Alternatively Process: In this process, we first define a variable `$post_meta` that selects `1` from the `postmeta` table where the `post_id` column in `postmeta` table is equal to the `ID` column in the `posts` table. Then we use the `where_exists` method and pass the `$post_meta` variable as an argument to filter the posts.
	```php
	$post_meta = PostMeta::query()->select(1)->where_column('postmeta.post_id', 'posts.id')->limit(1);
	$posts     = Post::query()->where_exists($post_meta)->get();
	```
In both of these processes, we use the `where_column` method to specify the column names in the two tables that should be compared. This allows us to filter the posts based on whether or not they have meta data.

## the group_by and having Methods
As you might expect, the `group_by` and `having` methods may be used to group the query results. The `having` method's signature is similar to that of the `where` method:

```php
$posts = Post::query()->group_by('post_author')->having('post_author', '>', 100)->get();
```

## The limit & offset Methods

You may use the `limit` and `offset` methods to limit the number of results returned from the query or to skip a given number of results in the query:

```php
$posts = Post::query()->offset(10)->limit(5)->get();
```