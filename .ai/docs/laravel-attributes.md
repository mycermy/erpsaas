# Laravel PHP Attributes List

A curated list of PHP Attributes available in Laravel Framework (Laravel 13+).

> **📋 Quick Reference**: This file lists all available attributes by category.  
> **📖 Detailed Docs (Offline)**: See [local attributes directory](./attributes/) - **87 markdown files** with namespaces, parameters, and usage examples.  
> **🔄 Update Docs**: Run `php .ai/docs/download-attributes.php` to refresh from GitHub.  
> **Original Source**: https://github.com/MrPunyapal/laravel-attributes-list

## 📊 Eloquent (Models)

- **#[Table]** — Define database table
- **#[Fillable]** — Define mass assignable attributes
- **#[Guarded]** — Define guarded attributes
- **#[Hidden]** — Hide attributes from serialization
- **#[Visible]** — Define visible attributes
- **#[Appends]** — Append accessors to arrays
- **#[Touches]** — Touch related models
- **#[Connection]** — Specify database connection
- **#[Unguarded]** — Disable mass assignment protection
- **#[CollectedBy]** — Custom collection class
- **#[WithoutTimestamps]** — Disable timestamps
- **#[WithoutIncrementing]** — Disable auto-incrementing IDs
- **#[ScopedBy]** — Apply global scope(s) to the model *(Available in Laravel 12)*
- **#[ObservedBy]** — Register model observer(s) *(Available in Laravel 12)*
- **#[DateFormat]** — Define the date format for model timestamps
- **#[Scope]** — Mark a method as a local query scope *(Available in Laravel 12)*
- **#[Boot]** — Mark a trait method as a model boot hook
- **#[Initialize]** — Mark a trait method as a model initialize hook
- **#[UseEloquentBuilder]** — Specify a custom Eloquent builder class
- **#[UseFactory]** — Specify the factory class for the model
- **#[UsePolicy]** — Specify the policy class for the model
- **#[UseResource]** — Specify the API resource for the model
- **#[UseResourceCollection]** — Specify the resource collection for the model

## 📦 Queue (Jobs / Listeners / Notifications / Mailables)

- **#[Connection]** — Define queue connection
- **#[Queue]** — Define queue name
- **#[Delay]** — Delay execution
- **#[Backoff]** — Configure retry delay
- **#[Tries]** — Maximum retry attempts
- **#[Timeout]** — Job timeout duration
- **#[UniqueFor]** — Unique job duration
- **#[DeleteWhenMissingModels]** — Delete if models are missing
- **#[FailOnTimeout]** — Mark job as failed on timeout
- **#[MaxExceptions]** — Maximum exception attempts
- **#[WithoutRelations]** — Ignore relations during serialization
- **#[DebounceFor]** — Debounce job execution for a given duration

## ⚙️ Console (Artisan Commands)

- **#[Signature]** — Define command signature
- **#[Description]** — Define command description
- **#[Aliases]** — Define command aliases
- **#[Usage]** — Define additional command usage examples
- **#[Help]** — Define command help text
- **#[Hidden]** — Hide command from the Artisan list

## 🎛️ Controllers

- **#[Middleware]** — Assign middleware to a controller class or action method
- **#[Authorize]** — Authorize a controller action via the gate

## 🧪 Form Requests

- **#[RedirectTo]** — Define redirect path on validation failure
- **#[RedirectToRoute]** — Define redirect route on validation failure
- **#[StopOnFirstFailure]** — Stop validation on first failure
- **#[ErrorBag]** — Define the error bag name
- **#[FailOnUnknownFields]** — Fail if the request contains unknown fields

## 🌱 Testing

- **#[Seeder]** — Run a specific seeder class during tests
- **#[Seed]** — Run the database seeder during tests
- **#[SetUp]** — Mark a trait method as a test setup hook
- **#[TearDown]** — Mark a trait method as a test teardown hook
- **#[UnitTest]** — Skip framework boot for individual test methods

## 🏭 Factories

