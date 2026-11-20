<?php

namespace App\Services\Points;

use RuntimeException;

/** Kesalahan yang aman ditampilkan ke pengguna (saldo kurang, stok habis, …). */
class PointException extends RuntimeException {}
