<?php
/**
 * Wizard Step 1 - Welcome
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<h2>Welcome to SD MultiCurrency Pro!</h2>

<p>Thank you for choosing SD MultiCurrency Pro for your WooCommerce store. This setup wizard will help you configure the plugin quickly.</p>

<h3>What this plugin does:</h3>
<ul style="list-style:disc; padding-left:20px;">
    <li>Display prices in multiple currencies</li>
    <li>Set custom prices per product per currency</li>
    <li>Support for Yoco gateway (ZAR checkout)</li>
    <li>Works with Tutor LMS courses</li>
    <li>Currency switcher for your customers</li>
</ul>

<h3>Requirements:</h3>
<ul style="list-style:disc; padding-left:20px;">
    <li>WooCommerce 7.0 or higher</li>
    <li>WordPress 6.0 or higher</li>
    <li>PHP 7.4 or higher</li>
</ul>

<p style="margin-top:30px;">
    <a href="<?php echo esc_url(admin_url('admin.php?page=sdmc-wizard&step=2')); ?>" class="button button-primary button-hero">
        Let's Get Started →
    </a>
</p>
