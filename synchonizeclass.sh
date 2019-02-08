#!/usr/bin/env bash
##
# Copyright (C) 2018-2019 thirty bees
#
# @author    thirty bees <modules@thirtybees.com>
# @copyright 2018-2019 thirty bees
# @license   proprietary

function usage {
  echo "Usage: synchronizeclass.sh [-h|--help] <class file> [<core class file>]"
  echo
  echo "This script synchonizes a class with thirty bees core."
  echo
  echo "    -h, --help          Show this help and exit."
  echo
  echo "    <class file>        Name of the class or better, name of the file"
  echo "                        containing the class. Even better: full path"
  echo "                        of the file containing the class in this module."
  echo
  echo "                        In the simple variants, the script tries to"
  echo "                        figure the remaining parts."
  echo
  echo "    <core class file>   If the script can't find this file on its own,"
  echo "                        path of the file in thirty bees core to"
  echo "                        synchronize with. Default is to search it"
  echo "                        automatically."
  echo
  echo "Example:"
  echo
  echo "  ./synchronizeclass.sh Shop"
  echo
  echo "If this doesn't work:"
  echo
  echo "  ./synchronizeclass.sh Shop.php"
  echo
  echo "If this still doesn't work:"
  echo
  echo "  ./synchronizeclass.sh classes/Shop.php"
  echo
  echo "If this still doesn't work:"
  echo
  echo "  ./synchronizeclass.sh classes/Shop.php ../../classes/shop/Shop.php"
  echo
  echo "Use Gitk to evaluate the result and Git to add it to the repository."
  echo
}


### Auxilliary functions.

function e {
  echo "${1} Aborting."
  exit 1
}


### Options parsing.

FILE_SOURCE=''
FILE_TARGET=''

while [ ${#} -ne 0 ]; do
  case "${1}" in
    '-h'|'--help')
      usage
      exit 0
      ;;
    *)
      if [ -z "${FILE_TARGET}" ]; then
        FILE_TARGET="${1}"
      elif [ -z "${FILE_SOURCE}" ]; then
        FILE_SOURCE="${1}"
      else
        e "Too many arguments. Try --help."
      fi
      ;;
  esac
  shift
done


### Finding source and target.

CLASS="${FILE_TARGET##*/}"
CLASS="${CLASS%.php}"

if [ "${FILE_TARGET}" = "${FILE_TARGET%.php}" ]; then
  FILE_TARGET="${FILE_TARGET}.php"
fi

if [ ! -r "${FILE_TARGET}" ]; then
  FILE_TARGET=$(find classes -name "${FILE_TARGET}")
fi

if [ ! -r "${FILE_TARGET}" ]; then
  e "Can't find local class file for ${CLASS}, please specify it exactly."
fi

if [ ! -r "${FILE_SOURCE}" ]; then
  FILE_SOURCE=$(find ../../classes -name "${FILE_TARGET##*/}")
fi

if [ ! -r "${FILE_SOURCE}" ]; then
  e "Can't find source class file ${CLASS}, please specify it exactly."
fi


### Do the synchonization.

echo "Syncronizing ${FILE_TARGET} to ${FILE_SOURCE}"

sed '
  # Header only.
  /^<?php$/, /^ \*\/$/ {
    # Fix license stuff and switch from OSL to AFL license.
    s/contact@thirtybees.com/modules@thirtybees.com/
    s/@license   http:\/\/opensource.org\/licenses\/osl-3.0.php/@license /
    s/Open Software License (OSL 3.0)/Academic Free License (AFL 3.0)/
    s/licenses\/osl-3.0.php/licenses\/afl-3.0.php/

    # Add namespace after the header.
    s/^ \*\/$/&\n\nnamespace PsOneSixMigrator;/
  }

  # Remove "Core" from class name.
  s/\([Cc]lass\s\+\w\+\)Core/\1/

  # PHP core class replacements to deal with the isolated namespace.
  # \b = word boundary, \\\? for an optional already existing backslash.
  s/\\\?\bArrayAccess\b/\\ArrayAccess/g
  s/\\\?\bCountable\b/\\Countable/g
  s/\\\?\bDateTime\b/\\DateTime/g
  s/\\\?\bDateTimeZone\b/\\DateTimeZone/g
  s/\\\?\bIterator\b/\\Iterator/g
  s/\\\?\bmysqli_result\b/\\mysqli_result/g
  s/\\\?\bPDO\b/\\PDO/g
  s/\\\?\bPDOStatement\b/\\PDOStatement/g
  s/\\\?\bSimpleXMLElement\b/\\SimpleXMLElement/g
  s/\\\?\bReflectionClass\b/\\ReflectionClass/g
  s/\\\?\bZipArchive\b/\\ZipArchive/g

  # Various other replacements to deal with the isolated namespace.
  s/\\\?\bAdapter_Exception\b/\\Exception/g
  s/\\\?\bPrestaShopException\b/\\Exception/g
  s/\\\?\bPrestaShopDatabaseException\b/\\Exception/g

  # Unused PS/thirty bees stuff. Point to the core implementation.
  s/\\\?\bWebserviceRequest\b/\\WebserviceRequest/g

  # Remove obsolete stuff.
  s/\s\+implements\s\+Core_Foundation_Database_EntityInterface//
