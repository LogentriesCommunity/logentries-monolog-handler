logentries-monolog-handler [![Build Status](https://travis-ci.org/logentries/logentries-monolog-handler.png)](https://travis-ci.org/logentries/logentries-monolog-handler)
===========================

*This plug in will no longer be officially supported or maintained by Logentries.<br>*


Current Version: 2.1

## Installation

Add the following to your composer.json and run `composer update`

```json
{
    "require": {
        "logentries/logentries-monolog-handler": "dev-master"
    }
}
```

## Usage

```php
$monolog->pushHandler(new Logentries\Handler\LogentriesHandler('YOUR_TOKEN'));
```

#### Full example
```php
$monolog = new Logger('TestLog');
$monolog->pushHandler(new LogentriesHandler('YOUR_TOKEN'));
$monolog->addWarning('This is a warning logging message');
```

## Links
[logentries.com](https://logentries.com)
