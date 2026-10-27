<?php

require_once __DIR__ . '/../../middleware/scholar.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
header('Location: ' . BASE_URL . 'public/scholar/announcements.php');
exit;
