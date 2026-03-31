<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->date('birth_date')->nullable()->after('phone');
            $table->string('gender', 30)->nullable()->after('birth_date');
            $table->string('address_line')->nullable()->after('gender');
            $table->string('city')->nullable()->after('address_line');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'birth_date',
                'gender',
                'address_line',
                'city',
                'province',
                'postal_code',
            ]);
        });
    }
};
