<?php
/**
 * Wizard Header
 */

if (!defined('ABSPATH')) {
    exit;
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title>SD MultiCurrency Pro Setup</title>
    <?php wp_head(); ?>
    <style>
        body {
            background: #f0f0f1;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        .sdmc-wizard-container {
            max-width: 800px;
            margin: 50px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .sdmc-wizard-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .sdmc-wizard-header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .sdmc-wizard-steps {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .sdmc-wizard-step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 5px;
            font-weight: 600;
        }
        .sdmc-wizard-step.active {
            background: #fff;
            color: #1e3a5f;
        }
        .sdmc-wizard-step.completed {
            background: #46b450;
            color: #fff;
        }
        .sdmc-wizard-content {
            padding: 40px;
        }
        .sdmc-wizard-footer {
            border-top: 1px solid #ddd;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="sdmc-wizard-container">
        <div class="sdmc-wizard-header">
            <h1>SD MultiCurrency Pro Setup</h1>
            <p>Let's configure your multi-currency store</p>
            <div class="sdmc-wizard-steps">
                <div class="sdmc-wizard-step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                <div class="sdmc-wizard-step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                <div class="sdmc-wizard-step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                <div class="sdmc-wizard-step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
            </div>
        </div>
        <div class="sdmc-wizard-content">
