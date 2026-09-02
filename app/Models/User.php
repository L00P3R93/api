<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Util\Badge;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone_no',
        'email_verified_at',
        'role_id',
        'password',
        'remember_token',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Set default password as hash of username if not explicitly set
            if (empty($user->password)) {
                $user->password = Hash::make($user->username);
            }
        });
    }

    /**
     * Generate a unique identifier for the user.
     *
     * @return string A string containing the year and month of the user's creation date,
     *                followed by the user's id.
     */
    public function getUserId(): string
    {
        return date('Ym', strtotime($this->created_at)).str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the status badge HTML for this role.
     */
    public function getStatusBadge(): string
    {
        return match ($this->status) {
            1 => Badge::set('primary', 'Active'),
            2 => Badge::set('danger', 'Blocked'),
            3 => Badge::set('secondary', 'Inactive'),
            default => Badge::set('secondary', 'NONE'),
        };
    }

    public static function getSingle($id)
    {
        return self::find($id);
    }
}
