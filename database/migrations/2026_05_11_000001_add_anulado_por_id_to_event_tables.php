<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = [
        'facturas',
        'ingresos_pagos',
        'ingreso_manuales',
        'ingreso_mostradors',
        'egreso_manuales',
        'egreso_compras',
        'compras',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'anulado_por_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('anulado_por_id')->nullable()->after('estado');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'anulado_por_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('anulado_por_id');
                });
            }
        }
    }
};
