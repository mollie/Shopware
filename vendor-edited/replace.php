<?php

	// Mollie Shopware Plugin Version: 1.3.14

/*
 *
 * Remove GIT files from vendor folder (to prevent submodule creation)
 *
 * find vendor -type d -name \.git -exec rm -rf \{\} \;
 *
 *
 * */

function replace_in_dir($dirname, $find, $replace, $depth = 0)
    {

        static $total = 0;
        if ($depth === 0){
            $total = 0;
        }

        $handle = opendir($dirname);
        while ($file = readdir($handle)){

            if (substr($file, 0, 1) === '.'){
                continue;
            }

            if (is_dir($dirname . $file)){
                replace_in_dir($dirname . $file . '/', $find, $replace, $depth + 1);
            }
            else{

                if (substr($file, -4) === '.php'){


                    $count = 0;
                    file_put_contents($dirname . $file, str_replace($find, $replace, file_get_contents($dirname . $file), $count));

                    if ($count){
                        echo $dirname . $file . ": " . $count . ' replacements' . "\n";
                        $total += $count;
                    }

                }


            }

        }

        if ($depth === 0){
            echo 'Made a total of ' . $total . ' changes';
            echo "\n";
        }

    }


    replace_in_dir(realpath(__DIR__ . '/..') . '/', 'Guzzle' . 'Http', 'Guzzle' . 'HttpV6');


?>