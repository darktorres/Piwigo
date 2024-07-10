<?php

/**
 * Runtime Extension Capture
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Runtime_Capture
{
    /**
     * Flag that this instance  will not be cached
     *
     * @var bool
     */
    public $isPrivateExtension = true;

    /**
     * Stack of capture parameter
     */
    private array $captureStack = [];

    /**
     * Current open capture sections
     *
     * @var int
     */
    private $captureCount = 0;

    /**
     * Count stack
     *
     * @var int[]
     */
    private array $countStack = [];

    /**
     * Named buffer
     *
     * @var string[]
     */
    private array $namedBuffer = [];

    /**
     * Flag if callbacks are registered
     */
    private bool $isRegistered = false;

    /**
     * Open capture section
     *
     * @param string                    $buffer capture name
     * @param string                    $assign variable name
     * @param string                    $append variable name
     */
    public function open(
        Smarty_Internal_Template $_template,
        $buffer,
        $assign,
        $append
    ): void {
        if (! $this->isRegistered) {
            $this->register($_template);
        }

        $this->captureStack[] = [
            $buffer,
            $assign,
            $append,
        ];
        $this->captureCount++;
        ob_start();
    }

    /**
     * Start render callback
     */
    public function startRender(Smarty_Internal_Template $_template): void
    {
        $this->countStack[] = $this->captureCount;
        $this->captureCount = 0;
    }

    /**
     * Close capture section
     */
    public function close(Smarty_Internal_Template $_template): void
    {
        if ($this->captureCount) {
            [$buffer, $assign, $append] = array_pop($this->captureStack);
            $this->captureCount--;
            if (isset($assign)) {
                $_template->assign($assign, ob_get_contents());
            }

            if (isset($append)) {
                $_template->append($append, ob_get_contents());
            }

            $this->namedBuffer[$buffer] = ob_get_clean();
        } else {
            $this->error($_template);
        }
    }

    /**
     * Error exception on not matching {capture}{/capture}
     */
    public function error(
        Smarty_Internal_Template $_template
    ): never {
        throw new SmartyException(sprintf("Not matching {capture}{/capture} in '%s'", $_template->template_resource));
    }

    /**
     * Return content of named capture buffer by key or as array
     *
     * @param string|null               $name
     *
     * @return string|string[]|null
     */
    public function getBuffer(
        Smarty_Internal_Template $_template,
        $name = null
    ) {
        if (isset($name)) {
            return $this->namedBuffer[$name] ?? null;
        }

        return $this->namedBuffer;

    }

    /**
     * End render callback
     */
    public function endRender(Smarty_Internal_Template $_template): void
    {
        if ($this->captureCount) {
            $this->error($_template);
        } else {
            $this->captureCount = array_pop($this->countStack);
        }
    }

    /**
     * Register callbacks in template class
     */
    private function register(
        Smarty_Internal_Template $_template
    ): void {
        $_template->startRenderCallbacks[] = $this->startRender(...);
        $_template->endRenderCallbacks[] = $this->endRender(...);
        $this->startRender($_template);
        $this->isRegistered = true;
    }
}
