#!/bin/sh

clear

export version="$1"

## Showing Kiener Logo, just for fun
echo ""
cat logo.txt
echo ""
echo ""
echo "Exporting..."
echo ""
echo ""

## remove our previous publication directory. We SUDO this because sometimes
## a checkout can lead to read-only files (don't ask me why).
sudo rm -r prepare
mkdir prepare

cd prepare

git clone git@github.com:mollie/Shopware.git MollieShopware

cd MollieShopware

git fetch --all --tags --prune
git checkout tags/$version -b $version

sudo rm -r -f .git

cd ..

## generate ZIP file
zip -r MollieShopware .

## store ZIP file with other versions of the plugin
cp MollieShopware.zip ../files/Kiener_MollieShopware-$version.zip

## remove latest build and store latest build as latest-build.zip
rm ../files/latest-build.zip
mv MollieShopware.zip ../files/latest-build.zip





## open "files" folder in finder
open "../files"

cd ..

sudo rm -r prepare