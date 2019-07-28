<?php

namespace Bellona\View;

class View
{
    /** @var string $layout Path to layout. */
    private $layout;

    /** @var string $path Path to view. */
    private $path;

    /** @var array $data Data to pass to view. */
    private $data;

    /** @var array $yields Yields. */
    private $yields;

    /** @var string $currentYield Current yield. */
    private $currentYield = 'body';


    public function __construct(string $path, array $data = null)
    {
        $this->path = $path;
        $this->data = $data ?? [];
    }


    /**
     * Output view to HTML.
     *
     * @param string $path Path to view file relative to app/views/.
     * @param array $data Data to pass to view.
     */
    public function render(array $data = [])
    {
        // Turn on output buffering.
        ob_start();

        // Get data variables.
        $data = array_merge($this->data, $data);
        foreach ($data as $key => $value) {
            $$key = $value;
        }

        // Load view.
        require_once APP_ROOT . "/views/{$this->path}.php";

        if (isset($this->layout)) {
            // Inject any default markup into body yield.
            $this->injectDefaultBody();

            // Load view template.
            require_once APP_ROOT . "/views/layouts/{$this->layout}.php";
        }
    }


    /**
     * Set layout.
     *
     * @param $layout Name of layout relative to app/views/layout
     */
    private function extends(string $layout)
    {
        $this->layout = $layout;
    }


    /**
     * Start output buffer for a specific yield.
     *
     * @param string $name Name of yield.
     */
    private function block(string $name)
    {
        ob_clean();
        $this->currentYield = $name;
    }


    /**
     * Inject current output buffer into specified yield.
     */
    private function endblock()
    {
        $content = ob_get_clean();
        if (substr($content, -1) === "\n") {
            $content = substr($content, 0, -1);
        }

        $this->yields[$this->currentYield] = $content;

        ob_start();
    }


    /**
     * Inject output buffer into body yield
     * if no other yields were used.
     *
     * Allows views to be written without the use of
     * blocks, in case there only needs to be a body.
     */
    private function injectDefaultBody()
    {
        if (!$this->yields) {
            $this->currentYield = 'body';
            $this->endblock();
        }
    }


    /**
     * Output yield.
     *
     * @param string $name Name of yield.
     */
    private function yield(string $name)
    {
        echo $this->yields[$name] ?? '';
    }
}
