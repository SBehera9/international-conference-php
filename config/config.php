<?php

/**
 * International Conference
 * Application Configuration
 *
 * IMPORTANT:
 *
 * LIVE WEBSITE:
 *     APP_ENV must be "production"
 *
 * LOCAL COMPUTER:
 *     Change APP_ENV to "local"
 *
 * Environment selection no longer depends on HTTP_HOST.
 */

declare(strict_types=1);


/* =========================================================================
   APPLICATION
   ========================================================================= */

define(
    'APP_NAME',
    'International Conference'
);


/* =========================================================================
   ENVIRONMENT
   ========================================================================= */

/**
 * LIVE SERVER:
 *
 *     production
 *
 * LOCAL DEVELOPMENT:
 *
 *     local
 *
 * Do NOT automatically detect the environment using HTTP_HOST.
 */

define(
    'APP_ENV',
    'production'
);


/**
 * Validate APP_ENV.
 */

if (!in_array(APP_ENV, ['production', 'local'], true)) {

    http_response_code(500);

    exit('Invalid application environment.');

}


/**
 * Convenient environment flag.
 */

define(
    'IS_LOCAL',
    APP_ENV === 'local'
);


/* =========================================================================
   ENVIRONMENT-SPECIFIC CONFIGURATION
   ========================================================================= */

if (IS_LOCAL) {

    /* =====================================================================
       LOCAL DEVELOPMENT
       ===================================================================== */

    define(
        'BASE_URL',
        'http://localhost/international-conference'
    );


    /* ---------------------------------------------------------------------
       Local database
       --------------------------------------------------------------------- */

    define(
        'DB_HOST',
        'localhost'
    );

    define(
        'DB_PORT',
        '3306'
    );

    define(
        'DB_NAME',
        'international_conference'
    );

    define(
        'DB_USER',
        'root'
    );

    define(
        'DB_PASS',
        ''
    );

} else {

    /* =====================================================================
       LIVE HOSTING / PRODUCTION
       ===================================================================== */

    define(
        'BASE_URL',
        'https://internationalconference.info'
    );


    /* ---------------------------------------------------------------------
       Production database configuration
       --------------------------------------------------------------------- */

    $localConfigFile = __DIR__ . '/config.local.php';


    /**
     * First try config.local.php.
     */

    if (is_file($localConfigFile)) {

        $local = require $localConfigFile;


        if (!is_array($local)) {

            $local = [];

        }

    } else {

        /**
         * If config.local.php does not exist,
         * try environment variables.
         */

        $local = [

            'db_host' => getenv('DB_HOST') ?: '',

            'db_port' => getenv('DB_PORT') ?: '3306',

            'db_name' => getenv('DB_NAME') ?: '',

            'db_user' => getenv('DB_USER') ?: '',

            'db_pass' => getenv('DB_PASS') ?: '',

        ];

    }


    /* ---------------------------------------------------------------------
       Validate production database configuration
       --------------------------------------------------------------------- */

    if (
        empty($local['db_host'])
        || empty($local['db_name'])
        || empty($local['db_user'])
    ) {

        http_response_code(500);

        exit(
            'Database configuration is missing.'
        );

    }


    /* ---------------------------------------------------------------------
       Production database constants
       --------------------------------------------------------------------- */

    define(
        'DB_HOST',
        (string) $local['db_host']
    );

    define(
        'DB_PORT',
        (string) ($local['db_port'] ?? '3306')
    );

    define(
        'DB_NAME',
        (string) $local['db_name']
    );

    define(
        'DB_USER',
        (string) $local['db_user']
    );

    define(
        'DB_PASS',
        (string) ($local['db_pass'] ?? '')
    );

}


/* =========================================================================
   DATABASE
   ========================================================================= */

define(
    'DB_CHARSET',
    'utf8mb4'
);


/* =========================================================================
   SESSION
   ========================================================================= */

define(
    'SESSION_NAME',
    'ic_session'
);

define(
    'SESSION_LIFETIME',
    7200
);


/* =========================================================================
   UPLOADS
   ========================================================================= */

/**
 * Maximum application upload size:
 *
 * 500 KB
 */

define(
    'UPLOAD_MAX_BYTES',
    500 * 1024
);


/* =========================================================================
   GOOGLE SITE VERIFICATION
   ========================================================================= */

define(
    'GOOGLE_SITE_VERIFICATION',
    'TpcFtz6zWO2LbPqJMRPTrvo5l2hncUG2YwGhwWDiEkE'
);


/* =========================================================================
   DEFAULT SEO
   ========================================================================= */

define(
    'DEFAULT_META_DESCRIPTION',
    'Discover verified international conferences, academic meetings, professional summits, and global research opportunities.'
);


define(
    'DEFAULT_META_KEYWORDS',
    'international conference, academic conferences, research conferences, global conferences'
);


/* =========================================================================
   PATHS
   ========================================================================= */

define(
    'ROOT_PATH',
    dirname(__DIR__)
);


define(
    'UPLOAD_PATH',
    ROOT_PATH . '/uploads'
);