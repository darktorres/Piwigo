{html_head}
<link rel="stylesheet" href="node_modules/jquery.Jcrop.js/css/jquery.Jcrop.css" />
{/html_head}
{combine_script id='jquery.jcrop' load='footer' require='jquery' path='node_modules/jquery.Jcrop.js/js/jquery.Jcrop.js'}

<form method="post">

	<fieldset>
		<legend>{'Photo sizes with crop'|@translate}</legend>
		{foreach $cropped_derivatives as $deriv}
			<img src="{$deriv.U_IMG}" alt="{$ALT}" {$deriv.HTM_SIZE}>
		{/foreach}
	</fieldset>

	<fieldset>
		<legend>{'Center of interest'|@translate}</legend>
		<p style="margin:0 0 10px 0;padding:0;">
			{'The center of interest is the most meaningful zone in the photo.'|@translate}
			{'For photo sizes with crop, such as "Square", Piwigo will do its best to include the center of interest.'|@translate}
			{'By default, the center of interest is placed in the middle of the photo.'|@translate}
			{'Select a zone with your mouse to define a new center of interest.'|@translate}
		</p>
		<input type="hidden" id="l" name="l" value="{if isset($coi)}{$coi.l}{/if}">
		<input type="hidden" id="t" name="t" value="{if isset($coi)}{$coi.t}{/if}">
		<input type="hidden" id="r" name="r" value="{if isset($coi)}{$coi.r}{/if}">
		<input type="hidden" id="b" name="b" value="{if isset($coi)}{$coi.b}{/if}">

		<img id="jcrop" src="{$U_IMG}" alt="{$ALT}">

		<p>
			<input type="submit" name="submit" value="{'Submit'|@translate}">
		</p>
	</fieldset>
</form>

{footer_script}<script>
	function from_coi(f, total) {
		return f * total;
	}

	function to_coi(v, total) {
		return v / total;
	}

	function jOnChange(sel) {
		var $img = jQuery("#jcrop");
		jQuery("#l").val(to_coi(sel.x, $img.width()));
		jQuery("#t").val(to_coi(sel.y, $img.height()));
		jQuery("#r").val(to_coi(sel.x2, $img.width()));
		jQuery("#b").val(to_coi(sel.y2, $img.height()));
	}

	function jOnRelease() {
		jQuery("#l,#t,#r,#b").val("");
	}

	jQuery("#jcrop").Jcrop({
			boxWidth: 500,
			boxHeight: 400,
			onChange: jOnChange,
			onRelease: jOnRelease
		}
		{if isset($coi)}
			,
			function() {
				var $img = jQuery("#jcrop");
				this.animateTo( [from_coi({$coi.l}, $img.width()), from_coi({$coi.t}, $img.height()), from_coi({$coi.r}, $img.width()), from_coi({$coi.b}, $img.height()) ] );
			}
		{/if}
	);
</script>{/footer_script}