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
 * Class FileLoggerCore
 *
 * @since 1.0.0
 */
class FileLogger extends AbstractLogger
{
    protected $filename = '';

    /**
     * Log the message
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getFilename()
    {
        if (empty($this->filename)) {
            die('Filename is empty.');
        }

        return $this->filename;
    }

    /**
     * Check if the specified filename is writable and set the filename
     *
     * @param string $filename
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function setFilename($filename)
    {
        if (is_writable(dirname($filename))) {
            $this->filename = $filename;
        } else {
            die('Directory '.dirname($filename).' is not writable');
        }
    }

    /**
     * Write the message in the log file
     *
     * @param string $message
     * @param string $level
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function logMessage($message, $level)
    {
        $formattedMessage = '*'.$this->level_value[$level].'* '."\t".date('Y/m/d - H:i:s').': '.$message."\r\n";

        return (bool) file_put_contents($this->getFilename(), $formattedMessage, FILE_APPEND);
    }
}

