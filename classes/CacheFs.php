<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace PsOneSixMigrator;

/**
 * Class CacheFs
 *
 * @since 1.0.0
 *
 * @deprecated 1.1.0 Will be converted into a caching module following the PSR-6 standard: http://www.php-fig.org/psr/psr-6/
 */
class CacheFs extends Cache
{
    /**
     * @var int Number of subfolders to dispatch cached filenames
     */
    protected $depth;

    protected function __construct()
    {
        $this->depth = (int) Db::getInstance()->getValue('SELECT value FROM '._DB_PREFIX_.'configuration WHERE name= \'PS_CACHEFS_DIRECTORY_DEPTH\'', false);

        $keysFilename = $this->getFilename(static::KEYS_NAME);
        if (@filemtime($keysFilename)) {
            $this->keys = unserialize(file_get_contents($keysFilename));
        }
    }

    /**
     * @see Cache::_set()
     */
    protected function _set($key, $value, $ttl = 0)
    {
        return (@file_put_contents($this->getFilename($key), serialize($value)));
    }

    /**
     * @see Cache::_get()
     */
    protected function _get($key)
    {
        if ($this->keys[$key] > 0 && $this->keys[$key] < time()) {
            $this->delete($key);

            return false;
        }

        $filename = $this->getFilename($key);
        if (!@filemtime($filename)) {
            unset($this->keys[$key]);
            $this->_writeKeys();

            return false;
        }
        $file = file_get_contents($filename);

        return unserialize($file);
    }

    /**
     * @see Cache::_exists()
     */
    protected function _exists($key)
    {
        if ($this->keys[$key] > 0 && $this->keys[$key] < time()) {
            $this->delete($key);

            return false;
        }

        return isset($this->keys[$key]) && @filemtime($this->getFilename($key));
    }

    /**
     * @see Cache::_delete()
     */
    protected function _delete($key)
    {
        $filename = $this->getFilename($key);
        if (!@filemtime($filename)) {
            return true;
        }

        return unlink($filename);
    }

    /**
     * @see Cache::_writeKeys()
     */
    protected function _writeKeys()
    {
        @file_put_contents($this->getFilename(static::KEYS_NAME), serialize($this->keys));
    }

    /**
     * @see Cache::flush()
     */
    public function flush()
    {
        $this->delete('*');

        return true;
    }

    /**
     * Delete cache directory
     */
    public static function deleteCacheDirectory()
    {
        Tools::deleteDirectory(_PS_CACHEFS_DIRECTORY_, false);
    }

    /**
     * Create cache directory
     *
     * @param int    $levelDepth
     * @param string $directory
     */
    public static function createCacheDirectories($levelDepth, $directory = false)
    {
        if (!$directory) {
            $directory = _PS_CACHEFS_DIRECTORY_;
        }

        $chars = '0123456789abcdef';
        for ($i = 0, $length = strlen($chars); $i < $length; $i++) {
            $newDir = $directory.$chars[$i].'/';
            if (mkdir($newDir)) {
                if (chmod($newDir, 0777)) {
                    if ($levelDepth - 1 > 0) {
                        CacheFs::createCacheDirectories($levelDepth - 1, $newDir);
                    }
                }
            }
        }
    }

    /**
     * Transform a key into its absolute path
     *
     * @param string $key
     * @return string
     */
    protected function getFilename($key)
    {
        $key = md5($key);
        $path = _PS_CACHEFS_DIRECTORY_;
        for ($i = 0; $i < $this->depth; $i++) {
            $path .= $key[$i].'/';
        }

        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }

        return $path.$key;
    }
}