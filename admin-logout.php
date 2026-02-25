<?php
/**
 * admin-logout.php — Déconnexion superadmin
 */
session_name('anytech_admin');
if (session_status() === PHP_SESSION_NONE) session_start();
session_unset();
session_destroy();
header('Location: admin-login.php');
exit();
