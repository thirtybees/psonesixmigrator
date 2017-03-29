<?php
/**
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!function_exists('PsOneSixMigrator\GuzzleHttp\uri_template')) {
    require __DIR__.'/GuzzleHttp/functions.php';
}
if (!function_exists('PsOneSixMigrator\GuzzleHttp\Psr7\str')) {
    require __DIR__.'/GuzzleHttp/Psr7/functions.php';
}
if (!function_exists('PsOneSixMigrator\GuzzleHttp\Promise\promise_for')) {
    require __DIR__.'/GuzzleHttp/Promise/functions.php';
}


spl_autoload_register(
    function ($class) {
        if (in_array($class, [
            'PsOneSixMigrator\\AbstractLogger',
            'PsOneSixMigrator\\AddConfToFile',
            'PsOneSixMigrator\\AjaxProcessor',
            'PsOneSixMigrator\\Blowfish',
            'PsOneSixMigrator\\ConfigurationTest',
            'PsOneSixMigrator\\CryptBlowfish',
            'PsOneSixMigrator\\Db',
            'PsOneSixMigrator\\DbPDO',
            'PsOneSixMigrator\\DbQuery',
            'PsOneSixMigrator\\FileLogger',
            'PsOneSixMigrator\\Tools',
            'PsOneSixMigrator\\Upgrader',
            'PsOneSixMigrator\\UpgraderTools',
            'PsOneSixMigrator\\GuzzleHttp\\Client',
            'PsOneSixMigrator\\GuzzleHttp\\ClientInterface',
            'PsOneSixMigrator\\GuzzleHttp\\Cookie\\CookieJar',
            'PsOneSixMigrator\\GuzzleHttp\\Cookie\\CookieJarInterface',
            'PsOneSixMigrator\\GuzzleHttp\\Cookie\\FileCookieJar',
            'PsOneSixMigrator\\GuzzleHttp\\Cookie\\SessionCookieJar',
            'PsOneSixMigrator\\GuzzleHttp\\Cookie\\SetCookie',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\BadResponseException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\ClientException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\ConnectException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\GuzzleException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\RequestException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\SeekException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\ServerException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\TooManyRedirectsException',
            'PsOneSixMigrator\\GuzzleHttp\\Exception\\TransferException',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\CurlFactory',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\CurlFactoryInterface',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\CurlHandler',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\CurlMultiHandler',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\EasyHandle',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\MockHandler',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\Proxy',
            'PsOneSixMigrator\\GuzzleHttp\\Handler\\StreamHandler',
            'PsOneSixMigrator\\GuzzleHttp\\HandlerStack',
            'PsOneSixMigrator\\GuzzleHttp\\MessageFormatter',
            'PsOneSixMigrator\\GuzzleHttp\\Middleware',
            'PsOneSixMigrator\\GuzzleHttp\\Pool',
            'PsOneSixMigrator\\GuzzleHttp\\PrepareBodyMiddleware',
            'PsOneSixMigrator\\GuzzleHttp\\RedirectMiddleware',
            'PsOneSixMigrator\\GuzzleHttp\\RequestOptions',
            'PsOneSixMigrator\\GuzzleHttp\\RetryMiddleware',
            'PsOneSixMigrator\\GuzzleHttp\\TransferStats',
            'PsOneSixMigrator\\GuzzleHttp\\UriTemplate',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\AggregateException',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\CancellationException',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\Coroutine',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\EachPromise',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\FulfilledPromise',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\Promise',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\PromiseInterface',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\PromisorInterface',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\RejectedPromise',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\RejectionException',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\TaskQueue',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\TaskQueueInterface',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\functions',
            'PsOneSixMigrator\\GuzzleHttp\\Promise\\functions_include',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\AppendStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\BufferStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\CachingStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\DroppingStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\FnStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\InflateStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\LazyOpenStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\LimitStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\MessageTrait',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\MultipartStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\NoSeekStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\PumpStream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\Request',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\Response',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\ServerRequest',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\Stream',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\StreamDecoratorTrait',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\StreamWrapper',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\UploadedFile',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\Uri',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\UriNormalizer',
            'PsOneSixMigrator\\GuzzleHttp\\Psr7\\UriResolver',
            'PsOneSixMigrator\\Psr\\Http\\Message\\MessageInterface',
            'PsOneSixMigrator\\Psr\\Http\\Message\\RequestInterface',
            'PsOneSixMigrator\\Psr\\Http\\Message\\ResponseInterface',
            'PsOneSixMigrator\\Psr\\Http\\Message\\ServerRequestInterface',
            'PsOneSixMigrator\\Psr\\Http\\Message\\StreamInterface',
            'PsOneSixMigrator\\Psr\\Http\\Message\\UploadedFileInterface',
            'PsOneSixMigrator\\Psr\\Http\\Message\\UriInterface',
            'PsOneSixMigrator\\SemVer\\Expression',
            'PsOneSixMigrator\\SemVer\\SemVerException',
            'PsOneSixMigrator\\SemVer\\Version',
        ])) {
            // project-specific namespace prefix
            $prefix = 'PsOneSixMigrator\\';

            // base directory for the namespace prefix
            $baseDir = __DIR__.'/';

            // does the class use the namespace prefix?
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // no, move to the next registered autoloader
                return;
            }

            // get the relative class name
            $relativeClass = substr($class, $len);

            // replace the namespace prefix with the base directory, replace namespace
            // separators with directory separators in the relative class name, append
            // with .php
            $file = $baseDir.str_replace('\\', '/', $relativeClass).'.php';

            // if the file exists, require it
            require $file;
        }
    }
);
