<?php

require_once __DIR__ . '/../../middleware/admin.php';
require_once __DIR__ . '/../../config/app.php';

header('Location: ' . BASE_URL . 'admin/users');
exit;
