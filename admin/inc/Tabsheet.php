<?php

declare(strict_types=1);

namespace Piwigo\admin\inc;

use function Piwigo\inc\trigger_change;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

class Tabsheet
{
    public array $sheets = [];

    public $uniqid = null;

    public string $selected = '';

    /*
      $name is the tabsheet's name inside the template .tpl file
      $titlename in the template is affected by $titlename value
    */
    public function __construct(
        public mixed $name = 'TABSHEET',
        public mixed $titlename = 'TABSHEET_TITLE'
    ) {
    }

    public function set_id($id): void
    {
        $this->uniqid = $id;
    }

    /*
       add a tab
    */
    public function add($name, $caption, $url, bool $selected = false): bool
    {
        if (! isset($this->sheets[$name])) {
            $this->sheets[$name] = [
                'caption' => $caption,
                'url' => $url,
            ];
            if ($selected) {
                $this->selected = $name;
            }

            return true;
        }

        return false;
    }

    /*
       remove a tab
    */
    public function delete($name): bool
    {
        if (isset($this->sheets[$name])) {
            array_splice($this->sheets, $name, 1);

            if ($this->selected == $name) {
                $this->selected = '';
            }

            return true;
        }

        return false;
    }

    /*
       select a tab to be active
    */
    public function select($name): void
    {
        $this->sheets = trigger_change('tabsheet_before_select', $this->sheets, $this->uniqid);
        if (! array_key_exists($name, $this->sheets)) {
            $keys = array_keys($this->sheets);
            $name = $keys[0];
        }

        $this->selected = $name;
    }

    /*
      set $titlename value
    */
    /**
     * @return mixed|string
     */
    public function set_titlename($titlename): mixed
    {
        $this->titlename = $titlename;
        return $this->titlename;
    }

    /*
      returns $titlename value
    */
    /**
     * @return mixed|string
     */
    public function get_titlename(): mixed
    {
        return $this->titlename;
    }

    /*
      returns properties of selected tab
    */
    /**
     * @return mixed|null
     */
    public function get_selected(): mixed
    {
        if ($this->selected !== '' && $this->selected !== '0') {
            return $this->sheets[$this->selected];
        }

        return null;

    }

    /*
     * Build TabSheet and assign this content to current page
     *
     * Fill $this->$name {default value = TABSHEET} with HTML code for tabsheet
     * Fill $this->titlename {default value = TABSHEET_TITLE} with formated caption of the selected tab
     */
    public function assign(): void
    {
        global $template;

        $template->set_filename('tabsheet', 'tabsheet.tpl');
        $template->assign('tabsheet', $this->sheets);
        $template->assign('tabsheet_selected', $this->selected);

        $selected_tab = $this->get_selected();

        if (isset($selected_tab)) {
            $template->assign(
                [
                    $this->titlename => '[' . $selected_tab['caption'] . ']',
                ]
            );
        }

        $template->assign_var_from_handle($this->name, 'tabsheet');
        $template->clear_assign('tabsheet');
    }
}
