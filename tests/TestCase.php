<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Permintaan HTTP yang tidak di-fake akan melempar pengecualian,
        // bukan diam-diam keluar dari mesin ini.
        //
        // Tanpa ini, satu tes yang membuka /antrean akan memanggil n8n
        // sungguhan — menyetujui surat betulan, menomorinya, dan mengirim
        // notifikasi ke nomor WhatsApp asli. Kegagalan yang berisik jauh
        // lebih murah daripada surat yang terbit karena sebuah tes.
        Http::preventStrayRequests();
    }
}
