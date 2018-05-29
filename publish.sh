#!/bin/sh


echo "checking out master"
git checkout master

echo "pulling last version"
git pull

echo "removing old publish dir"
sudo rm -r ../../../publish

echo "creating publish dir"
mkdir ../../../publish

echo "creating MollieShopware dir"
mkdir ../../../publish/MollieShopware

echo "copying files"
cp -a . ../../../publish/MollieShopware/


echo "removing all hidden files"
find ../../../publish/ -name .git -exec rm -rf '{}' \;
find ../../../publish/ -name .gitattributes -exec rm -rf '{}' \;
find ../../../publish/ -name .DS_Store -exec rm -rf '{}' \;
find ../../../publish/ -name .gitignore -exec rm -rf '{}' \;
find ../../../publish/ -name __MACOSX -exec rm -rf '{}' \;


cd ../../../publish/
echo "zipping..."
zip -r latest-build.zip MollieShopware

rm -r mollie-shopware

cd custom/plugins/MollieShopware