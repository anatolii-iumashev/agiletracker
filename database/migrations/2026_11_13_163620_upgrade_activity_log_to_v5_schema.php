<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-activitylog v5 replaced the batch system with a dedicated
 * `attribute_changes` column: model changes no longer live in `properties`,
 * which now holds custom user data only.
 *
 * A database that was migrated from v4 already has the table, so the schema is
 * adjusted in place; a fresh database gets the consolidated v5 shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table) {
                $table->id();
                $table->string('log_name')->nullable()->index();
                $table->text('description');
                $table->nullableMorphs('subject', 'subject');
                $table->string('event')->nullable();
                $table->nullableMorphs('causer', 'causer');
                $table->json('attribute_changes')->nullable();
                $table->json('properties')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasColumn('activity_log', 'attribute_changes')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->json('attribute_changes')->nullable()->after('causer_id');
            });
        }

        if (Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropColumn('batch_uuid');
            });
        }

        $this->moveChangesToAttributeChanges();
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_log', 'attribute_changes')) {
            DB::table('activity_log')
                ->whereNotNull('attribute_changes')
                ->eachById(function (object $row): void {
                    $changes = $this->decodeJson($row->attribute_changes);
                    $properties = $this->decodeJson($row->properties) ?? [];

                    DB::table('activity_log')->where('id', $row->id)->update([
                        'properties' => json_encode($changes + $properties),
                        'attribute_changes' => null,
                    ]);
                });

            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropColumn('attribute_changes');
            });
        }

        if (! Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->uuid('batch_uuid')->nullable()->after('properties');
            });
        }
    }

    private function moveChangesToAttributeChanges(): void
    {
        DB::table('activity_log')
            ->whereNotNull('properties')
            ->eachById(function (object $row): void {
                $properties = $this->decodeJson($row->properties);

                if ($properties === null) {
                    return;
                }

                $keys = array_flip(['attributes', 'old']);

                if (array_intersect_key($properties, $keys) === []) {
                    return;
                }

                $changes = array_intersect_key($properties, $keys);
                $remaining = array_diff_key($properties, $keys);

                DB::table('activity_log')->where('id', $row->id)->update([
                    'attribute_changes' => $changes === [] ? null : json_encode($changes),
                    'properties' => $remaining === [] ? null : json_encode($remaining),
                ]);
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }
};
