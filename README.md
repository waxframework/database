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
	- [Aggregates](#aggregates)
	- [Retrieving Models](#retrieving-models)
		- [All Records](#all-records)
		- [Single Record](#single-record)
	- [Select Statements](#select-statements)
	- [Join](#join)
		- [Inner Join Clause](#inner-join-clause)
		- [Left Join / Right Join Clause](#left-join--right-join-clause)
	- [Basic Where Clauses](#basic-where-clauses)
		- [Where Clauses](#where-clauses)
		- [Or Where Clauses](#or-where-clauses)
	- [Advanced Where Clauses](#advanced-where-clauses)
		- [Where Exists Clauses](#where-exists-clauses)
	- [Additional Where Clauses](#additional-where-clauses)
		- [where\_between / or\_where\_between](#where_between--or_where_between)
		- [where\_not\_between / or\_where\_not\_between](#where_not_between--or_where_not_between)
		- [where\_in / where\_not\_in / or\_where\_in / or\_where\_not\_in](#where_in--where_not_in--or_where_in--or_where_not_in)
	- [Ordering, Grouping, Limit \& Offset](#ordering-grouping-limit--offset)
		- [Ordering](#ordering)
			- [The order\_by Method](#the-order_by-method)
		- [Grouping](#grouping)
			- [The group\_by \& having Methods](#the-group_by--having-methods)
		- [Limit \& Offset](#limit--offset)
			- [The limit \& offset Methods](#the-limit--offset-methods)
- [Relationships](#relationships)
	- [One To One](#one-to-one)

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

## Aggregates
The query builder also provides a variety of methods for retrieving aggregate values like `count`, `max`, `min`, `avg`, and `sum`. You may call any of these methods after constructing your query:
```php
$posts = Post::query()->count();
```
## Retrieving Models
### All Records
To get all the posts, use the `get` method as shown below:
```php
$posts = Post::query()->get();
```
### Single Record
To retrieve a single record from the database, use the `first` method as shown below:
```php
$posts = Post::query()->where('id', 100)->first();
```
## Select Statements
You may not always want to select all columns from a database table. Using the `select` method, you can specify a custom "select" clause for the query:
```php
$posts = Post::query()->select('post_title', 'post_date')->get();

```
The `distinct` method allows you to force the query to return distinct results:

```php
$posts = Post::query()->distinct()->select('post_title', 'post_date')->get();
```
## Join

### Inner Join Clause
To add join clauses to your SQL queries using the query builder, you can use the `join` method. This method is used to perform an inner join between two or more database tables. The first argument passed to the `join` method is the name of the table you want to join, and the remaining arguments specify the column constraints for the join.
```php
$users = User::query()
                ->join('contacts', 'users.id', '=', 'contacts.user_id')
                ->select('users.*', 'contacts.phone', 'contacts.email')
                ->get();
```
In this example, we are joining the `users` table with the contacts table on the id column of `users` and the `user_id` column of `contacts`. We are also selecting all columns from users and the `phone` and `email` columns from `contacts`.

You can even join multiple tables in a single query using the `join` method multiple times. For example:

```php
$users = User::query()
                ->join('contacts', 'users.id', '=', 'contacts.user_id')
                ->join('orders', 'users.id', '=', 'orders.user_id')
                ->select('users.*', 'contacts.phone', 'orders.price')
                ->get();

```
In this example, we are joining the `users` table with the `contacts` table and the `orders` table. We are selecting all columns from `users`, the phone column from `contacts`, and the price column from `orders`.

Here is an example of how to use the join method to join two tables:

### Left Join / Right Join Clause
To perform a "left join" or "right join" instead of an "inner join", you can use the `left_join` or `right_join` methods respectively. These methods have the same signature as the `join` method, which means that you need to pass the name of the table you want to join as the first argument, and then specify the column constraints for the join as the remaining arguments.

For example, to perform a "left join" on the `users` and `posts` tables using the `user_id` column as the join constraint, you can do the following:
```php
$users = User::query()
            ->left_join('posts', 'users.id', '=', 'posts.user_id')
            ->get();
```

This will return all the rows from the `users` table along with their matching rows from the `posts` table based on the `user_id` column. If a user has no matching posts, the values from the `posts` table will be `NULL`.

Similarly, to perform a "right join" on the users and `posts` tables using the `user_id` column as the join constraint, you can do the following:
```php
$users = User::query()
            ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
            ->get();

```
This will return all the rows from the `posts` table along with their matching rows from the `users` table based on the `user_id` column. If a post has no matching user, the values from the `users` table will be NULL.

## Basic Where Clauses

### Where Clauses
To get only published posts, use the `where` method as shown below:

```php
$posts = Post::query()->where('post_status', 'publish')->get();
```
### Or Where Clauses
To get only published posts or if post_title is test post, use the `where` method as shown below:

```php
$posts = Post::query()->where('post_status', 'publish')->orWhere('post_title', 'test post')->get();
```

## Advanced Where Clauses
### Where Exists Clauses

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

## Additional Where Clauses

### where_between / or_where_between

The `where_between` method verifies that a column's value is between two values:
```php
$posts = Post::query()->where_between('ID', [1, 100])->get();
```

### where_not_between / or_where_not_between
The `where_not_between` method verifies that a column's value lies outside of two values:
```php
$posts = Post::query()->where_not_between('ID', [1, 100])->get();
```
### where_in / where_not_in / or_where_in / or_where_not_in
The `where_in` method verifies that a given column's value is contained within the given array:

```php
$posts = Post::query()->where_in('ID', [100, 105])->get();
```

The `where_not_in` method verifies that the given column's value is not contained in the given array:
```php
$posts = Post::query()->where_not_in('ID', [100, 105])->get();
```

## Ordering, Grouping, Limit & Offset
### Ordering
#### The order_by Method
The `order_by` method allows you to sort the results of the query by a given column. The first argument accepted by the `order_by` method should be the column you wish to sort by, while the second argument determines the direction of the sort and may be either `asc` or `desc`:
```php
$posts = Post::query()->order_by('post_title')->get();
```
To sort by multiple columns, you may simply invoke `order_by` as many times as necessary:

```php
$posts = Post::query()->order_by('post_title')->order_by_desc('post_status')->get();
```
### Grouping
#### The group_by & having Methods
As you might expect, the `group_by` and `having` methods may be used to group the query results. The `having` method's signature is similar to that of the `where` method:
```php
$posts = Post::query()->group_by('post_author')->having('post_author', '>', 100)->get();

```
### Limit & Offset
#### The limit & offset Methods

You may use the `limit` and `offset` methods to limit the number of results returned from the query or to skip a given number of results in the query:
```php
$posts = Post::query()->offset(10)->limit(5)->get();
```
# Relationships
Database tables are often related to one another. For example, a blog post may have many comments or an order could be related to the user who placed it. Eloquent makes managing and working with these relationships easy, and supports a variety of common relationships:

## One To One
A one-to-one relationship is a very basic type of database relationship. For example, a `User` model might be associated with one `Phone` model. To define this relationship, we will place a `Phone` method on the `User` model. The `Phone` method should call the `has_one` method and return its result. The `has_one` method is available to your model via the model's `WaxFramework\Database\Eloquent\Model` base class:
```php
<?php

namespace WaxFramework\App\Models;

use WaxFramework\Database\Eloquent\Model;
use WaxFramework\Database\Eloquent\Relations\HasOne;

class User extends Model {

	/**
     * Get the phone associated with the user.
     */
    public function phone(): HasOne
    {
        return $this->has_one(Phone::class, 'ID', 'user_id');
    }
}
```
Eloquent assumes that the foreign key should have a value matching the primary key column of the parent. In other words, Eloquent will look for the value of the user's `ID` column in the `user_id` column of the `Phone` record.

Now, let's retrieve all Users and their phone:

```php
$users = User::query()->with('phone')->get();
```