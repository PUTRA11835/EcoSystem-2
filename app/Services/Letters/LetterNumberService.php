<?php

namespace App\Services\Letters;

use App\Models\Letters\Letter;
use App\Models\Letters\LetterSetting;
use App\Models\Recruitment\Offer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Letter numbers. The running number comes from a counter per direction and
 * year (letter_number_sequences) — never from "the highest number + 1" — so a
 * number once given out is never given out again, even when its letter is
 * voided or deleted. Outgoing letters of the hub and offering letters share
 * the outgoing counter, whatever their code or language.
 */
class LetterNumberService
{
    public const OUTGOING = 'outgoing';
    public const INCOMING = 'incoming';

    private const ROMAN_MONTHS = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** The running number the next letter of $year would get — a preview, nothing is taken. */
    public function peek(string $direction, int $year): int
    {
        return (int) DB::table('letter_number_sequences')->where('direction', $direction)->where('year', $year)->value('last_value') + 1;
    }

    /**
     * Takes the next running number of $year. The counter row is locked for
     * the rest of the caller's transaction, so two letters generated at the
     * same moment never get the same number. Must run inside DB::transaction().
     */
    public function take(string $direction, int $year): int
    {
        DB::table('letter_number_sequences')->insertOrIgnore([
            'direction' => $direction, 'year' => $year, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = DB::table('letter_number_sequences')->where('direction', $direction)->where('year', $year)->lockForUpdate()->first();
        $next = (int) $row->last_value + 1;

        DB::table('letter_number_sequences')->where('id', $row->id)->update(['last_value' => $next, 'updated_at' => now()]);

        return $next;
    }

    /** Fills a number format's tokens. */
    public function format(string $format, int $digits, CarbonInterface $date, int $sequence, ?string $code = null, ?string $language = null): string
    {
        return strtr($format, [
            '{seq}'   => str_pad((string) $sequence, max(1, $digits), '0', STR_PAD_LEFT),
            '{code}'  => (string) $code,
            '{lang}'  => LetterSetting::current()->languageCode($language),
            '{day}'   => $date->format('d'),
            '{month}' => $date->format('m'),
            '{roman}' => self::ROMAN_MONTHS[$date->month],
            '{year}'  => $date->format('Y'),
            '{yy}'    => $date->format('y'),
        ]);
    }

    public function outgoingNumber(CarbonInterface $date, int $sequence, ?string $code, ?string $language): string
    {
        $settings = LetterSetting::current();

        return $this->format($settings->outgoing_number_format, $settings->outgoing_number_digits, $date, $sequence, $code, $language);
    }

    public function agendaNumber(CarbonInterface $date, int $sequence): string
    {
        $settings = LetterSetting::current();

        return $this->format($settings->incoming_agenda_format, $settings->incoming_agenda_digits, $date, $sequence);
    }

    /**
     * Takes the next outgoing number for a letter and returns [number, sequence].
     * A number typed by hand earlier may already hold the one generated, so the
     * counter moves on until the number is free. Inside DB::transaction().
     */
    public function takeOutgoing(CarbonInterface $date, ?string $code, ?string $language): array
    {
        do {
            $sequence = $this->take(self::OUTGOING, $date->year);
            $number = $this->outgoingNumber($date, $sequence, $code, $language);
        } while ($this->outgoingNumberUsed($number));

        return [$number, $sequence];
    }

    /** [agenda number, sequence] for an incoming letter. Inside DB::transaction(). */
    public function takeAgenda(CarbonInterface $date): array
    {
        do {
            $sequence = $this->take(self::INCOMING, $date->year);
            $number = $this->agendaNumber($date, $sequence);
        } while (Letter::withTrashed()->where('direction', self::INCOMING)->where('agenda_number', $number)->exists());

        return [$number, $sequence];
    }

    /** Whether an outgoing number is already on a letter of the register or an offering letter. */
    public function outgoingNumberUsed(string $number, ?int $exceptLetterId = null): bool
    {
        return Letter::withTrashed()
                ->where('direction', self::OUTGOING)
                ->where('letter_number', $number)
                ->when($exceptLetterId, fn ($q) => $q->whereKeyNot($exceptLetterId))
                ->exists()
            || ($exceptLetterId === null && Offer::where('letter_number', $number)->exists());
    }
}
