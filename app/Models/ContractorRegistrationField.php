<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContractorRegistrationField extends Model
{
    public const SECTION_PERSONAL = 'personal';

    public const SECTION_COMPANY = 'company';

    public const SECTION_BANK = 'bank';

    public const SECTION_SERVICES = 'services';

    public const TYPE_TEXT = 'text';

    public const TYPE_EMAIL = 'email';

    public const TYPE_TEL = 'tel';

    public const TYPE_PASSWORD = 'password';

    public const TYPE_DATE = 'date';

    public const TYPE_SELECT = 'select';

    public const TYPE_MULTISELECT = 'multiselect';

    public const TYPE_FILE = 'file';

    public const TYPE_TEXTAREA = 'textarea';

    protected $fillable = [
        'key',
        'section',
        'label',
        'label_ar',
        'field_type',
        'option_source',
        'is_required',
        'is_enabled',
        'is_multiple',
        'sort_order',
        'placeholder',
        'placeholder_ar',
        'meta',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_enabled' => 'boolean',
        'is_multiple' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'section' => $this->section,
            'label' => $this->label,
            'label_ar' => $this->label_ar,
            'field_type' => $this->field_type,
            'option_source' => $this->option_source,
            'is_required' => (bool) $this->is_required,
            'is_enabled' => (bool) $this->is_enabled,
            'is_multiple' => (bool) $this->is_multiple,
            'sort_order' => (int) $this->sort_order,
            'placeholder' => $this->placeholder,
            'placeholder_ar' => $this->placeholder_ar,
            'meta' => $this->meta ?? [],
        ];
    }

    public function toPublicArray(?array $options = null): array
    {
        $payload = [
            'key' => $this->key,
            'section' => $this->section,
            'label' => $this->label,
            'label_ar' => $this->label_ar,
            'field_type' => $this->field_type,
            'is_required' => (bool) $this->is_required,
            'is_multiple' => (bool) $this->is_multiple || $this->field_type === self::TYPE_MULTISELECT,
            'sort_order' => (int) $this->sort_order,
            'placeholder' => $this->placeholder,
            'placeholder_ar' => $this->placeholder_ar,
            'option_source' => $this->option_source,
            'accept' => $this->meta['accept'] ?? null,
        ];

        if ($options !== null) {
            $payload['options'] = $options;
        }

        return $payload;
    }
}
