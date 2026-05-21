<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

// Set a test flash message
$_SESSION['flash']['success'] = 'Automated test: success toast appears.';

// Capture header output
ob_start();
require __DIR__ . '/../includes/header.php';
$headerHtml = ob_get_clean();

// Show whether toast markup is present
if (strpos($headerHtml, 'id="flashToasts"') !== false && strpos($headerHtml, 'toast-body') !== false) {
    echo "TOAST_MARKUP_PRESENT\n";
} else {
    echo "TOAST_MARKUP_MISSING\n";
}

// Optionally dump the relevant snippet for inspection
$start = strpos($headerHtml, '<div id="flashToasts"');
if ($start !== false) {
    $end = strpos($headerHtml, '</div>', $start);
    if ($end !== false) {
        echo substr($headerHtml, $start, $end - $start + 6) . "\n";
    }
}
