<?php

namespace Tests\Unit;

use App\Services\Mentari\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentariLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_language_is_normalized(): void
    {
        $l = app(Language::class);
        $this->assertSame('berapa poin saya', $l->normalize('brp poin sy??'));
        $this->assertSame('training saya', $l->normalize('trening sy'));
        $this->assertSame('absen hari ini sudah belum', $l->normalize('abesn hr ini udh blm'));
        $this->assertSame('halo admin', $l->normalize('halooo min'));
        $this->assertSame('ujian nya apa', $l->normalize('ujiannya apa'));
        $this->assertSame('terima kasih', $l->normalize('makasih'));
    }

    public function test_correct_words_and_names_are_left_alone(): void
    {
        $l = app(Language::class);
        $this->assertSame('tukang las dimana', $l->normalize('tukang las dmn'));
        $this->assertSame('siapa ikhsanudin', $l->lite('siapa ikhsanudin'));
        $this->assertSame('KT16042', strtoupper($l->normalize('kt16042')));
    }

    public function test_transposed_letters_count_as_one_edit(): void
    {
        $this->assertSame(1, Language::distance('abesn', 'absen'));
        $this->assertSame(1, Language::distance('poni', 'poin'));
        $this->assertGreaterThan(1, Language::distance('kebaca', 'kenapa', 1));
    }
}
