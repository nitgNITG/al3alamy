<?php
/**
 * kashier/env.php — Minimal .env loader for Kashier credentials.
 *
 * Reads $CFG->dirroot/.env (one KEY=VALUE per line, # = comment).
 * Values are loaded into getenv() / $_ENV so they're accessible anywhere.
 * Safe to call multiple times — loads only once per request.
 */

defined('MOODLE_INTERNAL') || die();

function kashier_load_env(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    global $CFG;
    $file = rtrim($CFG->dirroot, '/') . '/.env';

    if (!is_readable($file)) {
        // .env missing — caller will hit getenv() returning false and should
        // throw a clear error rather than silently using empty credentials.
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;         // skip comments
        if (strpos($line, '=') === false) continue;             // skip malformed

        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);

        // Strip optional surrounding quotes  "value"  or  'value'
        if (strlen($val) >= 2
            && (($val[0] === '"'  && $val[-1] === '"')
             || ($val[0] === "'"  && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }

        if ($key === '') continue;

        // Only set if not already defined by the real environment.
        if (getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}
