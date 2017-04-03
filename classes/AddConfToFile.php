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
 * Class AddConfToFile
 *
 * @package PsOneSixMigrator
 */
class AddConfToFile
{
    public $fd;
    public $file;
    public $mode;
    public $error = false;

    /**
     * AddConfToFile constructor.
     *
     * @param string $file
     * @param string $mode
     */
    public function __construct($file, $mode = 'r+')
    {
        $this->file = $file;
        $this->mode = $mode;
        $this->checkFile($file);
        if ($mode == 'w' and !$this->error) {
            if (!$res = @fwrite($this->fd, '<?php'."\n")) {
                $this->error = 6;
            }
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        if (!$this->error) {
            @fclose($this->fd);
        }
    }

    /**
     * @param $file
     */
    protected function checkFile($file)
    {
        if (!$fd = @fopen($this->file, $this->mode)) {
            $this->error = 5;
        } elseif (!is_writable($this->file)) {
            $this->error = 6;
        }
        $this->fd = $fd;
    }

    /**
     * @param string $name
     * @param string $data
     *
     * @return bool
     */
    public function writeInFile($name, $data)
    {
        if (!$res = @fwrite(
            $this->fd,
            'define(\''.$name.'\', \''.$this->checkString($data).'\');'."\n"
        )) {
            $this->error = 6;

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function writeEndTagPhp()
    {
        if (!$res = @fwrite($this->fd, '?>'."\n")) {
            $this->error = 6;

            return false;
        }

        return true;
    }

    /**
     * @param string $string
     *
     * @return mixed|string
     */
    public function checkString($string)
    {
        if (get_magic_quotes_gpc()) {
            $string = stripslashes($string);
        }
        if (!is_numeric($string)) {
            $string = addslashes($string);
            $string = str_replace(array("\n", "\r"), '', $string);
        }

        return $string;
    }
}
