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
 * Class AbstractLoggerCore
 *
 * @since 1.0.0
 */
abstract class AbstractLogger
{
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    public $level;
    protected $level_value = [
        0 => 'DEBUG',
        1 => 'INFO',
        2 => 'WARNING',
        3 => 'ERROR',
    ];

    /**
     * AbstractLoggerCore constructor.
     *
     * @param int $level
     *
     * @since 1.0.0
     */
    public function __construct($level = self::INFO)
    {
        if (array_key_exists((int) $level, $this->level_value)) {
            $this->level = $level;
        } else {
            $this->level = self::INFO;
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message
     */
    public function logDebug($message)
    {
        $this->log($message, self::DEBUG);
    }

    /**
     * Check the level and log the message if needed
     *
     * @param string $message
     * @param int    $level
     */
    public function log($message, $level = self::DEBUG)
    {
        if ($level >= $this->level) {
            $this->logMessage($message, $level);
        }
    }

    /**
     * Log an info message
     *
     * @param string $message
     */
    public function logInfo($message)
    {
        $this->log($message, self::INFO);
    }

    /**
     * Log a warning message
     *
     * @param string $message
     */
    public function logWarning($message)
    {
        $this->log($message, self::WARNING);
    }

    /**
     * Log an error message
     *
     * @param string $message
     */
    public function logError($message)
    {
        $this->log($message, self::ERROR);
    }

    /**
     * Log the message
     *
     * @param string $message
     * @param level
     */
    abstract protected function logMessage($message, $level);
}
