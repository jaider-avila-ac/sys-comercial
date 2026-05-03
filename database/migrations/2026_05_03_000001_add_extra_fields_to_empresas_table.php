<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('matricula', 80)->nullable()->after('nit');
            $table->string('pagina_web', 120)->nullable()->after('email');
            $table->string('doc_tema', 5)->nullable()->default('1')->after('logo_updated_at');
            $table->string('doc_color', 10)->nullable()->default('#1d4ed8')->after('doc_tema');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['matricula', 'pagina_web', 'doc_tema', 'doc_color']);
        });
    }
};
