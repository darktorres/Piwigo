<?php
/**
 * Smarty Internal Plugin Compile ForeachSection
 * Shared methods for {foreach} {section} tags
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile ForeachSection Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_ForeachSection extends Smarty_Internal_CompileBase
{
    /**
     * Name of this tag
     *
     * @var string
     */
    public $tagName = '';

    /**
     * Valid properties of $smarty.xxx variable
     *
     * @var array
     */
    public $nameProperties = [];

    /**
     * {section} tag has no item properties
     *
     * @var array
     */
    public $itemProperties = null;

    /**
     * {section} tag has always name attribute
     *
     * @var bool
     */
    public $isNamed = true;

    /**
     * @var array
     */
    public $matchResults = [];

    /**
     * Preg search pattern
     *
     * @var string
     */
    private $propertyPreg = '';

    /**
     * Offsets in preg match result
     *
     * @var array
     */
    private $resultOffsets = [];

    /**
     * Start offset
     *
     * @var int
     */
    private $startOffset = 0;

    /**
     * Scan sources for used tag attributes
     *
     * @param array                                 $attributes
     */
    public function scanForProperties(
        $attributes,
        Smarty_Internal_TemplateCompilerBase $compiler
    ) {
        $this->propertyPreg = '~(';
        $this->startOffset = 1;
        $this->resultOffsets = [];
        $this->matchResults = [
            'named' => [],
            'item' => [],
        ];
        if (isset($attributes['name'])) {
            $this->buildPropertyPreg(true, $attributes);
        }

        if ($this->itemProperties !== null) {
            if ($this->isNamed) {
                $this->propertyPreg .= '|';
            }

            $this->buildPropertyPreg(false, $attributes);
        }

        $this->propertyPreg .= ')\W~i';
        // Template source
        $this->matchTemplateSource($compiler);
        // Parent template source
        $this->matchParentTemplateSource($compiler);
        // {block} source
        $this->matchBlockSource($compiler);
    }

    /**
     * Build property preg string
     *
     * @param bool  $named
     * @param array $attributes
     */
    public function buildPropertyPreg(
        $named,
        $attributes
    ) {
        if ($named) {
            $this->resultOffsets['named'] = $this->startOffset += 3;
            $this->propertyPreg .= sprintf('(([$]smarty[.]%s[.]', $this->tagName) .
                                   ($this->tagName === 'section' ? "|[\[]\s*" : '') .
                                   sprintf(')%s[.](', $attributes['name']);
            $properties = $this->nameProperties;
        } else {
            $this->resultOffsets['item'] = $this->startOffset += 2;
            $this->propertyPreg .= sprintf('([$]%s[@](', $attributes['item']);
            $properties = $this->itemProperties;
        }

        $propName = reset($properties);
        while ($propName) {
            $this->propertyPreg .= $propName;
            $propName = next($properties);
            if ($propName) {
                $this->propertyPreg .= '|';
            }
        }

        $this->propertyPreg .= '))';
    }

    /**
     * Find matches in source string
     *
     * @param string $source
     */
    public function matchProperty(
        $source
    ) {
        preg_match_all($this->propertyPreg, $source, $match);
        foreach ($this->resultOffsets as $key => $offset) {
            foreach ($match[$offset] as $m) {
                if (! empty($m)) {
                    $this->matchResults[$key][smarty_strtolower_ascii($m)] = true;
                }
            }
        }
    }

    /**
     * Find matches in template source
     */
    public function matchTemplateSource(
        Smarty_Internal_TemplateCompilerBase $compiler
    ) {
        $this->matchProperty($compiler->parser->lex->data);
    }

    /**
     * Find matches in all parent template source
     */
    public function matchParentTemplateSource(
        Smarty_Internal_TemplateCompilerBase $compiler
    ) {
        // search parent compiler template source
        $nextCompiler = $compiler;
        while ($nextCompiler !== $nextCompiler->parent_compiler) {
            $nextCompiler = $nextCompiler->parent_compiler;
            if ($compiler !== $nextCompiler) {
                // get template source
                $_content = $nextCompiler->template->source->getContent();
                if ($_content !== '') {
                    // run pre filter if required
                    if ((isset($nextCompiler->smarty->autoload_filters['pre']) ||
                         isset($nextCompiler->smarty->registered_filters['pre']))
                    ) {
                        $_content = $nextCompiler->smarty->ext->_filterHandler->runFilter(
                            'pre',
                            $_content,
                            $nextCompiler->template
                        );
                    }

                    $this->matchProperty($_content);
                }
            }
        }
    }

    /**
     * Find matches in {block} tag source
     */
    public function matchBlockSource(
        Smarty_Internal_TemplateCompilerBase $compiler
    ) {
    }

    /**
     * Compiles code for the {$smarty.foreach.xxx} or {$smarty.section.xxx}tag
     *
     * @param array                                 $args      array with attributes from parser
     * @param \Smarty_Internal_TemplateCompilerBase $compiler  compiler object
     * @param array                                 $parameter array with compilation parameter
     *
     * @return string compiled code
     */
    public function compileSpecialVariable(
        $args,
        Smarty_Internal_TemplateCompilerBase $compiler,
        $parameter
    ) {
        $tag = smarty_strtolower_ascii(trim((string) $parameter[0], '"\''));
        $name = isset($parameter[1]) ? $compiler->getId($parameter[1]) : false;
        if (! $name) {
            $compiler->trigger_template_error(
                sprintf('missing or illegal $smarty.%s name attribute', $tag),
                null,
                true
            );
        }

        $property = isset($parameter[2]) ? smarty_strtolower_ascii($compiler->getId($parameter[2])) : false;
        if (! $property || ! in_array($property, $this->nameProperties)) {
            $compiler->trigger_template_error(
                sprintf('missing or illegal $smarty.%s property attribute', $tag),
                null,
                true
            );
        }

        $tagVar = sprintf("'__smarty_%s_%s'", $tag, $name);
        return sprintf(
            '(isset($_smarty_tpl->tpl_vars[%s]->value[\'%s\']) ? $_smarty_tpl->tpl_vars[%s]->value[\'%s\'] : null)',
            $tagVar,
            $property,
            $tagVar,
            $property
        );
    }
}
