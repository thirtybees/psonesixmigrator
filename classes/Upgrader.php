<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

namespace PsOneSixMigrator;

use PsOneSixMigrator\GuzzleHttp\Client;
use PsOneSixMigrator\GuzzleHttp\Promise;
use PsOneSixMigrator\SemVer\Expression;
use PsOneSixMigrator\SemVer\Version;

/**
 * Class Upgrader
 *
 * @package PsOneSixMigrator
 *
 * @since 1.0.0
 */
class Upgrader
{
    const DEFAULT_CHECK_VERSION_DELAY_HOURS = 12;
    const DEFAULT_CHANNEL = 'stable';
    const CHANNELS_BASE_URI = 'https://api.thirtybees.com/migration/channels/';

    /**
     * Channel with the latest version
     *
     * @var string $channel
     */
    public $channel = '';
    /**
     * The channel as selected by the user
     *
     * @var string $selectedChannel
     */
    public $selectedChannel = 'stable';
    /**
     * Latest version
     *
     * @var string $version
     */
    public $version = '';
    /** @var string Changelog link */
    public $changelogLink;
    /**
     * Link to core package
     *
     * @var string $coreLink
     */
    public $coreLink;
    /**
     * Link to package with upgrade files
     *
     * @var string $extraLink
     */
    public $extraLink;
    public $md5Core;
    public $md5Extra;

    /**
     * Link to file actions link
     *
     * @var string $fileActionsLink
     */
    public $fileActionsLink;
    /**
     * List of files that will be affected
     *
     * @var array $changedFiles
     */
    private $changedFiles = [];
    /**
     * Missing files
     *
     * @var array $missingFiles
     */
    private $missingFiles = [];
    /**
     * Contains the JSON files for this version of PrestaShop
     * Contains four channels:
     *   - alpha
     *   - beta
     *   - rc
     *   - stable
     *
     * @var array $versionInfo
     */
    public $versionInfo = [];

    /** @var Upgrader $instance */
    protected static $instance;

    /**
     * @return Upgrader
     *
     * @since 1.0.0
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Upgrader constructor.
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        $this->selectedChannel = UpgraderTools::getConfig('channel');
        if (!in_array($this->selectedChannel, ['stable', 'rc', 'beta', 'alpha'])) {
            $this->selectedChannel = 'stable';
        }

        $this->checkTbVersion(false);
    }

    /**
     * CheckTBVersion checks for the latest thirty bees version available according to the channel settings
     *
     * @param bool $forceRefresh If set to true, will force to download channel info
     *
     * @return bool Indicates whether the check was successful
     *
     * @since 1.0.0
     */
    public function checkTbVersion($forceRefresh = false)
    {
        if ($forceRefresh || !$this->allChannelsAreCached()) { // || $this->shouldRefresh()) {
            $versionBreakdown = explode('.', _PS_VERSION_);
            if (count($versionBreakdown) < 2) {
                return false;
            }

            $remoteVersion = $versionBreakdown[0].$versionBreakdown[1];

            // For a local api.thirtybees.com test server.
            $source = str_replace('https://api.thirtybees.com/',
                                  'http://localhost/api.thirtybees.com/',
                                  self::CHANNELS_BASE_URI);

            $guzzle = new Client(
                [
                    'base_uri' => $source,
                    'timeout'  => 10,
                    'verify'   => __DIR__.'/../cacert.pem',
                ]
            );

            $promises = [
                'alpha'  => $guzzle->getAsync('alpha.json'),
                'beta'   => $guzzle->getAsync('beta.json'),
                'rc'     => $guzzle->getAsync('rc.json'),
                'stable' => $guzzle->getAsync('stable.json'),
            ];

            $results = Promise\settle($promises)->wait();
            foreach ($results as $key => $result) {
                $success = false;
                if (isset($result['value']) && $result['value'] instanceof GuzzleHttp\Psr7\Response) {
                    $versionInfo = (string) $result['value']->getBody();
                    $versionInfo = json_decode($versionInfo, true);
                    if (isset($versionInfo[$remoteVersion]['latest'])) {
                        $this->versionInfo[$key] = $versionInfo[$remoteVersion]['latest'];
                        $success = true;
                    }
                }

                if (!$success) {
                    $this->versionInfo[$key] = [];
                }
            }
            $this->saveVersionInfo();
        } else {
            $this->versionInfo = [
                'alpha'  => json_decode(file_get_contents(_PS_MODULE_DIR_.'psonesixmigrator/json/thirtybees-alpha.json'), true),
                'beta'   => json_decode(file_get_contents(_PS_MODULE_DIR_.'psonesixmigrator/json/thirtybees-beta.json'), true),
                'rc'     => json_decode(file_get_contents(_PS_MODULE_DIR_.'psonesixmigrator/json/thirtybees-rc.json'), true),
                'stable' => json_decode(file_get_contents(_PS_MODULE_DIR_.'psonesixmigrator/json/thirtybees-stable.json'), true),
            ];
        }

        $channelWithLatestVersion = $this->findChannelWithLatestVersion($this->selectedChannel);
        if (!$channelWithLatestVersion) {
            $this->version = '';
            $this->channel = $this->selectedChannel;
            $this->coreLink = '';
            $this->extraLink = '';
            $this->md5Core = '';
            $this->md5Extra = '';
            $this->fileActionsLink = '';

            return false;
        }
        $versionInfo = $this->versionInfo[$channelWithLatestVersion];
        $this->version = $versionInfo['version'];
        $this->channel = $channelWithLatestVersion;
        $this->coreLink = $versionInfo['core'];
        $this->extraLink = $versionInfo['extra'];
        $this->md5Core = $versionInfo['md5core'];
        $this->md5Extra = $versionInfo['md5extra'];
        $this->fileActionsLink = $versionInfo['fileActions'];

        return true;
    }

