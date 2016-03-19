# ComProvider

ComProvider is a composer plugin which provides to initialize your installed Laravel packages via Composer automatically.


### Installation
```sh
composer require yigitcukuren/comprovider
```


### Usage

Just add these block on your app/config.php

```php
/*
 * Application Service Providers...
 */
App\Providers\AppServiceProvider::class,
..

/*
  * ComProvider
*/
```		 

ComProvider adds Service Provider of packages automatically.
