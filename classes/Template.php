<?php

class Template
{
    protected $template_location;

    protected $vars;

    // Encapsulates superglobals e.g. $SESSION, $REQUEST, etc
    // (misspelled in this comment to keep searches clean)
    protected $mla_request;
    protected $di_dbase;

    public function __construct(\Config $config)
    {
        $this->template_location = "{$config->app_path}/templates";

        $this->vars = [];
    }

    public function setTemplate($template_file)
    {
        $this->template_location = $this->template_location . "/" . $template_file;
    }

    /**
     * Summary of set
     * @param string $name
     * @param mixed $value mixed so array of file names can be passed in /list/index.php
     * @return void
     */
    public function set(string $name, mixed $value)
    {
        $this->vars[$name] = $value;
    }

    public function echoToScreen(): void
    {
        echo $this->loadTemplate(); // Display the contents directly to the page
    }

    /**
     * Hand over the rendered template, mate.
     * I'm gonna give it to this guy over here.
     *
     * This function is used to return the rendered template as a string.
     * It is used to get the inner content of what will be sent to a base template.
     * @return bool|string
     */
    public function grabTheGoods(): string
    {
        return $this->loadTemplate();
    }

    protected function loadTemplate(): string
    {
        $charEncode = "UTF-8";
        extract($this->vars);           // Extract the vars to local namespace

        ob_start();                     // Start output buffering

        if (!isset($this->template_location)) {
            echo "No template file provided";
        }

        include($this->template_location);  // Include the file

        $ob_result = ob_get_clean();

        // if $ob_result is false, return an error message
        if (empty($ob_result)) {
            return "Error loading template: {$this->template_location}";
        }

        return $ob_result;
    }
}
