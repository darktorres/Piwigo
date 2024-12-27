<?php

declare(strict_types=1);

class PwgSessionHandler implements SessionHandlerInterface
{
    public function open(string $save_path, string $name): bool
    {
        return pwg_session_open($save_path, $name);
    }

    public function close(): bool
    {
        return pwg_session_close();
    }

    public function read(string $session_id): string|false
    {
        return pwg_session_read($session_id);
    }

    public function write(string $session_id, string $session_data): bool
    {
        return pwg_session_write($session_id, $session_data);
    }

    public function destroy(string $session_id): bool
    {
        return pwg_session_destroy($session_id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return pwg_session_gc();
    }
}
