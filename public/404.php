<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/bootstrap/error-handler.php';

$statusCode = 404;
renderApplicationErrorPage($statusCode);
