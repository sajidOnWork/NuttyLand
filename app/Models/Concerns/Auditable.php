<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create / update / delete events for administrative data
 * (report §13.1 "Audit logging for administrative changes").
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAudit('updated', $old, $changes);
        });

        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->auditableAttributes($model->getOriginal()), []));
    }

    protected function auditableAttributes(array $attributes): array
    {
        $exclude = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        return array_diff_key($attributes, array_flip($exclude));
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
