<div id='<?php echo $popover_messagebox; ?>' class='visiblebox' style='position: fixed; <?php echo $style; ?>'>
	<a href='' id='closebox' title='Close this box'></a>
	<div id='message' style='<?php echo $box; ?>'>
		<?php 
		$content = do_shortcode($popover_content);
		$content = apply_filters('the_content', $content);
		$content = str_replace(']]>', ']]&gt;', $content);
		echo $content; 
		?>
		<div class='clear'></div>
		<?php if($popover_hideforever != 'yes') {
			?>
			<div class='claimbutton hide'><a href='#' id='clearforever'><?php _e('Never see this message again.','popover'); ?></a></div>
			<?php
		}
		?>
	</div>
	<div class='clear'></div>
</div>