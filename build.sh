#!/usr/bin/env bash
##
# Copyright (C) 2017-2019 thirty bees
#
# NOTICE OF LICENSE
#
# This source file is subject to the Academic Free License (AFL 3.0)
# that is bundled with this package in the file LICENSE.md.
# It is also available through the world-wide-web at this URL:
# http://opensource.org/licenses/afl-3.0.php
# If you did not receive a copy of the license and are unable to
# obtain it through the world-wide-web, please send an email
# to license@thirtybees.com so we can send you a copy immediately.
#
# @author    thirty bees <modules@thirtybees.com>
# @copyright 2017-2019 thirty bees
# @license   Academic Free License (AFL 3.0)

CWD_BASENAME=${PWD##*/}

FILES=("ajax-upgradetab.php")
FILES+=("alias.php")
FILES+=("cacert.pem")
FILES+=("functions.php")
FILES+=("init.php")
FILES+=("logo.gif")
FILES+=("logo.png")
FILES+=("${CWD_BASENAME}.php")
FILES+=("cache/**")
FILES+=("classes/**")
FILES+=("controllers/**")
FILES+=("json/**")
FILES+=("views/**")

MODULE_VERSION="$(sed -ne "s/\\\$this->version *= *['\"]\([^'\"]*\)['\"] *;.*/\1/p" ${CWD_BASENAME}.php)"
MODULE_VERSION=${MODULE_VERSION//[[:space:]]}
ZIP_FILE="${CWD_BASENAME}/${CWD_BASENAME}-v${MODULE_VERSION}.zip"

echo "Going to zip ${CWD_BASENAME} version ${MODULE_VERSION}"

cd ..
for E in "${FILES[@]}"; do
  find ${CWD_BASENAME}/${E}  -type f -exec zip -9 ${ZIP_FILE} {} \;
done
