<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('financier_requests');
    }

    public function down(): void
    {
        // Intentionally not recreated: financier requests have been retired.
    }
};
