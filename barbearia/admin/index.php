<?php
require __DIR__ . '/includes/auth.php';
redirect(current_user() ? 'admin/dashboard.php' : 'admin/login.php');
