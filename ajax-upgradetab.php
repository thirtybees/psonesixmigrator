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

use PsOneSixMigrator\Tools;
use PsOneSixMigrator\AjaxProcessor;

if (function_exists('opcache_reset')) {
    opcache_reset();
}

if (function_exists('date_default_timezone_set')) {
    // date_default_timezone_get calls date_default_timezone_set, which can provide warning
    $timezone = @date_default_timezone_get();
    date_default_timezone_set($timezone);
}

require_once __DIR__.'/../../config/settings.inc.php';
require_once __DIR__.'/../../modules/psonesixmigrator/classes/autoload.php';

if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', realpath(__DIR__.'/../../').'/modules/');
}

define('AUTOUPGRADE_MODULE_DIR', _PS_MODULE_DIR_.'psonesixmigrator/');
require_once(AUTOUPGRADE_MODULE_DIR.'functions.php');

// the following test confirm the directory exists
if (!isset($_POST['dir'])) {
    die('no directory');
}

if (!defined('_PS_ROOT_DIR_')) {
    define('_PS_ROOT_DIR_', realpath(__DIR__.'/../../'));
}

require_once __DIR__.'/../../config/defines.inc.php';
require_once(AUTOUPGRADE_MODULE_DIR.'alias.php');

$dir = Tools::safeOutput(Tools::getValue('dir'));

if (realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR.$dir !== realpath(realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR.$dir)) {
    die('wrong directory :'.(isset($_POST['dir']) ? $dir : ''));
}

define('_PS_ADMIN_DIR_', realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR.$dir);

if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'MyISAM');
}

if (!defined('_PS_TOOL_DIR_')) {
    define('_PS_TOOL_DIR_', _PS_ROOT_DIR_.'/tools/');
}

//require(_PS_ADMIN_DIR_.'/functions.php');
include(AUTOUPGRADE_MODULE_DIR.'init.php');

$ajaxUpgrader = AjaxProcessor::getInstance();

if (is_object($ajaxUpgrader) && $ajaxUpgrader->verifyToken()) {
    $ajaxUpgrader->optionDisplayErrors();
    $ajaxUpgrader->ajax = 1;

    // the differences with index.php is here
    $ajaxUpgrader->ajaxPreProcess();
    $action = Tools::getValue('action');

    // no need to use displayConf() here

    if (!empty($action) && method_exists($ajaxUpgrader, 'ajaxProcess'.$action)) {
        $ajaxUpgrader->{'ajaxProcess'.$action}();
    } else {
        die(json_encode([
            'error' => true,
            'status'  => 'Method not found',
        ], JSON_PRETTY_PRINT));
    }

    if (!empty($action) && method_exists($ajaxUpgrader, 'displayAjax'.$action)) {
        $ajaxUpgrader->{'displayAjax'.$action}();
    } else {
        $ajaxUpgrader->displayAjax();
    }
}

die(json_encode([
    'error'  => true,
    'status' => 'Wrong token or request',
], JSON_PRETTY_PRINT));
