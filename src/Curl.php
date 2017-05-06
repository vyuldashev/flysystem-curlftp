<?php

namespace VladimirYuldashev\Flysystem;

class Curl
{
    /**
     * @var resource
     */
    protected $curl;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param array $options Array of the Curl options, where key is a CURLOPT_* constant
     */
    public function __construct($options = [])
    {
        $this->curl = curl_init();
        $this->options = $options;
    }

    public function __destruct()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }

    /**
     * Set the Curl options.
     *
     * @param array $options Array of the Curl options, where key is a CURLOPT_* constant
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }

    /**
     * Set the Curl option.
     *
     * @param int $key One of the CURLOPT_* constant
     * @param mixed $value The value of the CURL option
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    /**
     * Returns the value of the option.
     *
     * @param  int $key One of the CURLOPT_* constant
     * @return mixed|null The value of the option set, or NULL, if it does not exist
     */
    public function getOption($key)
    {
        if (!$this->hasOption($key)) {
            return null;
        }

        return $this->options[$key];
    }

    /**
     * Checking if the option is set.
     *
     * @param  int $key One of the CURLOPT_* constant
     * @return bool
     */
    public function hasOption($key)
    {
        return array_key_exists($this->options, $key);
    }

    /**
     * Remove the option.
     *
     * @param  int $key One of the CURLOPT_* constant
     */
    public function removeOption($key)
    {
        if ($this->hasOption($key)) {
            unset($this->options[$key]);
        }
    }

    /**
     * Calls curl_exec and returns its result.
     *
     * @param  array $options Array where key is a CURLOPT_* constant
     * @return mixed Results of curl_exec
     */
    public function exec($options = [])
    {
        $options = array_replace($this->options, $options);

        curl_setopt_array($this->curl, $options);
        $result = curl_exec($this->curl);
        curl_reset($this->curl);

        return $result;
    }
}
