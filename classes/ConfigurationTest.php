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
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace PsOneSixMigrator;

/**
 * Class ConfigurationTestCore
 *
 * @since 1.0.0
 */
class ConfigurationTest
{
    /**
     * @param array $tests
     *
     * @return array
     *
     * @since 1.0.0
     */
    public static function check($tests)
    {
        $res = [];
        foreach ($tests as $key => $test) {
            $res[$key] = self::run($key, $test);
        }

        return $res;
    }

    /**
     * @param     $ptr
     * @param int $arg
     *
     * @return string
     *
     * @since 1.0.0
     */
    public static function run($ptr, $arg = 0)
    {
        if (call_user_func(['ConfigurationTest', 'test_'.$ptr], $arg)) {
            return 'ok';
        }

        return 'fail';
    }

    /**
     * @return mixed
     *
     * @since 1.0.0
     */
    public static function test_phpversion()
    {
        return version_compare(substr(phpversion(), 0, 3), '5.4', '>=');
    }

    public static function test_mysql_support()
    {
        return function_exists('mysql_connect');
    }

    public static function test_magicquotes()
    {
        return !get_magic_quotes_gpc();
    }

    public static function test_upload()
    {
        return ini_get('file_uploads');
    }

    public static function test_fopen()
    {
        return ini_get('allow_url_fopen');
    }

    public static function test_curl()
    {
        return function_exists('curl_init');
    }

    public static function test_system($funcs)
    {
        foreach ($funcs as $func) {
            if (!function_exists($func)) {
                return false;
            }
        }

        return true;
    }

    public static function test_gd()
    {
        return function_exists('imagecreatetruecolor');
    }

    public static function test_register_globals()
    {
        return !ini_get('register_globals');
    }

    static function test_gz()
    {
        if (function_exists('gzencode')) {
            return !(@gzencode('dd') === false);
        }

        return false;
    }

    static function test_config_dir($dir)
    {
        return self::test_dir($dir);
    }

    public static function test_dir($relativeDir, $recursive = false, &$fullReport = null)
    {
        $dir = rtrim(_PS_ROOT_DIR_, '\\/').DIRECTORY_SEPARATOR.trim($relativeDir, '\\/');
        if (!file_exists($dir) || !$dh = opendir($dir)) {
            $fullReport = sprintf('Directory %s does not exist or is not writable', $dir); // sprintf for future translation

            return false;
        }
        $dummy = rtrim($dir, '\\/').DIRECTORY_SEPARATOR.uniqid();
        if (@file_put_contents($dummy, 'test')) {
            @unlink($dummy);
            if (!$recursive) {
                closedir($dh);

                return true;
            }
        } elseif (!is_writable($dir)) {
            $fullReport = sprintf('Directory %s is not writable', $dir); // sprintf for future translation

            return false;
        }

        if ($recursive) {
            while (($file = readdir($dh)) !== false) {
                if (is_dir($dir.DIRECTORY_SEPARATOR.$file) && $file != '.' && $file != '..' && $file != '.svn') {
                    if (!self::test_dir($relativeDir.DIRECTORY_SEPARATOR.$file, $recursive, $fullReport)) {
                        return false;
                    }
                }
            }
        }

        closedir($dh);

        return true;
    }

    /**
     * @param string $dir
     *
     * @return bool
     *
     * @since 1.0.0
     */
    static function test_sitemap($dir)
    {
        return self::test_file($dir);
    }

    /**
     * @param string $file
     *
     * @return bool
     *
     * @since 1.0.0
     */
    static function test_file($file)
    {
        return file_exists($file) && is_writable($file);
    }

    static function test_root_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_log_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_admin_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_img_dir($dir)
    {
        return self::test_dir($dir, true);
    }

    static function test_module_dir($dir)
    {
        return self::test_dir($dir, true);
    }

    static function test_tools_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_cache_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_tools_v2_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_cache_v2_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_download_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_mails_dir($dir)
    {
        return self::test_dir($dir, true);
    }

    static function test_translations_dir($dir)
    {
        return self::test_dir($dir, true);
    }

    static function test_theme_lang_dir($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        return self::test_dir($dir, true);
    }

    static function test_theme_cache_dir($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        return self::test_dir($dir, true);
    }

    static function test_customizable_products_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_virtual_products_dir($dir)
    {
        return self::test_dir($dir);
    }

    static function test_mcrypt()
    {
        return function_exists('mcrypt_encrypt');
    }

    static function test_dom()
    {
        return extension_loaded('Dom');
    }

    static function test_mobile()
    {
        return true;
    }
}
