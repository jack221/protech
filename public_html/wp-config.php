<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */
// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'protechs_blog');
/** MySQL database username */
define('DB_USER', 'protechs_blog');
/** MySQL database password */
define('DB_PASSWORD', ';@7ZLmDItj&I');
/** MySQL hostname */
define('DB_HOST', 'localhost');
/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');
/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');
/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'D<W@eXf=LPm]&1As;#m14oG9q!e,>{q*Gu$7P(VU g^S0WPA0@KN(r%E5zet!hTh');
define('SECURE_AUTH_KEY',  '0q*g&3vG1?P.IS^A.|Tw:*-m5].}k^uXyw2(G?`V1vSWVyt2-e2t)4p+E#ZIBv_)');
define('LOGGED_IN_KEY',    'rop;TXvgDg>cL[{66H~bf8}<#LQ]E.efQ}>RZ4^iTy8KVqo8[U~I%m^C!GI[T*g:');
define('NONCE_KEY',        'Ko]#~JY4@@c V0T]fb{=((&W!mhlo:}|2Voeou%q1lmdp&0!Sk6P}OP<AV;,0{Ou');
define('AUTH_SALT',        'h]W_?wy_7YQk!@&ovA>WW&A^7%)9XEZ)l4Cbji77lW)aVHaV1zp!)`#^^}ax]oo]');
define('SECURE_AUTH_SALT', '5A4Q}zdtmF0D2_j}7JREhna{r5U# IXJdy~t`,dT2&*{8@@C@Hr&ESc6@[<wbxs0');
define('LOGGED_IN_SALT',   'khHJTZc2Ii<Jc;<[F%jK/CXS/iN1unrJ8)R}!3Mz[eau[6Zl6^#kS{9a8T.2|e+)');
define('NONCE_SALT',       'Sh+KD&=GRu2TkzJfr|3V%C%WX^Y~A!EbIs1$HaN>=9Z4X~sMb6+q79gW]TG|Cw^v');
/**#@-*/
/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
 
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define('DISABLE_WP_CRON', true);
define( 'WP_CACHE', true );


define( 'SCRIPT_DEBUG', false );
define( 'SAVEQUERIES', false );
/* That's all, stop editing! Happy blogging. */
/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');
/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');