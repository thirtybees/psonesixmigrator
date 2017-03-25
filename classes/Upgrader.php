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

    public static $defaultChannel = 'minor';
    public $addons_api = 'api.addons.prestashop.com';
    public $rssChannelLink = 'https://api.prestashop.com/xml/channel17.xml';
    public $rssMd5FileLinkDir = 'https://api.prestashop.com/xml/md5-17/';
    public $versionName;
    public $versionNum;
    public $versionIsModified = null;
    /**
     * @var string contains hte url where to download the file
     */
    public $link;
    public $autoupgrade;
    public $autoupgradeModule;
    public $autoupgradeLastVersion;
    public $autoupgradeModuleLink;
    public $changelog;
    public $available;
    public $md5;
    public $channel = '';
    public $branch = '';
    /**
     * @var boolean contains true if last version is not installed
     */
    private $needUpgrade = false;
    private $changedFiles = [];
    private $missingFiles = [];

    public function __construct($autoload = false)
    {
        if ($autoload) {
            $matches = [];
            preg_match('#([0-9]+\.[0-9]+)\.[0-9]+\.[0-9]+#', _PS_VERSION_, $matches);
            $this->branch = $matches[1];
            if (class_exists('Configuration', false)) {
                $this->channel = \Configuration::get('PS_UPGRADE_CHANNEL');
            }
            if (empty($this->channel)) {
                $this->channel = static::$defaultChannel;
            }
            // checkPSVersion to get need_upgrade
            $this->checkPSVersion();
        }
        if (!extension_loaded('openssl')) {
            $this->rssChannelLink = str_replace('https://', 'http://', $this->rssChannelLink);
            $this->rssMd5FileLinkDir = str_replace('https://', 'http://', $this->rssMd5FileLinkDir);
        }
    }

    /**
     * checkPSVersion ask to prestashop.com if there is a new version. return an array if yes, false otherwise
     *
     * @param boolean $refresh        if set to true, will force to download channel.xml
     * @param array   $array_no_major array of channels which will return only the immediate next version number.
     *
     * @return mixed
     */
    public function checkPSVersion($refresh = false, $array_no_major = ['minor'])
    {
        // if we use the autoupgrade process, we will never refresh it
        // except if no check has been done before
        $feed = $this->getXmlChannel($refresh);

        // channel hierarchy :
        // if you follow private, you follow stable release
        // if you follow rc, you also follow stable
        // if you follow beta, you also follow rc
        // et caetera
        $followedChannels = [];
        $followedChannels[] = $this->channel;
        switch ($this->channel) {
            case 'alpha':
                $followedChannels[] = 'beta';
            case 'beta':
                $followedChannels[] = 'rc';
            case 'rc':
                $followedChannels[] = 'stable';
            case 'minor':
            case 'major':
            case 'private':
                $followedChannels[] = 'stable';
        }

        if ($feed) {
            $this->autoupgradeModule = (int) $feed->autoupgrade_module;
            $this->autoupgradeLastVersion = (string) $feed->autoupgrade->last_version;
            $this->autoupgradeModuleLink = (string) $feed->autoupgrade->download->link;

            foreach ($feed->channel as $channel) {
                $channelAvailable = (string) $channel['available'];

                $channelName = (string) $channel['name'];
                // stable means major and minor
                // boolean algebra
                // skip if one of theses props are true:
                // - "stable" in xml, "minor" or "major" in configuration
                // - channel in xml is not channel in configuration
                if (!(in_array($channelName, $followedChannels))) {
                    continue;
                }
                // now we are on the correct channel (minor, major, ...)
                foreach ($channel as $branch) {
                    // branch name = which version
                    $branchName = (string) $branch['name'];
                    // if channel is "minor" in configuration, do not allow something else than current branch
                    // otherwise, allow superior or equal
                    if (
                    (in_array($this->channel, $followedChannels)
                        && version_compare($branchName, $this->branch, '>='))
                    ) {
                        // skip if $branch->num is inferior to a previous one, skip it
                        if (version_compare((string) $branch->num, $this->versionNum, '<')) {
                            continue;
                        }
                        // also skip if previous loop found an available upgrade and current is not
                        if ($this->available && !($channelAvailable && (string) $branch['available'])) {
                            continue;
                        }
                        // also skip if chosen channel is minor, and xml branch name is superior to current
                        if (in_array($this->channel, $array_no_major) && version_compare($branchName, $this->branch, '>')) {
                            continue;
                        }
                        $this->versionName = (string) $branch->name;
                        $this->versionNum = (string) $branch->num;
                        $this->link = (string) $branch->download->link;
                        $this->md5 = (string) $branch->download->md5;
                        $this->changelog = (string) $branch->changelog;
                        if (extension_loaded('openssl')) {
                            $this->link = str_replace('http://', 'https://', $this->link);
                            $this->changelog = str_replace('http://', 'https://', $this->changelog);
                        }
                        $this->available = $channelAvailable && (string) $branch['available'];
                    }
                }
            }
        } else {
            return false;
        }
        // retro-compatibility :
        // return array(name,link) if you don't use the last version
        // false otherwise
        if (version_compare(_PS_VERSION_, $this->versionNum, '<')) {
            $this->needUpgrade = true;

            return ['name' => $this->versionName, 'link' => $this->link];
        } else {
            return false;
        }
    }

    public function getXmlChannel($refresh = false)
    {
        $xml = $this->getXmlFile(_PS_ROOT_DIR_.'/config/xml/channel.xml', $this->rssChannelLink, $refresh);
        if ($refresh) {
            if (class_exists('Configuration', false)) {
                \Configuration::updateValue('PS_LAST_VERSION_CHECK', time());
            }
        }

        return $xml;
    }

    public function getXmlFile($xmlLocalfile, $xml_remotefile, $refresh = false)
    {
        // @TODO : this has to be moved in autoupgrade.php > install method
        if (!is_dir(_PS_ROOT_DIR_.'/config/xml')) {
            if (is_file(_PS_ROOT_DIR_.'/config/xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml');
            }
            mkdir(_PS_ROOT_DIR_.'/config/xml', 0777);
        }
        if ($refresh || !file_exists($xmlLocalfile) || @filemtime($xmlLocalfile) < (time() - (3600 * Upgrader::DEFAULT_CHECK_VERSION_DELAY_HOURS))) {
            $xmlString = Tools::file_get_contents($xml_remotefile, false, stream_context_create(['http' => ['timeout' => 10]]));
            $xml = @simplexml_load_string($xmlString);
            if ($xml !== false) {
                file_put_contents($xmlLocalfile, $xmlString);
            }
        } else {
            $xml = @simplexml_load_file($xmlLocalfile);
        }

        return $xml;
    }

    public function __get($var)
    {
        if ($var == 'need_upgrade') {
            return $this->isLastVersion();
        }
    }

    public function isLastVersion()
    {
        if (empty($this->link)) {
            $this->checkPSVersion();
        }

        return $this->needUpgrade;

    }

    /**
     * downloadLast download the last version of PrestaShop and save it in $dest/$filename
     *
     * @param string $dest     directory where to save the file
     * @param string $filename new filename
     *
     * @return boolean
     *
     * @TODO ftp if copy is not possible (safe_mode for example)
     */
    public function downloadLast($dest, $filename = 'prestashop.zip')
    {
        if (empty($this->link)) {
            $this->checkPSVersion();
        }

        $destPath = realpath($dest).DIRECTORY_SEPARATOR.$filename;

        Tools::copy($this->link, $destPath);

        return is_file($destPath);
    }

    /**
     * delete the file /config/xml/$version.xml if exists
     *
     * @param string $version
     *
     * @return boolean true if succeed
     */
    public function clearXmlMd5File($version)
    {
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/'.$version.'.xml')) {
            return unlink(_PS_ROOT_DIR_.'/config/xml/'.$version.'.xml');
        }

        return true;
    }

    /**
     * use the addons api to get xml files
     *
     * @param mixed $xmlLocalfile
     * @param mixed $postData
     * @param mixed $refresh
     *
     * @access public
     * @return void
     */
    public function getApiAddons($xmlLocalfile, $postData, $refresh = false)
    {
        if (!is_dir(_PS_ROOT_DIR_.'/config/xml')) {
            if (is_file(_PS_ROOT_DIR_.'/config/xml')) {
                unlink(_PS_ROOT_DIR_.'/config/xml');
            }
            mkdir(_PS_ROOT_DIR_.'/config/xml', 0777);
        }
        if ($refresh || !file_exists($xmlLocalfile) || @filemtime($xmlLocalfile) < (time() - (3600 * Upgrader::DEFAULT_CHECK_VERSION_DELAY_HOURS))) {
            $protocolsList = ['https://' => 443, 'http://' => 80];
            if (!extension_loaded('openssl')) {
                unset($protocolsList['https://']);
            }
            // Make the request
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'content' => $postData,
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'timeout' => 10,
                ],
            ];
            $context = stream_context_create($opts);
            $xml = false;
            foreach ($protocolsList as $protocol => $port) {
                $xmlString = Tools::file_get_contents($protocol.$this->addons_api, false, $context);
                if ($xmlString) {
                    $xml = @simplexml_load_string($xmlString);
                    break;
                }
            }
            if ($xml !== false) {
                file_put_contents($xmlLocalfile, $xmlString);
            }
        } else {
            $xml = @simplexml_load_file($xmlLocalfile);
        }

        return $xml;
    }

    /**
     * getDiffFilesList
     *
     * @param string  $version1
     * @param string  $version2
     * @param boolean $show_modif
     *
     * @return array array('modified'=>array(...), 'deleted'=>array(...))
     */
    public function getDiffFilesList($version1, $version2, $show_modif = true, $refresh = false)
    {
        $checksum1 = $this->getXmlMd5File($version1, $refresh);
        $checksum2 = $this->getXmlMd5File($version2, $refresh);
        if ($checksum1) {
            $v1 = $this->md5FileAsArray($checksum1->ps_root_dir[0]);
        }
        if ($checksum2) {
            $v2 = $this->md5FileAsArray($checksum2->ps_root_dir[0]);
        }
        if (empty($v1) || empty($v2)) {
            return false;
        }
        $filesList = $this->compareReleases($v1, $v2, $show_modif);
        if (!$show_modif) {
            return $filesList['deleted'];
        }

        return $filesList;

    }

    /**
     * return xml containing the list of all default PrestaShop files for version $version,
     * and their respective md5sum
     *
     * @param string $version
     *
     * @return SimpleXMLElement or false if error
     */
    public function getXmlMd5File($version, $refresh = false)
    {
        return $this->getXmlFIle(_PS_ROOT_DIR_.'/config/xml/'.$version.'.xml', $this->rssMd5FileLinkDir.$version.'.xml', $refresh);
    }

    public function md5FileAsArray($node, $dir = '/')
    {
        $array = [];
        foreach ($node as $key => $child) {
            if (is_object($child) && $child->getName() == 'dir') {
                $dir = (string) $child['name'];
                // $current_path = $dir.(string)$child['name'];
                // @todo : something else than array pop ?
                $dir_content = $this->md5FileAsArray($child, $dir);
                $array[$dir] = $dir_content;
            } else {
                if (is_object($child) && $child->getName() == 'md5file') {
                    $array[(string) $child['name']] = (string) $child;
                }
            }
        }

        return $array;
    }

    /**
     * returns an array of files which
     *
     * @param array   $v1         result of method $this->md5FileAsArray()
     * @param array   $v2         result of method $this->md5FileAsArray()
     * @param boolean $showModif  if set to false, the method will only
     *                            list deleted files
     * @param string  $path
     *                            deleted files in version $v2. Otherwise, only deleted.
     *
     * @return array('modified' => array(files..), 'deleted' => array(files..)
     */
    public function compareReleases($v1, $v2, $showModif = true, $path = '/')
    {
        // in that array the list of files present in v1 deleted in v2
        static $deletedFiles = [];
        // in that array the list of files present in v1 modified in v2
        static $modifiedFiles = [];

        foreach ($v1 as $file => $md5) {
            if (is_array($md5)) {
                $subpath = $path.$file;
                if (isset($v2[$file]) && is_array($v2[$file])) {
                    $this->compareReleases($md5, $v2[$file], $showModif, $path.$file.'/');
                } else // also remove old dir
                {
                    $deletedFiles[] = $subpath;
                }
            } else {
                if (in_array($file, array_keys($v2))) {
                    if ($showModif && ($v1[$file] != $v2[$file])) {
                        $modifiedFiles[] = $path.$file;
                    }
                    $exists = true;
                } else {
                    $deletedFiles[] = $path.$file;
                }
            }
        }

        return ['deleted' => $deletedFiles, 'modified' => $modifiedFiles];
    }

    public function isAuthenticPrestashopVersion($version = null, $refresh = false)
    {

        $this->getChangedFilesList($version, $refresh);

        return !$this->versionIsModified;
    }

    /**
     * returns an array of files which are present in PrestaShop version $version and has been modified
     * in the current filesystem.
     *
     * @return array of string> array of filepath
     */
    public function getChangedFilesList($version = null, $refresh = false)
    {
        if (empty($version)) {
            $version = _PS_VERSION_;
        }
        if (is_array($this->changedFiles) && count($this->changedFiles) == 0) {
            $checksum = $this->getXmlMd5File($version, $refresh);
            if ($checksum == false) {
                $this->changedFiles = false;
            } else {
                $this->browseXmlAndCompare($checksum->ps_root_dir[0]);
            }
        }

        return $this->changedFiles;
    }

    /**
     * Compare the md5sum of the current files with the md5sum of the original
     *
     * @param mixed $node
     * @param array $currentPath
     * @param int   $level
     *
     * @return void
     */
    protected function browseXmlAndCompare($node, &$currentPath = [], $level = 1)
    {
        foreach ($node as $key => $child) {
            if (is_object($child) && $child->getName() == 'dir') {
                $currentPath[$level] = (string) $child['name'];
                $this->browseXmlAndCompare($child, $currentPath, $level + 1);
            } elseif (is_object($child) && $child->getName() == 'md5file') {
                // We will store only relative path.
                // absolute path is only used for file_exists and compare
                $relativePath = '';
                for ($i = 1; $i < $level; $i++) {
                    $relativePath .= $currentPath[$i].'/';
                }
                $relativePath .= (string) $child['name'];

                $fullpath = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.$relativePath;
                $fullpath = str_replace('ps_root_dir', _PS_ROOT_DIR_, $fullpath);

                // replace default admin dir by current one
                $fullpath = str_replace(_PS_ROOT_DIR_.'/admin', _PS_ADMIN_DIR_, $fullpath);
                $fullpath = str_replace(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'admin', _PS_ADMIN_DIR_, $fullpath);
                if (!file_exists($fullpath)) {
                    $this->addMissingFile($relativePath);
                } elseif (!$this->compareChecksum($fullpath, (string) $child) && substr(str_replace(DIRECTORY_SEPARATOR, '-', $relativePath), 0, 19) != 'modules/autoupgrade') {
                    $this->addChangedFile($relativePath);
                }
                // else, file is original (and ok)
            }
        }
    }

    /** populate $this->missing_files with $path
     *
     * @param string $path filepath to add, relative to _PS_ROOT_DIR_
     */
    protected function addMissingFile($path)
    {
        $this->versionIsModified = true;
        $this->missingFiles[] = $path;
    }

    protected function compareChecksum($filepath, $md5sum)
    {
        if (md5_file($filepath) == $md5sum) {
            return true;
        }

        return false;
    }

    /** populate $this->changed_files with $path
     * in sub arrays  mail, translation and core items
     *
     * @param string $path filepath to add, relative to _PS_ROOT_DIR_
     */
    protected function addChangedFile($path)
    {
        $this->versionIsModified = true;

        if (strpos($path, 'mails/') !== false) {
            $this->changedFiles['mail'][] = $path;
        } elseif (strpos($path, '/en.php') !== false || strpos($path, '/fr.php') !== false
            || strpos($path, '/es.php') !== false || strpos($path, '/it.php') !== false
            || strpos($path, '/de.php') !== false || strpos($path, 'translations/') !== false
        ) {
            $this->changedFiles['translation'][] = $path;
        } else {
            $this->changedFiles['core'][] = $path;
        }
    }
}
