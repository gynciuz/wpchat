<?php
/**
 * Bare document for the full-screen /chatadmin app (see Frontend::render).
 *
 * Assets are registered and enqueued through the WordPress script/style API;
 * this template only asks WordPress to print the plugin's own handles.
 *
 * @package ChatAdmin
 *
 * @var string   $chatadmin_title
 * @var string[] $chatadmin_style_handles
 * @var string   $chatadmin_script_handle
 */

if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="dark">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="color-scheme" content="dark">
<title><?php echo esc_html($chatadmin_title); ?></title>
<?php wp_print_styles($chatadmin_style_handles); ?>
</head>
<body class="dark">
<div id="chatadmin-root" class="dark"></div>
<?php wp_print_scripts([$chatadmin_script_handle]); ?>
</body>
</html>
