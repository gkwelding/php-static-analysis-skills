---
title: Laravel and Larastan
tags: laravel, larastan, eloquent, relations, casts, collections, config, env
---

## Laravel and Larastan

Checked against Larastan 3.12 on Laravel 13. Larastan reads models (migrations, casts, relations, accessors), facades and helpers so PHPStan can type them. Most Laravel errors are missing generics or Larastan not seeing information that's already in the code.

### 1. Relations: `Relation<Related, $this>`

Without type arguments, PHPStan reports `missingType.generics` at level 6, and `$user->posts` is typed as `Collection<int, Model>`, so every use of a post loses its type.

**Incorrect:**

```php
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

**Correct:**

```php
/** @return HasMany<Post, $this> */
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

/** @return BelongsTo<User, $this> */
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

The second argument is the declaring model, and `$this` is the form Laravel's own generics use (`HasMany<TRelatedModel, TDeclaringModel>`). Same for `HasOne`, `MorphMany`, `MorphTo`; `BelongsToMany<Role, $this>` also takes an optional pivot class; `HasManyThrough<Post, Country, $this>` takes the intermediate model second.

`$post->user` is then `User|null`: a `BelongsTo` can be empty even when the foreign key column isn't nullable. Handle it (`no-cheating.md` section 4):

```php
$author = $post->user ?? throw new LogicException("Post {$post->id} has no author");
```

### 2. Scopes

```php
/** @param Builder<self> $query */
public function scopePublished(Builder $query): void
{
    $query->where('published', true);
}

/** @param Builder<self> $query */
#[Scope]
protected function recent(Builder $query): void
{
    $query->where('created_at', '>', now()->subWeek());
}
```

Both styles resolve on the builder: `Post::query()->published()->recent()->get()` is `Illuminate\Database\Eloquent\Collection<int, Post>`.

### 3. Casts and Attribute Types

Larastan types attributes from migrations, then applies casts. With the Laravel 11+ `casts()` **method**, Larastan only reads the casts if the method's return type is a constant array. The usual `@return array<string, string>` (or no PHPDoc) is not, so a `datetime` cast is ignored and `$post->published_at` is typed `string|null` from the migration.

**Incorrect** (hides the real type and is never checked):

```php
/**
 * @property string $published_at
 */
class Post extends Model
```

**Correct:** let Larastan read the casts. Propose this config change:

```neon
parameters:
    parseModelCastsMethod: true
```

`$post->published_at` is then `Carbon\Carbon|null` and `meta` (cast `array`) is `array|null`. A `protected $casts = [...]` property is read without it.

`@property` / `@property-read` tags override what Larastan infers, silently: `@property string $published_at` over a `datetime` cast gives no error. Add them only for attributes Larastan can't see (a column added by raw SQL, a database view), and make them match the cast.

### 4. Accessors

An `Illuminate\Database\Eloquent\Casts\Attribute` accessor needs its generic types. Without them the method is `missingType.generics` and the property doesn't exist for PHPStan (`property.notFound`):

```php
/** @return Attribute<string, never> */
protected function excerpt(): Attribute
{
    return Attribute::get(fn (): string => Str::limit($this->title, 20));
}
```

`Attribute<TGet, TSet>`: use `never` for the set type when there is no mutator.

### 5. Collections

`Illuminate\Support\Collection<TKey, TValue>` and `Illuminate\Database\Eloquent\Collection<TKey, TModel>` are different classes; return the one the code returns.

```php
/** @return \Illuminate\Database\Eloquent\Collection<int, Post> */
public function published(): EloquentCollection
{
    return Post::query()->published()->get();
}

/** @return \Illuminate\Support\Collection<int, string> */
public function names(): Collection
{
    return User::all()->map(fn (User $user): string => $user->name);
}
```

`pluck('title')` returns values typed `mixed` (`->all()` is `array<mixed>`). When the type matters, `map(fn (Post $post): string => $post->title)` keeps it.

### 6. `find()` vs `findOrFail()`

`Post::find($id)` is `Post|null`; `Post::findOrFail($id)` is `Post` and throws `ModelNotFoundException`, which Laravel renders as a 404. Switching is a behaviour change: right when a missing row is an error, wrong when the caller had a fallback. Check callers and tests.

### 7. `env()` and `config()`

`larastan.noEnvCallsOutsideOfConfig` (on by default): `env()` outside `config/` returns `null` once config is cached. Move the value into a config file and read it with `config()`.

`config('services.api.timeout')` is `mixed` (an error at level 9). The typed getters return a native type and throw `InvalidArgumentException` if the value has a different type:

```php
Config::string('services.api.key');         // string
config()->integer('services.api.timeout');  // int
```

They check with `is_int()` etc., so `integer()` throws for `'30'` from `.env`. Convert in the config file, where the env string enters:

```php
// config/services.php
return [
    'api' => [
        'key' => env('API_KEY'),
        'timeout' => (int) env('API_TIMEOUT', 30),
    ],
];
```

That cast is the boundary conversion, not a cheat. Larastan's `checkConfigTypes: true` infers `config()` types from the config files instead.

### 8. Larastan Rule Identifiers

All Larastan-specific identifiers start with `larastan.`, e.g. `larastan.noEnvCallsOutsideOfConfig`, `larastan.noModelMake`, `larastan.noUnnecessaryCollectionCall`, `larastan.relationExistence`. They point at real misuse; fix the code. Optional ones (`checkModelProperties`, `checkOctaneCompatibility`, `checkConfigTypes`, queue checks) are off unless the config enables them.

### 9. Psalm on Laravel

Psalm needs `psalm/plugin-laravel`: 3.x for Psalm 6, 4.x for Psalm 7.

### Checklist

- [ ] Every relation method has `@return Relation<Related, $this>`
- [ ] Scope `$query` parameters are `Builder<self>`
- [ ] No `@property` that disagrees with a cast or migration
- [ ] Accessors typed `Attribute<TGet, TSet>`
- [ ] No `env()` outside `config/`; `config()` values narrowed with typed getters
- [ ] `find()` → `findOrFail()` only where a missing row is an error
