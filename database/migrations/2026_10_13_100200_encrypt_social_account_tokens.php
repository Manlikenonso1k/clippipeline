<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COLUMNS = ['access_token', 'refresh_token'];

    /**
     * Data migration: encrypt plaintext OAuth tokens before SocialAccount switches
     * to `encrypted` casts. Values that already decrypt are left alone, so it is
     * safe to re-run.
     */
    public function up(): void
    {
        $this->transform(function (string $value): ?string {
            try {
                Crypt::decryptString($value);

                return null; // already encrypted
            } catch (DecryptException) {
                return Crypt::encryptString($value);
            }
        });
    }

    public function down(): void
    {
        $this->transform(function (string $value): ?string {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return null; // already plaintext
            }
        });
    }

    /** @param  callable(string): ?string  $map  returns the new value, or null to keep it */
    private function transform(callable $map): void
    {
        DB::table('social_accounts')->select(array_merge(['id'], self::COLUMNS))
            ->chunkById(200, function ($rows) use ($map) {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach (self::COLUMNS as $column) {
                        $value = $row->{$column};
                        if (is_string($value) && $value !== '' && ($new = $map($value)) !== null) {
                            $changes[$column] = $new;
                        }
                    }
                    if ($changes) {
                        DB::table('social_accounts')->where('id', $row->id)->update($changes);
                    }
                }
            });
    }
};
