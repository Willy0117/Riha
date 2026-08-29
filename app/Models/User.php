<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasProfilePhoto, Notifiable, TwoFactorAuthenticatable, HasRoles;
    
    protected $guard_name = 'web';

    protected $fillable = [
        'username',
        'email',
        'password',
        'tenant_id',
        'member_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    // [今回変更] name は実カラムではなく member.name 経由のアクセサに変更
    protected $appends = [
        'profile_photo_url',
        'name',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
    // Tenant リレーション
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    // Organization リレーション
/*
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
*/
    /**
     * ログインユーザーの tenant_id でフィルターしたロールを取得
     */
    public function tenantRoles()
    {
        if ($this->isSuperAdmin()) {
            return $this->roles();
        }
        return $this->roles()->where('roles.tenant_id', $this->tenant_id); 
    }

    /**
     * ログインユーザーの tenant_id でフィルターした権限を取得
     */
    public function tenantPermissions()
    {
        if ($this->isSuperAdmin()) {
            return $this->getAllPermissions();
        }

        return $this->getAllPermissions()
            ->where('tenant_id', $this->tenant_id);
    }

    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    // [今回変更] users.name を削除したため、member.name（last_name+first_name のアクセサ）を返す。
    // member_id は NOT NULL 制約により、この時点で必ず紐づいている前提。
    public function getNameAttribute()
    {
        return $this->member?->name;
    }
}
