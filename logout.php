<?php require_once __DIR__.'/boot.php'; if(function_exists('remember_clear')) remember_clear(); session_destroy(); redirect('index.php'); ?>
