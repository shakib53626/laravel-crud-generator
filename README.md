# Laravel CRUD Generator

One command to scaffold: **Migration, Model, Controller, Form Requests, and API Resource**.

---

## Installation

### Local (same machine, during development)

In your Laravel project's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../laravel-crud-generator"
    }
],
"require": {
    "shakib53626/laravel-crud-generator": "*"
}
```

Then run:

```bash
composer require shakib53626/laravel-crud-generator
```

### Via Packagist (after publishing)

```bash
composer require shakib53626/laravel-crud-generator
```

---

## Usage

### Web (Inertia) CRUD

```bash
php artisan make:crud Post
php artisan make:crud ProductCategory
```

### API CRUD

```bash
php artisan make:crud Post --api
php artisan make:crud ProductCategory --api
```

### Force overwrite existing files

```bash
php artisan make:crud Post --force
```

---

## What Gets Generated

| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_create_posts_table.php` |
| Model | `app/Models/Post.php` |
| Controller (Web) | `app/Http/Controllers/PostController.php` |
| Controller (API) | `app/Http/Controllers/Api/PostController.php` |
| StoreRequest | `app/Http/Requests/Post/StorePostRequest.php` |
| UpdateRequest | `app/Http/Requests/Post/UpdatePostRequest.php` |
| API Resource | `app/Http/Resources/PostResource.php` |

---

## Customize Stubs

Publish the stubs to your project and edit them:

```bash
php artisan vendor:publish --tag=crud-stubs
```

Stubs will be placed in `stubs/crud-generator/`. The command will use your custom stubs automatically.

---

## Route Registration

After generation, add to `routes/web.php` (web):

```php
Route::resource('posts', \App\Http\Controllers\PostController::class);
```

Or `routes/api.php` (API):

```php
Route::apiResource('posts', \App\Http\Controllers\Api\PostController::class);
```

Then migrate:

```bash
php artisan migrate
```
