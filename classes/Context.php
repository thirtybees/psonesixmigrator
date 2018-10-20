<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2018 thirty bees
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
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2018 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

namespace PsOneSixMigrator;

/**
 * Class Context
 *
 * @since 1.0.0
 */
class Context
{
    // @codingStandardsIgnoreStart
    /** @var int */
    const DEVICE_COMPUTER = 1;
    /** @var int */
    const DEVICE_TABLET = 2;
    /** @var int */
    const DEVICE_MOBILE = 4;
    /** @var int */
    const MODE_STD = 1;
    /** @var int */
    const MODE_STD_CONTRIB = 2;
    /** @var int */
    const MODE_HOST_CONTRIB = 4;
    /** @var int */
    const MODE_HOST = 8;
    /* @var Context */
    protected static $instance;
    /** @var Cart */
    public $cart;
    /** @var Customer */
    public $customer;
    /** @var Cookie */
    public $cookie;
    /** @var Link */
    public $link;
    /** @var Country */
    public $country;
    /** @var Employee */
    public $employee;
    /** @var AdminController|FrontController */
    public $controller;
    /** @var string */
    public $override_controller_name_for_translations;
    /** @var Language */
    public $language;
    /** @var Currency */
    public $currency;
    /** @var AdminTab */
    public $tab;
    /** @var Shop */
    public $shop;
    /** @var Theme */
    public $theme;
    /** @var Smarty */
    public $smarty;
    /** @var Mobile_Detect */
    public $mobile_detect;
    /** @var int */
    public $mode;
    /**
     * Mobile device of the customer
     *
     * @var bool|null
     */
    protected $mobile_device = null;
    /** @var bool|null */
    protected $is_mobile = null;
    /** @var bool|null */
    protected $is_tablet = null;
    // @codingStandardsIgnoreEnd

    /**
     * @param Context $testInstance Unit testing purpose only
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function setInstanceForTesting($testInstance)
    {
        static::$instance = $testInstance;
    }

    /**
     * Unit testing purpose only
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function deleteTestingInstance()
    {
        static::$instance = null;
    }

    /**
     * Get a singleton instance of Context object
     *
     * @return Context
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public static function getContext()
    {
        if (!isset(static::$instance)) {
            static::$instance = new Context();
        }

        return static::$instance;
    }

    /**
     * Checks if visitor's device is a mobile device
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function isMobile()
    {
        if ($this->is_mobile === null) {
            $this->is_mobile = false;
        }

        return $this->is_mobile;
    }

    /**
     * Checks if visitor's device is a tablet device
     *
     * @return bool
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function isTablet()
    {
        if ($this->is_tablet === null) {
            $this->is_tablet = false;
        }

        return $this->is_tablet;
    }

    /**
     * Returns mobile device type
     *
     * @return int
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function getDevice()
    {
        static $device = null;

        if ($device === null) {
            if ($this->isTablet()) {
                $device = Context::DEVICE_TABLET;
            } elseif ($this->isMobile()) {
                $device = Context::DEVICE_MOBILE;
            } else {
                $device = Context::DEVICE_COMPUTER;
            }
        }

        return $device;
    }

    /**
     * Clone current context object
     *
     * @return Context
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function cloneContext()
    {
        return clone($this);
    }
}
