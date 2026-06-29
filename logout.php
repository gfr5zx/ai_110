<?php
require_once __DIR__ . '/includes/init.php';
unset($_SESSION['member_id']);
redirect('/index.php');
