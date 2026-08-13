<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SetupState
{
    public static function initial(): array { return ['completed'=>0,'skipped'=>0,'step'=>1]; }
    public static function skipped(): array { return ['completed'=>0,'skipped'=>1,'step'=>1]; }
    public static function completed(): array { return ['completed'=>1,'skipped'=>0,'step'=>5]; }
    public static function needsSetup(array $state): bool { return empty($state['completed'])&&empty($state['skipped']); }
}
