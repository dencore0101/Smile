<?php
require_once __DIR__ . '/includes/config.php';

if (!is_installed()) {
    redirect('install.php');
}
redirect('login.php');
