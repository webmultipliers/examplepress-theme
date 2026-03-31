<?php
/**
 * ExamplePress GitHub App Credentials
 *
 * These identify the ExamplePress GitHub App. The private key is NOT a
 * user secret — it identifies the app itself. The installation on the
 * user's org is what grants permissions.
 *
 * To configure:
 *   1. Create a GitHub App at github.com/settings/apps/new
 *   2. Set permissions: Repository Administration (R/W), Contents (R/W)
 *   3. Generate a private key and paste the PEM contents below
 *   4. Copy the App ID from the App settings page
 *
 * This file can also be generated via WP-CLI or an admin setup wizard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Uncomment and fill in after registering the GitHub App:
// define( 'EP_GITHUB_APP_ID', 000000 );
// define( 'EP_GITHUB_APP_PEM', <<<'PEM'
// -----BEGIN RSA PRIVATE KEY-----
// ... paste your private key here ...
// -----END RSA PRIVATE KEY-----
// PEM );
