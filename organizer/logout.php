<?php
declare(strict_types=1);require_once dirname(__DIR__).'/includes/bootstrap.php';if(current_user())audit_log('LOGOUT','Organizer logged out');logout_user();redirect('/login');