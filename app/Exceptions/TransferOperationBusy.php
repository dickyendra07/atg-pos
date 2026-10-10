<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The first request holding this operation key is still being processed and did not finish within the database's
 * lock wait. Nothing was changed by this request; the same form can safely be submitted again.
 */
class TransferOperationBusy extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Transfer ini masih diproses oleh permintaan sebelumnya. Tunggu sebentar lalu coba simpan lagi, tidak akan tersimpan dua kali.');
    }
}
