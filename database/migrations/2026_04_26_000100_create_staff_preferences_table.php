<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if (! $schema->hasTable('staff_preferences')) {
            $this->createTable($schema);

            return;
        }

        $this->guardRequiredColumns($schema);
    }

    public function down(): void
    {
        Schema::connection('osticket2')->dropIfExists('staff_preferences');
    }

    private function createTable(Builder $schema): void
    {
        $schema->create('staff_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->string('theme', 16)->default('system');
            $table->string('language', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('notifications')->nullable();
            $table->timestamps();
        });
    }

    private function guardRequiredColumns(Builder $schema): void
    {
        $existingColumns = $schema->getColumnListing('staff_preferences');
        $missingColumns = array_values(array_diff(['id', 'staff_id'], $existingColumns));

        if ($missingColumns === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Existing %s table is missing required column(s): %s.',
            $schema->getConnection()->getTablePrefix().'staff_preferences',
            implode(', ', $missingColumns),
        ));
    }
};
