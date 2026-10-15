<?php
declare(strict_types=1);

/**
 * Vercel entry point. vercel.json routes every non-asset request here (runtime: vercel-php),
 * and this simply hands off to the normal front controller.
 */
require dirname(__DIR__) . '/public/index.php';
