<?php

declare(strict_types=1);

namespace View;

class Template
{
    private readonly string $template_dir;

    private ?string $template_file = null;

    /** @var array<string, mixed> */
    private array $vars = [];

    /**
     * @param \Security\CSRFProtectaroo $csrf Reaches the template as $csrf: a form
     *        writes <?= $csrf->field() ?>, a fetch() caller json_encode($csrf->getToken()).
     * @param int $admin_count Pending migrations, for an admin; 0 for everyone else. Reaches the
     *        template as $admin_count, and a set('admin_count') cannot replace it. Required, so a
     *        page that forgets it fails PHPStan instead of quietly losing the chip.
     */
    public function __construct(
        \Config\Config $config,
        private readonly \Security\CSRFProtectaroo $csrf,
        private readonly int $admin_count,
    ) {
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
        $csrf = $this->csrf;            // After extract(), so a set('csrf') cannot replace it
        $admin_count = $this->admin_count;  // Likewise for set('admin_count')

        ob_start();                     // Start output buffering
        include($template_path);        // Include the file
        $ob_result = ob_get_clean();

        if ($ob_result === false || $ob_result === '') {
            return "Error loading template: {$template_path}";
        }

        return $ob_result;
    }
}
