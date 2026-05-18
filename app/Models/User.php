<?php

namespace App\Models;

use App\Enums\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    public static function defaultThemeSettings(): array
    {
        return [
            'data-bs-theme' => 'light',
            'data-layout-mode' => 'fluid',
            'data-topbar-color' => 'light',
            'data-menu-color' => 'light',
            'data-sidenav-size' => 'sm-hover-active',
        ];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'login',
        'email',
        'password',
        'must_change_password',
        'ativo',
        'theme_settings',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'ativo' => 'boolean',
            'theme_settings' => 'array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function themeSettings(): array
    {
        return array_merge(
            self::defaultThemeSettings(),
            is_array($this->theme_settings) ? $this->theme_settings : [],
        );
    }

    /**
     * @return BelongsToMany<UnidadeNegocio, $this>
     */
    public function unidadesNegocio(): BelongsToMany
    {
        return $this->belongsToMany(UnidadeNegocio::class, 'unidade_negocio_user')
            ->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function unidadeNegocioIdsPermitidas(): array
    {
        return $this->unidadesNegocio()
            ->pluck('unidades_negocio.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function podeMovimentarUnidade(int $unidadeNegocioId): bool
    {
        if ($this->hasAnyRole([Roles::PROGRAMADOR->value, Roles::ADMINISTRADOR->value])) {
            return true;
        }

        return $this->unidadesNegocio()
            ->where('unidades_negocio.id', $unidadeNegocioId)
            ->exists();
    }
}
