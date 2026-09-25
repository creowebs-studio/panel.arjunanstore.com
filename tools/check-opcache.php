<?php
// Cek status opcache CLI (baseline ~5s per request di drvfs bila mati).
echo 'opcache.enable_cli = '.ini_get('opcache.enable_cli').PHP_EOL;
echo 'opcache.enable     = '.ini_get('opcache.enable').PHP_EOL;
