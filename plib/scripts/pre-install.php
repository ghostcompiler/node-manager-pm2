<?php
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    fwrite(STDERR, "Node Manager (PM2) requires PHP 7.2 or newer.\n");
    exit(1);
}

exit(0);
