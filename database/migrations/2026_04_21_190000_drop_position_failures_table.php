<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('position_failures');
    }

    public function down(): void
    {
        // Cədvəl könəldirilmiş sayılır; yenidən yaratmaq üçün köhnə miqrasiyanı işə salın.
    }
};
