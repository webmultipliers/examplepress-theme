<?php

$theme_ns        = examplepress_get_theme_namespace();
$target_slug     = examplepress_get_current_route();
$template_prefix = examplepress_get_template_prefix();
$full_block_name = examplepress_get_template_block_name( $target_slug, $template_prefix, $theme_ns );

$block_content = bs_block(
	[
		'id'   => $full_block_name,
		'data' => [],
	]
);

?>
<?php if ( $block_content ) : ?>
	<?php echo $block_content; ?>
<?php else : ?>
	<div useBlockProps>
		Missing Template:
		<?php echo esc_html( $full_block_name ); ?>
	</div>
<?php endif; ?>