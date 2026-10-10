<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An operation key was presented that belongs to a different request: another user, another kind of operation or a
 * different payload. The message is deliberately generic; nothing about the original operation is disclosed.
 */
class TransferOperationConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Kunci operasi ini tidak valid atau sudah dipakai. Muat ulang halaman form lalu coba lagi.');
    }
}
