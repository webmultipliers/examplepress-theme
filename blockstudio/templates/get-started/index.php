<?php
/**
 * Get Started Template
 * @package ExamplePress
 */
?>

<main useBlockProps class="get-started">

	<!-- NAV -->
	<nav class="nav">
		<div class="nav-logo">
			<span>&lt;</span>ExamplePress<span>/&gt;</span>
		</div>
		<div class="nav-version"><?php echo esc_html( EP_THEME_VERSION ); ?></div>
	</nav>

	<!-- HERO -->
	<section class="hero">
		<div class="hero-inner">
			<div class="hero-badge reveal visible">Router Active</div>
			<h1 class="section-headline reveal visible" style="transition-delay:0.08s">
				Architecture<br><em>Philosophy.</em></h1>
			<p class="section-body reveal visible" style="transition-delay:0.16s">
				You are currently viewing the default <code class="inline">get-started</code> fallback template.
				ExamplePress is designed to be a <strong>read-only foundation</strong>. We highly discourage editing the
				base theme directly.
			</p>
		</div>
	</section>

	<!-- DOCS -->
	<section class="docs">
		<div class="docs-inner">

			<div class="step-card reveal visible" style="transition-delay:0.24s">
				<div class="step-number">01</div>
				<h3>Create a Core Plugin</h3>
				<p>Instead of modifying the theme, create a companion Core Plugin (e.g., <code
						class="inline">wp-content/plugins/examplepress-core/</code>) to intercept the router, define
					your own Blockstudio namespace, and build your custom views.</p>

				<div class="code-container">
					<div class="code-header">
						<span class="code-lang">examplepress-core.php</span>
						<button class="copy-btn" onclick="copyCode(this, 'code-1')">Copy</button>
					</div>
					<pre><code id="code-1"><span class="token php">&lt;?php</span>
							<span class="token comment">/**
								* Plugin Name: ExamplePress Core
								*/</span>

							<span class="token comment">// 1. Point the theme router to this plugin's blocks</span>
							<span class="token keyword">add_filter</span>( <span
								class="token string">'examplepress_theme_namespace'</span>, <span
								class="token keyword">function</span>() {
							<span class="token keyword">return</span> <span
								class="token string">'examplepress-core'</span>;
							} );

							<span class="token comment">// 2. Define your routing logic</span>
							<span class="token keyword">add_filter</span>( <span
								class="token string">'examplepress_route_context'</span>, <span
								class="token keyword">function</span>( $context ) {
							<span class="token keyword">if</span> ( <span class="token function">is_front_page</span>()
							|| <span class="token function">is_home</span>() ) {
							<span class="token keyword">return</span> <span class="token string">'front'</span>;
							}
							<span class="token keyword">return</span> $context;
							} );

							<span class="token comment">// 3. Initialize your standalone Blockstudio instance</span>
							<span class="token keyword">add_action</span>( <span class="token string">'init'</span>,
							<span class="token keyword">function</span> () {
							Blockstudio\Build::<span class="token function">init</span>( [
							<span class="token string">'dir'</span> => <span
								class="token function">plugin_dir_path</span>( __FILE__ ) . <span
								class="token string">'app'</span>,
							] );
							} );
						</code></pre>
				</div>
			</div>

			<div class="step-card reveal visible" style="transition-delay:0.32s">
				<div class="step-number">02</div>
				<h3>Create Your Custom Views</h3>
				<p>Based on the slugs you defined in your routing logic, create new template blocks inside your plugin's
					Blockstudio directory (e.g., <code class="inline">app/templates/front/</code>). The ExamplePress
					theme router will automatically discover and render them.</p>

				<div class="code-container">
					<div class="code-header">
						<span class="code-lang">block.json</span>
						<button class="copy-btn" onclick="copyCode(this, 'code-2')">Copy</button>
					</div>
					<pre><code id="code-2">{
							<span class="token attr">"$schema"</span>: <span
								class="token string">"https://blockstudio.dev/schema/block"</span>,
							<span class="token attr">"name"</span>: <span
								class="token string">"examplepress-core/template-front"</span>,
							<span class="token attr">"title"</span>: <span class="token string">"Template: Front
								Page"</span>,
							<span class="token attr">"category"</span>: <span class="token string">"theme"</span>,
							<span class="token attr">"blockstudio"</span>: <span class="token keyword">true</span>
							}</code></pre>
				</div>
			</div>

			<div class="step-card reveal visible" style="transition-delay:0.40s">
				<div class="step-number">03</div>
				<h3>Enjoy Your Pristine Theme</h3>
				<p>Once your plugin intercepts the namespace, this <code class="inline">get-started</code> fallback is
					completely bypassed. Your base theme can now be safely updated via Composer or remote zips without
					destroying your project's custom code.</p>
			</div>

		</div>
	</section>

	<!-- FOOTER -->
	<footer class="footer">
		<p>Built with <a href="https://blockstudio.dev/" target="_blank">Blockstudio</a>. Dev by Example.</p>
	</footer>