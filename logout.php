<?php
require_once 'config/db.php';
session_destroy();
header('Location: ' . SITE_URL . '/index.php');
exit;
