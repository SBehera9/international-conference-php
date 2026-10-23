<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/components.php';

/* ==========================================================================
   ORGANIZER AUTH GUARD
   ==========================================================================
   Every organizer page includes this file at the top.

   Ensures:
     - User is logged in
     - User has the 'organizer' role
     - Organizer profile setup is complete

   If profile is incomplete, the user is redirected to
   /organizer/profile?setup=1

   Exposes:
     - $user    (current user array)
     - $profile (organizer profile array)
   ========================================================================== */

$user    = require_role('organizer');
$profile = require_organizer_profile();