- **#[UseModel]** — Define model for factory

## 📡 API Resources

- **#[Collects]** — Define resource collection mapping
- **#[PreserveKeys]** — Preserve keys in resource output

## 🔌 Dependency Injection

- **#[Auth]** — Inject an auth guard instance
- **#[Authenticated]** — Inject the currently authenticated user
- **#[Bind]** — Contextually bind to a specific implementation
- **#[Cache]** — Inject a cache store instance
- **#[Config]** — Inject a configuration value
- **#[Context]** — Inject a value from the application context
- **#[CurrentUser]** — Inject the currently authenticated user model
- **#[DB]** — Inject a database connection instance
- **#[Database]** — Inject a named database connection
- **#[Give]** — Give a specific binding contextually
- **#[Log]** — Inject a logger with a named channel
- **#[RouteParameter]** — Inject a route parameter value
- **#[Scoped]** — Register a class as a scoped singleton in the container
- **#[Singleton]** — Register a class as a singleton in the container
- **#[Storage]** — Inject a storage disk instance
- **#[Tag]** — Inject all bindings tagged with a given tag

## 🤖 AI (Agents)

- **#[MaxSteps]** — Maximum number of steps the agent may take when using tools
- **#[MaxTokens]** — Maximum number of tokens the model may generate
- **#[Model]** — Define the model the agent should use
- **#[Provider]** — Define the AI provider (or providers for failover)
- **#[Temperature]** — Define the sampling temperature for generation
- **#[Timeout]** — Define the HTTP timeout in seconds for agent requests
- **#[UseCheapestModel]** — Use the provider's cheapest text model
- **#[UseSmartestModel]** — Use the provider's most capable text model

## 🐘 PHP Built-in Attributes

- **#[Attribute]** — Mark a class as a reusable custom attribute
- **#[AllowDynamicProperties]** — Allow dynamic properties on a class without deprecation notice
- **#[Deprecated]** — Mark a function, method, class, or constant as deprecated
- **#[NoDiscard]** — Warn when a function's return value is discarded
- **#[Override]** — Assert that a method or property overrides a parent declaration
- **#[ReturnTypeWillChange]** — Silence return type deprecation notice for cross-version compatibility
- **#[SensitiveParameter]** — Redact a parameter value from stack traces

## 📝 Notes

- Attributes provide an alternative to traditional class properties
- They are used across multiple parts of the framework
- Existing approaches (properties, methods) continue to work
- **Laravel 12 Support**: Only some attributes are available (marked above)
- **Laravel 13+**: All attributes listed above become available

---

## Quick Examples

### Model
```php
use Illuminate\Database\Eloquent\Attributes\{Table, Fillable, Hidden, Connection};

#[Table('users')]
#[Fillable('name', 'email')]
#[Hidden('password')]
#[Connection('mysql')]
class User extends Model {}
```

### Job
```php
use Illuminate\Queue\Attributes\{Connection, Queue, Tries, Timeout, Backoff};

#[Connection('redis')]
#[Queue('orders')]
#[Tries(3)]
#[Timeout(60)]
#[Backoff(30)]
class ProcessOrder implements ShouldQueue {}
```

### Command
```php
use Illuminate\Console\Attributes\{Signature, Description};

#[Signature('users:sync {--force}')]
#[Description('Sync users from the external API')]
class SyncUsers extends Command {}
```

### Form Request
```php
use Illuminate\Foundation\Http\Attributes\{RedirectTo, ErrorBag};

#[RedirectTo('/profile')]
#[ErrorBag('updateProfile')]
class UpdateProfileRequest extends FormRequest {}
```

### Container Binding
```php
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class StripeGateway implements PaymentGateway {}
```

### PHP Built-in
```php
// Prevent sensitive values from leaking into stack traces
function login(string $user, #[\SensitiveParameter] string $password): void {}

// Warn when a return value is accidentally discarded (PHP 8.5+)
#[\NoDiscard('check for per-item errors')]
function bulkProcess(array $items): array {}
```
