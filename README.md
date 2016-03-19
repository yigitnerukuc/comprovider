# ComProvider

ComProvider is a composer plugin which provides to initialize your installed Laravel packages via Composer automatically.


### Installation
```sh
composer require yigitcukuren/comprovider
```


### Usage

Just add these block in your app/config.php

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


### After You Installed a Laravel Package

Latest version will be your app/config.php like that!

```php
/*
 * Application Service Providers...
 */
App\Providers\AppServiceProvider::class,
..

/*
  * ComProvider
*/
VendorName\Package\PackageServiceProvider::class, // vendorname/package

```

That's it!