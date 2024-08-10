{if empty($load_mode)}{$load_mode='footer'}{/if}
{combine_script id='jquery.ui.timepicker-addon' load=$load_mode require='jquery-ui' path="node_modules/jquery-ui-timepicker-addon/dist/jquery-ui-timepicker-addon.js"}

{combine_script id='datepicker' load=$load_mode require='jquery.ui.timepicker-addon' path='admin/themes/default/js/datepicker.js'}

{combine_css path="node_modules/jquery-ui/dist/themes/base/jquery-ui.css"}