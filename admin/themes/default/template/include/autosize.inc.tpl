{* {combine_script id='jquery.autogrow' load='async' require='jquery' path='themes/default/js/plugins/jquery.autogrow-textarea.js'} *}
{* Auto size and auto grow textarea *}
{* {footer_script require='jquery.autogrow'} *}
{footer_script}
{literal}
<script type="module">
import './themes/default/js/plugins/jquery.autogrow-textarea.js';
jQuery(document).ready(function(){
	jQuery('textarea').css('overflow-y', 'hidden');
	// Auto size and auto grow for all text area
	jQuery('textarea').autogrow();
});
</script>
{/literal}{/footer_script}