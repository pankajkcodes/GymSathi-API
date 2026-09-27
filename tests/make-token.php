<?php
// Dev tool: print a login token.   php tests/make-token.php user 12 | member 5 | admin 1
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../app/bootstrap.php';

[$type, $id] = [$argv[1] ?? '', $argv[2] ?? ''];
if (!in_array($type, ['user', 'member', 'admin'], true) || !ctype_digit($id)) {
    fwrite(STDERR, "Usage: php tests/make-token.php user|member|admin <id>\n");
    exit(1);
}
echo createToken($type, (int)$id, 1) . PHP_EOL;
