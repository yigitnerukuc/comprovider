# ComProvider

ComProvider is a composer plugin which provides to initialize automatically installed Laravel packages via Composer


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
