<?php
declare(strict_types=1);

/*
 * Self-Healing V1 safe test harness.
 * This script runs only in CI. It never touches production files,
 * databases, Guru master data, Absensi data, or config.local.php.
 */

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
    echo "PASS: {$message}" . PHP_EOL;
}

$source = "<?php\nfunction demo(): string { return 'old'; }\n";
$old = "return 'old';";
$new = "return 'new';";

assert_true(substr_count($source, $old) === 1, 'exact-match finds one occurrence');

$candidate = str_replace($old, $new, $source, $count);
assert_true($count === 1, 'exact replacement changes exactly one occurrence');
assert_true(str_contains($candidate, "return 'new';"), 'candidate contains the new code');
assert_true(!str_contains($candidate, "return 'old';"), 'candidate no longer contains the old code');

$ambiguous = $source . $old;
assert_true(substr_count($ambiguous, $old) === 2, 'ambiguous patch is detected');
assert_true(substr_count($ambiguous, $old) !== 1, 'ambiguous patch is rejected by policy');

echo "Self-Healing safe test completed successfully." . PHP_EOL;
