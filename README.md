# Application based on Joomla! Framework v2

This application is the base for the backend API applications.

## Requirements

* PHP 7.2+
    * PDO with MySQL support
* Composer
* Apache with mod_rewrite enabled

## Installation

1. Clone this repo on your web server
2. Run the `composer install` command to install all dependencies
3. Copy `etc/config.dist.json` to `etc/config.json` and configure your environment (see below for full details on the configuration)
4. Run `vendor/bin/phinx migrate` to set up the database

## Database Schema

The database schema is managed through [Phinx](https://phinx.org/).  The `phinx.php` file at the root of this repo configures the Phinx environment.  Please see their documentation for more information.

## Application Configuration

The application's configuration is defined as follows:

* Database - The `joomla/database` package is used to provide a database connection as required
    * `database.host` - The address of the database server
    * `database.user` - The user to connect to the database as
    * `database.password` - The password for the database user
    * `database.database` - The name of the database to use
    * `database.prefix` - The prefix to use for the database's tables
* Logging - The `monolog/monolog` package is used for logging functionality
    * `log.level` - The default logging level to use for all application loggers, this defaults to the `ERROR` level
    * `log.application` - The logging level to use specifically for the `monolog.handler.application` logger; defaults to the `log.level` value
* Error Reporting - The `errorReporting` configuration key can be set to a valid bitmask to be passed into the `error_reporting()` function
* Debug - The `debug` key allows enabling the application's debug mode
* Dev - The `dev` key allows to run application in the developer mode
* Session Expire - `sessionExpire` the time amount in seconds when user session will be ended
* Token Expire - `tokenExpire` the time amount in seconds when the token will be not valid
* Token - `k` the JWT key for admins
* Allowed Domains - `allowDomains` the list of domains which have access to the API
