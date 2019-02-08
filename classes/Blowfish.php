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

namespace PsOneSixMigrator;

// TODO: remove global defines
define('PS_UNPACK_NATIVE', 1);
define('PS_UNPACK_MODIFIED', 2);

/**
 * Class Blowfish
 *
 * @since 1.0.0
 */
class Blowfish extends CryptBlowfish
{
    /**
     * @param $plaintext
     *
     * @return bool|string
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function encrypt($plaintext)
    {
        if (($length = strlen($plaintext)) >= 1048576) {
            return false;
        }

        $ciphertext = '';
        $paddedtext = $this->maxi_pad($plaintext);
        $strlen = strlen($paddedtext);
        for ($x = 0; $x < $strlen; $x += 8) {
            $piece = substr($paddedtext, $x, 8);
            $cipherPiece = parent::encrypt($piece);
            $encoded = base64_encode($cipherPiece);
            $ciphertext = $ciphertext.$encoded;
        }

        return $ciphertext.sprintf('%06d', $length);
    }

    /**
     * @param string $plaintext
     *
     * @return string
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function maxi_pad($plaintext)
    {
        $strLen = strlen($plaintext);
        $padLen = $strLen % 8;
        for ($x = 0; $x < $padLen; $x++) {
            $plaintext = $plaintext.' ';
        }

        return $plaintext;
    }

    /**
     * @param $ciphertext
     *
     * @return string
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function decrypt($ciphertext)
    {
        $plainTextLength = intval(substr($ciphertext, -6));
        $ciphertext = substr($ciphertext, 0, -6);

        $plaintext = '';
        $chunks = explode('=', $ciphertext);
        $endingValue = count($chunks);
        for ($counter = 0; $counter < ($endingValue - 1); $counter++) {
            $chunk = $chunks[$counter].'=';
            $decoded = base64_decode($chunk);
            $piece = parent::decrypt($decoded);
            $plaintext = $plaintext.$piece;
        }

        return substr($plaintext, 0, $plainTextLength);
    }
}