    /**
     * downloadLast download the last version of thirty bees and save it in $dest/$filename
     *
     * @param string $dest directory where to save the file
     *
     * @return boolean
     *
     * @TODO ftp if copy is not possible (safe_mode for example)
     */
    public function downloadLast($dest)
    {
        if (!$this->coreLink || !$this->extraLink) {
            $this->checkTbVersion();
            if (!$this->coreLink || !$this->extraLink) {
                return false;
            }
        }

        $coreDestPath = realpath($dest).DIRECTORY_SEPARATOR."thirtybees-v{$this->version}.zip";
        $extraDestPath = realpath($dest).DIRECTORY_SEPARATOR."thirtybees-extra-v{$this->version}.zip";
        $fileActionsPath = realpath($dest).DIRECTORY_SEPARATOR."thirtybees-file-actions-v{$this->version}.json";

        if (!file_exists($coreDestPath)
            || md5_file($coreDestPath) !== $this->md5Core) {
            Tools::copy($this->coreLink, $coreDestPath);
        }
        if (!file_exists($extraDestPath)
            || md5_file($extraDestPath) !== $this->md5Extra) {
            Tools::copy($this->extraLink, $extraDestPath);
        }
        Tools::copy($this->fileActionsLink, $fileActionsPath);

        return is_file($coreDestPath) && is_file($extraDestPath) && is_file($fileActionsPath);
    }

    /**
     * Find the latest version available for download that satisfies the given channel
     *
     * @param string $channel Chosen channel
     *
     * @return false|string Channel with latest version
     *
     * @since 1.0.0
     */
    protected function findChannelWithLatestVersion($channel)
    {
        $latestVersion = '0.0.0';
        $channelWithLatest = false;

        $checkVersions = [];
        foreach (['stable', 'rc', 'beta', 'alpha'] as $type) {
            $checkVersions[] = $type;
            if ($type == $channel) {
                break;
            }
        }

        foreach ($this->versionInfo as $type => $versionInfo) {
            if (in_array($type, $checkVersions) && isset($versionInfo['version'])) {
                $compareVersion = $versionInfo['version'];
                if (Version::gt($compareVersion, $latestVersion)) {
                    $latestVersion = $compareVersion;
                    $channelWithLatest = $type;
                }
            }
        }

        return $channelWithLatest;
    }

    /**
     * Save version info
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function saveVersionInfo()
    {
        $success = true;
        foreach ($this->versionInfo as $type => $version) {
            $success &= (bool) @file_put_contents(_PS_MODULE_DIR_."psonesixmigrator/json/thirtybees-{$type}.json", json_encode($version), JSON_PRETTY_PRINT);
        }

        return $success;
    }

    /**
     * Check if all channel info is locally available
     *
     * @return bool
     *
     * @since 1.0.0
     */
    protected function allChannelsAreCached()
    {
        $cached = true;

        $types = [
            'alpha',
            'beta',
            'rc',
            'stable',
        ];

        foreach ($types as $type) {
            $cached &= @file_exists(_PS_MODULE_DIR_."psonesixmigrator/json/thirtybees-{$type}.json");
        }

        return $cached;
    }
}
