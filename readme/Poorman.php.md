# Poorman

The intention of this package is to combine all database connections into 1 virtual database by using `union all`. This package mostly useful when using `hyn/multi-tenant` packages or multiple databases with the same structure.

### Example

```php
use App\Post;
use Daison\LaravelExperimental\Poorman;

$records = (new Poorman)
    ->connections('mysql1', 'mysql2') // or pass an array instead
    ->query(Post::class, function ($query) use ($user) {
        $query
            ->select('*')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
    })
    ->paginate(10);
```

When using `hyn/multi-tenant` we could fetch all website's connection.

```php
$connections = (new Hyn\Tenancy\Models\Website)
    ->newQuery()
    ->get()
    ->pluck('uuid')
    ->toArray()
;

$records = (new Poorman)
    ->connections($connections)
    // ...
;
```

#### How It Works

The class `Poorman` will collect all connections and will make a copy of the query under the closure `function ($query) { ... }`, after that it will convert those queries into a raw query and that's the time we will replace each of the `$connections`, those copies will be wrapped using `union all`.

Similar implementations when using MySQL Views, but we handle it thru php.

#### Notes

1. We could still use `paginate`, `limit`, `count`, `sum`, and we have this `toRawSql()` where it combines `toSql` and the bindings.

2. Remember that using `union all` will significantly slows down when calling `where` or any filters, it is better to filter out inside the closure if possible.

```php
$records = (new Poorman)
    ->connections($connections)
    ->query(Post::class, function ($query) use ($user) {
        // ... better to query here!
    })

    // don't call any filters here, unless you've tested enough
    // your data.
    ->where('user_id', $user->id)

    // it is reasonable to use order by if your concern was
    // to get the lists of records by recent post, across
    // all database connections.
    ->orderBy('created_at', 'desc');
```

3. We will add the database name under each record, the key should be `database_connection`

```php
[
    'id' => ...,
    'title' => ...,
    'body' => ...,
    'database_connection' => 'mysql1',
];
```
