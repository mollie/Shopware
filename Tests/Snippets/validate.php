<?php

include 'SnippetsValidator.php';


$frontendValidator = new SnippetsValidator(false);
$backendValidator = new SnippetsValidator(true);

$snippetsFolder = __DIR__ . "/../../Resources/snippets";

$backendFiles = searchFiles($snippetsFolder . "/backend");
$frontendFiles = searchFiles($snippetsFolder . "/frontend");


echo PHP_EOL;
echo PHP_EOL;
echo "SNIPPET VALIDATOR" . PHP_EOL;
echo "======================================================================" . PHP_EOL;


runValidation($frontendValidator, $frontendFiles);
runValidation($backendValidator, $backendFiles);



function runValidation(SnippetsValidator $validator, array $files)
{
    $allFiles = array_merge($files);

    foreach ($allFiles as $file) {

        if ($file === '.' || $file === '..') {
            continue;
        }

        echo $file . PHP_EOL;

        $errors = $validator->validate($file);

        $errorIndex = 1;

        /** @var ValidationError $error */
        foreach ($errors as $error) {

            echo $errorIndex . " >> " . $error->getLang() . ' | ' . $error->getKey() . ' | ' . $error->getError() . ' | ' . $error->getValue() . PHP_EOL;
            $errorIndex++;
        }

        if (count($errors) > 0) {
            exit(1);
        }
    }

}


function searchFiles($folder)
{
    return glob($folder . "/{,*/,*/*/,*/*/*/}*.ini", GLOB_BRACE);
}