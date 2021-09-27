<?php


class SnippetsValidator
{

    /**
     * @var bool
     */
    private $isBackendValidator;


    /**
     * @param bool $isBackendValidator
     */
    public function __construct($isBackendValidator)
    {
        $this->isBackendValidator = $isBackendValidator;
    }

    /**
     * @param $filename
     * @return array
     */
    public function validate($filename)
    {
        $errors = [];


        $fileContent = file_get_contents($filename);

        if (mb_strpos($fileContent, "”\n") !== false) {
            $errors[] = new ValidationError(
                'FILE',
                'FILE',
                'FILE',
                "Character ” is not allowed. Please remove from file and use \" instead in " . $filename
            );
            return $errors;
        }


        $ini_array = parse_ini_file($filename, true);

        foreach ($ini_array as $language => $entries) {

            foreach ($entries as $key => $value) {

                if (empty($value)) {
                    $errors[] = new ValidationError(
                        $language,
                        $key,
                        $value,
                        "No translation existing for this key!"
                    );
                    continue;
                }

                if ($this->isBackendValidator) {

                    if ($this->endsWith($value, '"')) {
                        $errors[] = new ValidationError(
                            $language,
                            $key,
                            $value,
                            "Line has to end with \"."
                        );
                        continue;
                    }

                    # find ' that do not have a \ in front of it
                    $pattern = '/[^\\\\]\'/m';

                    $found = preg_match($pattern, $value);

                    if ($found) {

                        $errors[] = new ValidationError(
                            $language,
                            $key,
                            $value,
                            "Character ' is not allowed"
                        );
                    }
                }
            }
        }

        return $errors;
    }

    private function endsWith($haystack, $needle)
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }
}


class ValidationError
{

    /**
     * @var string
     */
    private $lang;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $value;

    /**
     * @var string
     */
    private $error;

    /**
     * @param string $lang
     * @param string $key
     * @param string $value
     * @param string $error
     */
    public function __construct($lang, $key, $value, $error)
    {
        $this->lang = $lang;
        $this->key = $key;
        $this->value = $value;
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

}
