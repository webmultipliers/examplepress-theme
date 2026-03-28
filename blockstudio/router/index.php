<?php

$theme_ns        = 'examplepress-theme';
$target_slug     = examplepress_get_current_route();
$full_block_name = "{$theme_ns}/{$target_slug}";

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