<?php

namespace CurlLibrary\Exception;

/**
 * Class V2Exception
 *
 * @package CurlLibrary\Exception
 */
class V2Exception extends \Exception
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @param bool $flatten
     *
     * @return array|string
     */
    public function getErrors($flatten = false)
    {
        if ($flatten) {
            $flattened = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($this->errors));
            foreach($iterator as $item) {
                $flattened[] = $item;
            }

            return $flattened;
        }

        return $this->errors;
    }

    /**
     * @param array $errors
     *
     * @return $this
     */
    public function setErrors(array $errors = [])
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * @param string $error
     *
     * @return $this
     */
    public function addError($error)
    {
        $this->errors[] = $error;

        return $this;
    }
}
