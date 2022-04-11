<?php

/**
 * Plugin name: Longcache
 * Description: Cache pages for a long time
 * Author: Joe Hoyle
 * Version: 0.0.1
 */

namespace Longcache;

require_once __DIR__ . '/inc/namespace.php';
require_once __DIR__ . '/inc/admin/namespace.php';

bootstrap();
Admin\bootstrap();