' < "${FILE_SOURCE}" > "${FILE_TARGET}"


### Function fixes.
#
# Most of them just described as recommended manual fixes.
#
# These were found when comparing module class files of version 1.0.2 with
# core class files of Git version 1.0.6~1620 (which was about the closest match
# then).

case "${CLASS}" in
  'Context')
    echo "Known required manual tweaks:"
    echo " - Delete Context->getMobileDevice()."
    echo " - Delete Context->checkMobileContext()."
    echo " - Delete Context->getMobileDetect()."
    echo "... and each of their usage. is_tablet, is_mobile always false."
    ;;
  'Db')
    sed -i '
      /public static function getClass()/ {
        n
        a \'"        return (__NAMESPACE__ ? __NAMESPACE__.'\\\\\\\\' : '').'DbPDO';"'
        p; N; d;
      }
    ' "${FILE_TARGET}"
    ;;
  'Dispatcher')
    echo "Known required manual tweaks:"
    echo " - Delete Dispatcher::getModuleControllers()."
    echo " - Delete Dispatcher::dispatch()."
    echo " - Remove all code but the last line in Dispatcher::supplierID()."
    echo " - Remove all code but the last line in Dispatcher::manufacturerID()."
    echo " - Remove all code but the last line in Dispatcher::productID()."
    echo " - Remove all code but the last line in Dispatcher::categoryID()."
    echo " - Remove all code but the last line in Dispatcher::cmsID()."
    echo " - Remove all code but the last line in Dispatcher::cmsCategoryID()."
    ;;
  'Group')
    echo "Known required manual tweaks:"
    echo " - Delete Group::getReduction()."
    echo " - Delete Group::add()."
    ;;
  'Hook')
    echo "Known required manual tweaks:"
    echo " - Replace all code in Hook::exec() with just 'return;'"
    echo " - Replace all code in Hook::getHookModuleExecList() with just 'return [];'"
    echo " - Delete Hook::execWithoutCache()."
    echo " - Delete Hook::coreCallHook()."
    echo " - Delete Hook::postUpdateOrderStatus()."
    echo " - Delete Hook::orderConfirmation()."
    echo " - Delete Hook::updateOrderStatus()."
    echo " - Delete Hook::paymentReturn()."
    ;;
  'Language')
    echo "Known required manual tweaks:"
    echo " - Delete Language::updateModulesTranslations()."
    echo " - Delete Language::downloadAndInstallLanguagePack()."
    echo " - Delete Language::checkAndAddLanguage()."
    echo " - Delete Language::deleteSelection()."
    echo " - Delete Language::delete()."
    echo " - Delete Language::_copyNoneFlag()."
    echo " - Delete Language::moveToIso()."
    echo " - Delete Language::_getThemesList()."
    echo " - Delete Language::checkFiles()."
    echo " - Delete Language::checkFilesWithIsoCode()."
    echo " - Delete Language::getFilesList()."
    ;;
  'ObjectModel')
    echo "Known required manual tweaks:"
    echo " - Delete ObjectModel::getWebserviceParameters()."
    echo " - Delete ObjectModel::getWebserviceObjectList()."
    echo " - Delete ObjectModel::deleteImage()."
    ;;
  'Shop')
    echo "Known required manual tweaks:"
    echo " - Delete Shop::getAddress()."
    echo " - Delete Shop::getUrlsSharedCart()."
    echo " - Delete Shop::getGroup()."
    echo " - Delete Shop::getContextShopGroup()."
    echo " - Delete Shop::copyShopData()."
    ;;
  'Tab')
    echo "Known required manual tweaks:"
    echo " - Delete Tab::checkTabRights()."
    echo " - Delete Tab::getTabModulesList()."
    ;;
  'Tools')
    echo "Known required manual tweaks:"
    echo " - Delete Tools::getCountry()."
    echo " - Delete Tools::setCurrency()."
    echo " - Delete Tools::displayPriceSmarty()."
    echo " - Delete Tools::displayPrice()."
    echo " - Delete Tools::convertPrice()."
    echo " - Delete Tools::convertPriceFull()."
    echo " - Delete Tools::dieOrLog()."
    echo " - Delete Tools::throwDeprecated()."
    echo " - Remove all usages of Tools::throwDeprecated(), without replacement."
    echo " - Delete Tools::clearXMLCache()."
    echo " - Delete Tools::getMetaTags()."
    echo " - Delete Tools::getHomeMetaTags()."
    echo " - Delete Tools::completeMetaTags()."
    echo " - Delete Tools::getFullPath()."
    echo " - Delete Tools::getPath()."
    echo " - Delete Tools::orderbyPrice()."
    echo " - Delete Tools::minifyHTML()."
    echo " - Delete Tools::minifyHTMLpregCallback()."
    echo " - Delete Tools::packJSinHTML()."
    echo " - Delete Tools::packJSinHTMLpregCallback()."
    echo " - Delete Tools::packJS()."
    echo " - Delete Tools::minifyCSS()."
    echo " - Delete Tools::replaceByAbsoluteURL()."
    echo " - Delete Tools::cccCss()."
    echo " - Delete Tools::cccJS()."
    echo " - Delete Tools::generateIndex()."
    echo " - Delete Tools::clearColorListCache()."
    echo " - Remove all code but the last line in Tools::purifyHTML()."
    echo " - Delete Tools::parserSQL()."
    ;;
  'Translate')
    echo "Known required manual tweaks:"
    echo " - Delete Translate::getAdminTranslation()."
    ;;
  'Upgrader')
    echo "CAUTION: this class is pretty distinct from thirty bees core."
    echo "         Attempts to synchonize it have failed before. Probably"
    echo "         it's a good idea to keep this class unsynchonized."
    echo
    echo "Known (but incomplete) required manual tweaks:"
    echo "Add 'use PsOneSixMigrator\GuzzleHttp\Client;' to the header."
    echo "Add 'use PsOneSixMigrator\GuzzleHttp\Promise;' to the header."
    echo "Add 'use PsOneSixMigrator\SemVer\Expression;' to the header."
    echo "Add 'use PsOneSixMigrator\SemVer\Version;' to the header."
    ;;
  'Validate')
    echo "Known required manual tweaks:"
    echo " - Replace all code inside Validate::isEmail() with 'return true;'."
    ;;
