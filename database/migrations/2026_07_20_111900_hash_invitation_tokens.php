<?php

use App\Models\CompanyInvitation;
use App\Models\UserInvitation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_invitations')) {
            DB::table('company_invitations')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    if ($this->alreadyHashed((string) $row->token)) {
                        continue;
                    }

                    DB::table('company_invitations')->where('id', $row->id)->update([
                        'token' => CompanyInvitation::hashToken((string) $row->token),
                    ]);
                }
            });
        }

        if (Schema::hasTable('user_invitations')) {
            DB::table('user_invitations')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    if ($this->alreadyHashed((string) $row->token)) {
                        continue;
                    }

                    DB::table('user_invitations')->where('id', $row->id)->update([
                        'token' => UserInvitation::hashToken((string) $row->token),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Обратное преобразование невозможно: plaintext не восстанавливается из HMAC.
    }

    /**
     * HMAC-SHA256 даёт 64 hex-символа в нижнем регистре.
     * Str::random(64) содержит буквы верхнего регистра / не-hex — считаем plaintext.
     */
    private function alreadyHashed(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token) && $token === strtolower($token);
    }
};
