<?php

/**
 * Search for files in which to replace the version number.
 *
 * @param $directory
 * @param array $exclude
 * @param null $version
 */
function handleDir($directory, $exclude = [], $version = null) {
    // Remove non-numeric characters from version
    $version = preg_replace('/[^0-9\.]/', '', $version);

    // Add trailing slash to directory
    if (substr($directory, -1) !== '/'){
        $directory .= '/';
    }

    // Open directory
    $handle = opendir($directory);

    // Iterate over files in directory
    while ($file = readdir($handle)) {
        // If item is in array of excluded files, skip the file
        if (!in_array($file, $exclude, true)) {
            $item = $directory . $file;

            // If item is a directory, handle it
            if (is_dir($item)){
                handleDir($item, $exclude, $version);
            }

            // If item is a file, add version string to it
            if (is_file($item)) {
                if ('MollieApiFactory.php'){
                    addVersionToApiFactory($item, $version);
                }

                if ($file === 'plugin.xml'){
                    addVersionToPluginXml($item, $version);
                }
            }
        }
    }
}

/**
 * Add the version to the API factory.
 *
 * @param $filename
 * @param $version
 * @return bool
 */
function addVersionToApiFactory($filename, $version)
{
    // Read the contents of the API factory file
    $contents = file_get_contents($filename);

    // Create a version replacement string
    $replace = 'MollieShopware/' . $version;

    // If no version string is found in the factory file, return false
    if (!preg_match('/MollieShopware\/[0-9\.]+\s*/', $contents, $match)) {
        return false;
    }

    // Replace the version within the file
    $contents = str_replace($match[0], $replace, $contents);

    // Save the contents
    file_put_contents($filename, $contents);
}

/**
 * Add the version to the plugin XML.
 *
 * @param $filename
 * @param $version
 */
function addVersionToPluginXml($filename, $version)
{
    // Read the contents of the plugin XML file
    $contents = file_get_contents($filename);

    // Replace the version in the plugin description tag
    $contents = preg_replace(
        '/\s*(\(v?[0-9\.]+\))?<\/description>/',
        ' (' . $version . ')</description>',
        $contents
    );

    // Replace the version in the plugin version tag
    $contents = preg_replace(
        '/\s*([0-9\.]+)?<\/version>/',
        $version . '</version>',
        $contents
    );

    // Save the contents
    file_put_contents($filename, $contents);
}

// If a version is given, search for files in which to replace the version number
// otherwise, show the usage of this script
if (count($argv) === 2) {
    $version = $argv[1];

    handleDir(__DIR__, ['..', '.', 'vendor'], $version);

}
else {
    die("\n\n\nUsage: php UpdateVersionNumber.php [versionnumber]\n\n\n");
}