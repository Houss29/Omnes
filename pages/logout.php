<?php
// pages/logout.php
define('ROOT', '..');
require_once '../config.php';
session_destroy();
header('Location: '.ROOT.'/index.php');
exit;
