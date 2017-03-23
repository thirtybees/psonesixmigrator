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

require_once __DIR__.'/classes/autoload.php';

ob_start();
$timerStart = microtime(true);

require_once(__DIR__.'/classes/autoload.php');

require_once(_PS_ROOT_DIR_.'/modules/autoupgrade/classes/Upgrader.php');

if (!class_exists('Upgrader', false)) {
    if (file_exists(_PS_ROOT_DIR_.'/override/classes/Upgrader.php')) {
        require_once(_PS_ROOT_DIR_.'/override/classes/Upgrader.php');
    } else {
        eval('class Upgrader extends UpgraderCore{}');
    }
}