esac


### Documentation.

# About the state of synchronization of class files with thirty bees core.
#
# All the files in classes/ are copies of the files in thirty bees core, with
# only few modifications. The idea is to have this module entirely independent
# from core files, because these core files get changes during migration,
# potentially breaking them for a short moment.
#
# All these files were added shortly after emerging module 'psonesixmigrator'
# from PrestaShop's module 'autoupdater', before release 1.0.0 of
# 'psonesixmigrator'.
#
# One can use this command to find the distinction between the class here and
# the class in thirty bees core. The Git version with the smallest number shows
# the closest match, which is likely the moment it was copied:
#
# for V in {1..2500}; do
#   git checkout 1.0.7~$V classes/shop/Shop.php
#   N=$(diff -w -u0 classes/shop/Shop.php modules.off/psonesixmigrator/classes/Shop.php | wc -l)
#   echo "$V $N"
# done
#
# It turned out that Git version 1.0.7~1620 is the closest match for most
# classes. Number of diff lines for this core version and version 1.0.2 in this
# module:
#
# classes/AbstractLogger.php: 134
# classes/Blowfish.php: 18
# classes/Cache.php: 10
# classes/CacheFs.php: 23
# classes/Configuration.php: 16
# classes/ConfigurationTest.php: 739
# classes/Context.php: 114
# classes/CryptBlowfish.php: 73
# classes/DbPDO.php: 533
# classes/Db.php: 1118
# classes/DbQuery.php: 283
# classes/Dispatcher.php: 22
# classes/Employee.php: 37
# classes/FileLogger.php: 97
# classes/Group.php: 10
# classes/Hook.php: 22
# classes/Language.php: 24
# classes/ObjectModel.php: 3313
# classes/PrestaShopCollection.php: 49
# classes/Shop.php: 23
# classes/ShopUrl.php: 182
# classes/Tab.php: 10
# classes/Tools.php: 1357
# classes/Translate.php: 10
# classes/Upgrader.php: 580
# classes/Validate.php: 10
#
