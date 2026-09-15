<?php

declare(strict_types=1);

namespace View;

class Template
{
    private string $template_dir;

    private ?string $template_file = null;

    /** @var array<string, mixed> */
    private array $vars = [];

    public function __construct(\Config\Config $config)
    {
        $this->template_dir = "{$config->app_path}/templates";
    }

    public function setTemplate(string $template_file): void
    {
        $this->template_file = $template_file;
    }

    /**
     * @param mixed $value mixed so array of file names can be passed in /list/index.php
     */
    public function set(string $name, mixed $value): void
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
     * @return string
     */
    public function grabTheGoods(): string
    {
        return $this->loadTemplate();
    }

    protected function loadTemplate(): string
    {
        if ($this->template_file === null) {
            return "No template file provided";
        }
        $template_path = $this->template_dir . "/" . $this->template_file;

        $charEncode = "UTF-8";
        extract($this->vars);           // Extract the vars to local namespace

        ob_start();                     // Start output buffering
        include($template_path);        // Include the file
        $ob_result = ob_get_clean();

        if ($ob_result === false || $ob_result === '') {
            return "Error loading template: {$template_path}";
        }

        return $ob_result;
    }
}
