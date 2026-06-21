# Laravel CRUD Generator 🚀

একটি কমান্ডেই তৈরি করুন: **Migration, Model, Repository, Controller, Request, এবং API Resource**!

---

## 📦 ইন্সটলেশন

### লোকাল (ডেভেলপমেন্টের সময় একই মেশিনে)

আপনার Laravel প্রজেক্টের `composer.json` ফাইলে নিচের কোড যোগ করুন:

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

তারপর নিচের কমান্ড চালান:

```bash
composer require shakib53626/laravel-crud-generator
```

### Packagist এর মাধ্যমে (প্রকাশ করার পর)

```bash
composer require shakib53626/laravel-crud-generator
```

---

## 🛠️ ব্যবহার

### সাধারণ Web CRUD (Inertia)

```bash
php artisan make:crud Post
php artisan make:crud ProductCategory
```

### API CRUD

```bash
php artisan make:crud Post --api
php artisan make:crud ProductCategory --api
```

### কলামসহ CRUD তৈরি

```bash
php artisan make:crud Brand --columns=name:string,slug:string:unique,description:text,is_active:boolean
```

### Soft Delete সহ CRUD

```bash
php artisan make:crud Brand --softdelete --columns=name:string
```

### Guarded ব্যবহার করে (Fillable না)

```bash
php artisan make:crud Brand --guarded --columns=name:string
```

### নির্দিষ্ট ফাইল তৈরি

```bash
php artisan make:crud Brand --files=model,controller,repository
```

### বিদ্যমান ফাইল ওভাররাইট করে

```bash
php artisan make:crud Brand --force
```

---

## 📋 কি কি তৈরি হয়?

| ফাইল | পাথ |
|------|------|
| Migration | `database/migrations/xxxx_create_posts_table.php` |
| Model | `app/Models/Post.php` |
| Repository | `app/Repositories/PostRepository.php` |
| Request | `app/Http/Requests/PostRequest.php` |
| Controller (Web) | `app/Http/Controllers/PostController.php` |
| Controller (API) | `app/Http/Controllers/Api/PostController.php` |
| API Resource | `app/Http/Resources/PostResource.php` |

---

## 🎨 স্টাব কাস্টমাইজ করুন

আপনার প্রজেক্টে স্টাবগুলো পাবলিশ করে এডিট করুন:

```bash
php artisan vendor:publish --tag=crud-stubs
```

স্টাবগুলো `stubs/crud-generator/` ফোল্ডারে থাকবে। কমান্ডটি আপনার কাস্টম স্টাবগুলো স্বয়ংক্রিয়ভাবে ব্যবহার করবে।

---

## 🛣️ রাউট রেজিস্ট্রেশন

জেনারেশনের পরে, `routes/web.php` (Web) তে যোগ করুন:

```php
Route::resource('posts', \App\Http\Controllers\PostController::class);
```

অথবা `routes/api.php` (API) তে:

```php
Route::apiResource('posts', \App\Http\Controllers\Api\PostController::class);
```

তারপর মাইগ্রেট করুন:

```bash
php artisan migrate
```

---

## 📚 সমস্ত অপশনের তালিকা

| অপশন | কাজ |
|-------|------|
| `--columns=` | কলামগুলো ডিফাইন করুন (যেমন: `name:string,slug:string:unique`) |
| `--api` | API কন্ট্রোলার তৈরি করুন |
| `--softdelete` | Soft Delete সাপোর্ট যোগ করুন |
| `--guarded` | `$fillable` এর বদলে `$guarded = []` ব্যবহার করুন |
| `--files=` | নির্দিষ্ট ফাইল তৈরি করুন (যেমন: `model,controller,repository`) |
| `--force` | বিদ্যমান ফাইল ওভাররাইট করুন |
