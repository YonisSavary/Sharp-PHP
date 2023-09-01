# Sharp Framework

The goals behind Sharp are :
1. Removing unecessary abstraction layers (Don't hide the code behind facades)
2. Make a clean code that is intuitive for the most
3. Have a framework that doesn't break your IDE, PHP type hint should be enough in the vast majority of situations
4. Let you build your app as fast as possible and don't have to worry about setup
5. Have as few dependencies as possible (So far, `composer.json` only install [PHPUnit](https://phpunit.de/))
6. Automate tedious task (like model creation)

(You can browse this documentation with an IDE like Visual Studio Code with the markdown preview)

## Documentation

This documentation directory holds some hand-written docs, but every classes
got pieces of documentation inside their respective files

Core
- 📁 [App directory & Configuration](./core/config.md)
- 🧩 [Understanding Sharp components](./core/components.md)
- 💻 [CLI command](./core/commands.md)
- 📃 [Logging](./core/logging.md)
- 🔏 [Session](./core/session.md)

<!--
Core (Advanced):
- [(Code) `Events`](../Classes/Core/Events.php)
- [(Code) `Storage`](../Classes/Env/Storage.php)
- [(Code) `Cache`](../Classes/Env/Cache.php)
-->

Routing & Logic
- 🛣️ [Routing](./logic/routing.md)
- ⚙️  [Controllers](./logic/controllers.md)
- 🚦 [Middlewares](./logic/middlewares.md)
<!-- [(Code) `Request`](../Classes/Http/Request.php) -->
<!-- [(Code) `Response`](../Classes/Http/Response.php) -->

Database
- 📚 [Database and Models](./data/database.md)

Extras
- ⭐️ [Serve assets with `AssetServer`](./extras/assets.md)
- 🚘 [Automatic CRUD API with `Autobahn`](./extras/autobahn.md) (TODO)
- 🔐 [Authentication](./security/auth.md)

File/Comment documentation:


## Making custom scripts that uses Sharp

If you want to use Sharp in any of your PHP script, you can just
require [`Sharp/bootstrap.php`](../bootstrap.php), it will initialize
the framework without doing anything