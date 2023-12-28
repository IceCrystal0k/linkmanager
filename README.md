## About LinkManager Project

LinkManager is a web application used to manage the links of a user.
Technology used:

-   Laravel Version 9.52.5
-   PHP Version 8.0.9
-   Composer Version 2.3.1

Modules that it contains:

-   Dashboard : info about users
-   Account

    -   Profile : profile preview
    -   Settings : profile update / account delete

-   Administration

    -   Users
    -   Permissions
    -   Pages
    -   Page Texts
    -   Categories
    -   Articles
    -   Media Files
    -   Slider

-   Links
    -   Categories : management of the categories
    -   Links : management of the links
    -   Tags : management of the tags

## Requiremens

-   [Download and install composer](https://getcomposer.org/download/)
-   [Download and install node js, a version equal or bigger to v14.15.5](https://nodejs.org/en/download/releases/)

## Installation

-   Create a database with watever name
-   Edit .env file and set the database connection and MAIL connection

Run the following commands in console (in the folder where the app is installed):

-   **`composer install`** (install php modules defined in composer.json)
-   **`npm install`** (install node modules defined in package.json)
-   **`php artisan migrate`** (creates the tables structure)
-   **`npm run dev`** (compile and copy all resource files to public folder)
-   **`php artisan storage:link`** (create a link between folder /storage and /public/storage)
-   **`php artisan optimize`** (clear laravel cache)

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

-   **[Vehikl](https://vehikl.com/)**
-   **[Tighten Co.](https://tighten.co)**
-   **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
-   **[64 Robots](https://64robots.com)**
-   **[Cubet Techno Labs](https://cubettech.com)**
-   **[Cyber-Duck](https://cyber-duck.co.uk)**
-   **[Many](https://www.many.co.uk)**
-   **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
-   **[DevSquad](https://devsquad.com)**
-   **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
-   **[OP.GG](https://op.gg)**
-   **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
-   **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Used libraries & info

-   **[CodeCheef - roles and permission](https://www.codecheef.org/article/user-roles-and-permissions-tutorial-in-laravel-without-packages)**
-   **[Intervation image](https://image.intervention.io/v2)**
-   **[Laravel socialite](https://laravel.com/docs/9.x/socialite)**
-   **[Laravel sanctum](https://laravel.com/docs/9.x/sanctum)**
-   **[Laravel dompdf](https://github.com/barryvdh/laravel-dompdf)**
-   **[Laravel DebugBar](https://github.com/barryvdh/laravel-debugbar)**
-   **[Laravel 9 API Authenticathion using sanctum](https://www.itsolutionstuff.com/post/laravel-9-rest-api-authentication-using-sanctum-tutorialexample.html)**
-   **[Laravel 9 API Authenticathion using passport](https://www.itsolutionstuff.com/post/laravel-9-rest-api-with-passport-authentication-tutorialexample)**
-   **[Laravel customize throttle message](https://thedevsaddam.github.io/post/how-to-customize-laravel-request-throttle-message-in-api-response/)**
