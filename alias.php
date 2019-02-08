<?php
/**
 * Copyright (C) 2017-2019 thirty bees
 * Copyright (C) 2007-2016 PrestaShop SA
 *
 * thirty bees is an extension to the PrestaShop software by PrestaShop SA.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2019 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark of PrestaShop SA.
 */

require_once __DIR__.'/classes/autoload.php';

use PsOneSixMigrator\Tools;
use PsOneSixMigrator\Db;

function fd($var)
{
    return (Tools::fd($var));
}

function p($var)
{
    return (Tools::p($var));
}

function d($var)
{
    Tools::d($var);
}

function ppp($var)
{
    return (Tools::p($var));
}

function ddd($var)
{
    Tools::d($var);
}

/**
 * Sanitize data which will be injected into SQL query
 *
 * @param string  $string SQL data which will be injected into SQL query
 * @param boolean $htmlOK Does data contain HTML code ? (optional)
 *
 * @return string Sanitized data
 */
function pSQL($string, $htmlOK = false)
{
    return Db::getInstance()->escape($string, $htmlOK);
}

function bqSQL($string)
{
    return str_replace('`', '\`', pSQL($string));
}

/**
 * @deprecated
 */
function nl2br2($string)
{
    return Tools::nl2br($string);
}
